<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Services\Ai\DocumentExtractor;
use Mmmut\AcademicAudit\Services\Ai\ExtractionException;
use Mmmut\AcademicAudit\Services\SettingsService;
use Mmmut\AcademicAudit\Support\ItemForm;

/** "Fill from document": reads an uploaded proof and returns values for the add-row form. */
class AiController extends Controller
{
    public const TEMP_DIR = 'tmp-proofs';

    public function extract(Request $request, Audit $audit, string $code, DocumentExtractor $extractor, SettingsService $settings)
    {
        $this->viewable($audit);
        $def = $this->definition($code);
        $access = $this->access();
        abort_unless($access->canEdit($audit) || ($def['faculty'] && $access->canContribute($audit)), 403);
        if (! $settings->enabled('ai.document_autofill')) {
            return response()->json(['message' => 'Document auto-fill is switched off by IQAC.'], 422);
        }
        $request->validate(['document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp']);

        $limit = max(1, (int) $settings->get('ai.daily_limit'));
        $used = DB::table('aa_ai_logs')->where('user_id', $access->userId())->where('feature', 'autofill')
            ->where('status', 'ok')->where('created_at', '>=', now()->startOfDay())->count();
        if ($used >= $limit) {
            return response()->json(['message' => "Daily limit of {$limit} document auto-fills reached. Please fill the row manually."], 429);
        }

        $file = $request->file('document');
        $log = ['user_id' => $access->userId(), 'audit_id' => $audit->id, 'parameter_code' => $code, 'feature' => 'autofill', 'created_at' => now()];
        try {
            $result = $extractor->extract($def, $file->getRealPath(), $file->getMimeType());
        } catch (ExtractionException $e) {
            DB::table('aa_ai_logs')->insert($log + ['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // keep only values that pass the same validation as manual entry
        $data = [];
        $skipped = [];
        $rules = ItemForm::rules($def, '');
        foreach ($def['columns'] as $c) {
            $v = $result['data'][$c['key']] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            $rule = array_values(array_filter($rules[$c['key']], fn ($r) => $r !== 'required'));
            if (validator([$c['key'] => $v], [$c['key'] => $rule])->fails()) {
                $skipped[] = $c['label'];
                continue;
            }
            $data[$c['key']] = $v;
        }

        // the document becomes the row's proof when the user saves the row
        $token = Str::random(32);
        $path = $file->storeAs(config('academic-audit.upload_dir').'/'.self::TEMP_DIR, $token.'.'.$file->extension(), config('academic-audit.disk'));
        session()->put("audit.ai_proof.{$token}", ['path' => $path, 'audit_id' => $audit->id, 'name' => $file->getClientOriginalName()]);

        DB::table('aa_ai_logs')->insert($log + ['status' => 'ok', 'model' => $result['model'], 'input_tokens' => $result['input_tokens'], 'output_tokens' => $result['output_tokens']]);

        return response()->json([
            'data' => $data,
            'proof_token' => $token,
            'filled' => count($data),
            'message' => count($data)
                ? count($data).' field(s) filled from the document. Please check every value before saving.'.($skipped ? ' Could not use: '.implode(', ', $skipped).'.' : '')
                : 'No matching values were found in the document.',
        ]);
    }

    /** Move a temp proof saved by extract() to the row's proof path. */
    public static function claimProof(string $token, Audit $audit): ?string
    {
        $info = session()->pull("audit.ai_proof.{$token}");
        if (! $info || $info['audit_id'] !== $audit->id) {
            return null;
        }
        $disk = Storage::disk(config('academic-audit.disk'));
        $target = config('academic-audit.upload_dir')."/{$audit->id}/proofs/".basename($info['path']);
        return $disk->exists($info['path']) && $disk->move($info['path'], $target) ? $target : null;
    }
}
