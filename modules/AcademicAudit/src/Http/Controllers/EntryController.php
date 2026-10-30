<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Services\ScoreService;
use Mmmut\AcademicAudit\Support\ItemForm;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class EntryController extends Controller
{
    public function index(Request $request, Audit $audit, string $code, ScoreService $scores, PrefillProvider $prefill,
        \Mmmut\AcademicAudit\Services\SettingsService $settings, \Mmmut\AcademicAudit\Services\Ai\DataQualityChecker $quality,
        \Mmmut\AcademicAudit\Services\Ai\ScoreAdvisor $advisor)
    {
        $this->viewable($audit);
        $def = $this->definition($code);
        $access = $this->access();
        $facultyView = ! $access->isIqac() && ! $access->isHodOf($audit) && ! $access->isAuditorOf($audit);

        $entries = Entry::where('audit_id', $audit->id)->where('parameter_code', $code)
            ->when($facultyView, fn ($q) => $q->where('faculty_id', $access->faculty()?->id ?? 0))
            ->orderBy('id')->get();

        $editing = $request->filled('edit') ? $entries->firstWhere('id', (int) $request->edit) : null;
        if ($editing && ! $this->canModify($audit, $editing)) {
            $editing = null;
        }

        return $this->view('audit.items', [
            'audit' => $audit,
            'code' => $code,
            'def' => $def,
            'param' => $audit->cycle->parameters()->with('slabs')->where('code', $code)->firstOrFail(),
            'entries' => $entries,
            'editing' => $editing,
            'eval' => $scores->evaluate($audit)[$code],
            'canAdd' => $access->canEdit($audit) || ($def['faculty'] && $access->canContribute($audit)),
            'canReview' => $access->canEdit($audit),
            'facultyView' => $facultyView,
            'programs' => ItemForm::programs($audit),
            'facultyNames' => ItemForm::facultyNames($audit),
            'prefillName' => in_array($code, $prefill->supports()) ? (method_exists($prefill, 'nameFor') ? $prefill->nameFor($code) : $prefill->name()) : null,
            'issues' => $settings->enabled('ai.quality_checks')
                ? $quality->check($audit)->where('code', $code)->filter(fn ($i) => ! $facultyView || $entries->contains('id', $i['entry_id']))->values()
                : collect(),
            'suggestion' => $settings->enabled('ai.suggestions') ? $advisor->suggestions($audit)->get($code) : null,
            'autofill' => $settings->enabled('ai.document_autofill'),
            'proofMode' => $settings->get('workflow.proof_mode'),
            'attachments' => $audit->attachments()->where('slot', $code)->get(),
        ]);
    }

    public function store(Request $request, Audit $audit, string $code)
    {
        $this->viewable($audit);
        $def = $this->definition($code);
        $access = $this->access();
        $asHod = $access->canEdit($audit);
        abort_unless($asHod || ($def['faculty'] && $access->canContribute($audit)), 403);

        $request->validate(ItemForm::rules($def) + $this->proofRule($request, $audit, $code), [], ItemForm::attributes($def));
        $data = ItemForm::clean($def, $request->input('data', []));

        $faculty = $asHod ? null : $access->faculty();
        $entry = Entry::create([
            'audit_id' => $audit->id,
            'parameter_code' => $code,
            'data' => $data,
            'faculty_id' => $faculty?->id,
            'created_by' => $access->userId(),
            'status' => $asHod ? Entry::APPROVED : Entry::PENDING,
            'source' => $asHod ? 'manual' : 'faculty',
        ]);
        $this->saveProof($request, $audit, $entry);

        return redirect()->route('audit.items', [$audit, $code])
            ->with('status', $asHod ? 'Row added.' : 'Row added and sent to HOD for approval.');
    }

    public function update(Request $request, Audit $audit, Entry $entry)
    {
        $this->viewable($audit);
        abort_unless($this->canModify($audit, $entry), 403);
        $def = $this->definition($entry->parameter_code);
        $request->validate(ItemForm::rules($def) + $this->proofRule($request, $audit, $entry->parameter_code, $entry), [], ItemForm::attributes($def));

        $entry->data = ItemForm::clean($def, $request->input('data', []));
        if ($entry->status === Entry::REJECTED && ! $this->access()->canEdit($audit)) {
            $entry->status = Entry::PENDING; // faculty corrected a rejected row
        }
        $entry->save();
        $this->saveProof($request, $audit, $entry);

        return redirect()->route('audit.items', [$audit, $entry->parameter_code])->with('status', 'Row updated.');
    }

    public function destroy(Audit $audit, Entry $entry)
    {
        $this->viewable($audit);
        abort_unless($this->canModify($audit, $entry), 403);
        if ($entry->proof_path) {
            Storage::disk(config('academic-audit.disk'))->delete($entry->proof_path);
        }
        $entry->delete();
        return back()->with('status', 'Row deleted.');
    }

    /** HOD approves / rejects a faculty contribution. */
    public function review(Request $request, Audit $audit, Entry $entry, \Mmmut\AcademicAudit\Services\Notifier $notifier)
    {
        $this->editable($audit);
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'remark' => 'nullable|string|max:500']);
        $entry->update([
            'status' => $data['decision'] === 'approve' ? Entry::APPROVED : Entry::REJECTED,
            'remark' => $data['remark'] ?? null,
        ]);
        if ($entry->source === 'faculty') {
            $notifier->entryReviewed($entry);
        }
        return back()->with('status', $data['decision'] === 'approve' ? 'Row approved.' : 'Row rejected.');
    }

    public function proof(Audit $audit, Entry $entry)
    {
        $this->viewable($audit);
        abort_unless($entry->proof_path, 404);
        return Storage::disk(config('academic-audit.disk'))->download($entry->proof_path);
    }

    public function prefill(Audit $audit, string $code, PrefillProvider $prefill)
    {
        $this->editable($audit);
        $def = $this->definition($code);
        $existing = Entry::where('audit_id', $audit->id)->where('parameter_code', $code)->whereNotNull('source_ref')->pluck('source_ref')->flip();
        $added = 0;
        foreach ($prefill->rows($code, $audit) as $row) {
            if (isset($existing[$row['source_ref']])) {
                continue;
            }
            Entry::create([
                'audit_id' => $audit->id,
                'parameter_code' => $code,
                'data' => ItemForm::clean($def, $row['data']),
                'faculty_id' => $row['faculty_id'] ?? null,
                'created_by' => $this->access()->userId(),
                'status' => Entry::APPROVED,
                'source' => $row['source'] ?? 'iqac',
                'source_ref' => $row['source_ref'],
            ]);
            $added++;
        }
        $source = method_exists($prefill, 'nameFor') ? $prefill->nameFor($code) : $prefill->name();
        return back()->with('status', "{$added} rows prefilled from {$source}. Please verify them.");
    }

    public function template(Audit $audit, string $code)
    {
        $this->viewable($audit);
        $def = $this->definition($code);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(str_replace('.', '', $code));
        foreach ($def['columns'] as $i => $c) {
            $sheet->setCellValue([$i + 1, 1], $c['label']);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth(24);
            $hint = match ($c['type']) {
                'yn' => 'Y / N', 'date' => 'YYYY-MM-DD', 'number' => 'number',
                'select' => implode(' / ', $c['options']), default => '',
            };
            $sheet->setCellValue([$i + 1, 2], $hint);
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('2:2')->getFont()->setItalic(true)->getColor()->setRGB('888888');

        $file = tempnam(sys_get_temp_dir(), 'aa').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($file);
        return response()->download($file, "audit-{$code}-template.xlsx")->deleteFileAfterSend();
    }

    public function import(Request $request, Audit $audit, string $code)
    {
        $this->editable($audit);
        $def = $this->definition($code);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        $errors = [];
        $added = 0;
        foreach (array_slice($rows, 1) as $n => $cells) {
            if (! array_filter($cells, fn ($c) => $c !== null && $c !== '')) {
                continue;
            }
            $data = [];
            foreach ($def['columns'] as $i => $c) {
                $v = $cells[$i] ?? null;
                if ($c['type'] === 'yn' && $v !== null) {
                    $v = in_array(strtoupper(trim((string) $v)), ['Y', 'YES']) ? 'Y' : (in_array(strtoupper(trim((string) $v)), ['N', 'NO']) ? 'N' : $v);
                }
                $data[$c['key']] = is_string($v) ? trim($v) : $v;
            }
            // skip the hint row of our own template
            if ($n === 0 && in_array($data[$def['columns'][0]['key']] ?? null, ['', 'number', 'YYYY-MM-DD', 'Y / N'], true)) {
                continue;
            }
            $v = validator(['data' => $data], ItemForm::rules($def), [], ItemForm::attributes($def));
            if ($v->fails()) {
                $errors[] = 'Row '.($n + 2).': '.implode(' ', $v->errors()->all());
                continue;
            }
            Entry::create([
                'audit_id' => $audit->id, 'parameter_code' => $code, 'data' => ItemForm::clean($def, $data),
                'created_by' => $this->access()->userId(), 'status' => Entry::APPROVED, 'source' => 'import',
            ]);
            $added++;
        }
        return back()->with('status', "{$added} rows imported.")->withErrors($errors ? ['import' => array_slice($errors, 0, 20)] : []);
    }

    private function canModify(Audit $audit, Entry $entry): bool
    {
        $access = $this->access();
        if ($access->canEdit($audit)) {
            return true;
        }
        return $access->canContribute($audit)
            && $entry->faculty_id === $access->faculty()?->id
            && $entry->status !== Entry::APPROVED;
    }

    private function proofRule(Request $request, Audit $audit, string $code, ?Entry $entry = null): array
    {
        $required = app(\Mmmut\AcademicAudit\Services\SettingsService::class)->get('workflow.proof_mode') === 'require'
            && ! $entry?->proof_path
            && ! $request->filled('ai_proof_token')
            && ! $audit->attachments()->where('slot', $code)->exists();
        return ['proof' => ($required ? 'required' : 'nullable').'|file|max:'.config('academic-audit.max_upload_kb').'|mimes:'.config('academic-audit.upload_mimes')];
    }

    private function saveProof(Request $request, Audit $audit, Entry $entry): void
    {
        if (! $request->hasFile('proof')) {
            if ($request->filled('ai_proof_token') && ($path = AiController::claimProof((string) $request->ai_proof_token, $audit))) {
                if ($entry->proof_path) {
                    Storage::disk(config('academic-audit.disk'))->delete($entry->proof_path);
                }
                $entry->update(['proof_path' => $path]);
            }
            return;
        }
        $disk = Storage::disk(config('academic-audit.disk'));
        if ($entry->proof_path) {
            $disk->delete($entry->proof_path);
        }
        $entry->update(['proof_path' => $request->file('proof')->store(config('academic-audit.upload_dir')."/{$audit->id}/proofs", config('academic-audit.disk'))]);
    }
}
