<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Support\Catalogue;

class BasicInfoController extends Controller
{
    public const LEVELS = ['UG' => 'B.Tech./B.Pharm/B.B.A.', 'PG' => 'M.Tech./MCA/MBA/MSc', 'PhD' => 'Ph.D.'];

    public function edit(Audit $audit)
    {
        $this->viewable($audit);
        return $this->view('audit.basic', [
            'audit' => $audit,
            'info' => $audit->basic_info ?? [],
            'levels' => self::LEVELS,
            'years' => Catalogue::YEARS,
            'canEdit' => $this->access()->canEdit($audit),
        ]);
    }

    public function update(Request $request, Audit $audit)
    {
        $this->editable($audit);
        $data = $request->validate([
            'about' => ['nullable', 'string', 'max:2000', function ($a, $v, $fail) {
                if (str_word_count((string) $v) > 150) {
                    $fail('About the department must be at most 150 words.');
                }
            }],
            'vision' => 'nullable|string|max:2000',
            'mission' => 'nullable|string|max:4000',
            'programs' => 'array',
            'programs.*.name' => 'nullable|string|max:200',
            'programs.*.level' => 'nullable|in:UG,PG,PhD',
            'programs.*.intake' => 'nullable|numeric|min:0',
            'programs.*.year' => 'nullable|integer|min:1900|max:2100',
            'sanctioned' => 'array',
            'sanctioned.*.body' => 'nullable|string|max:200',
            'sanctioned.*.ts' => 'nullable|numeric|min:0',
            'enrolled' => 'array',
            'enrolled.*.*' => 'nullable|numeric|min:0',
            'faculty' => 'array',
            'faculty.*.name' => 'nullable|string|max:200',
            'faculty.*.qualification' => 'nullable|string|max:200',
            'faculty.*.experience' => 'nullable|numeric|min:0',
            'faculty.*.doj' => 'nullable|string|max:20',
        ]);
        foreach (['programs', 'faculty'] as $list) {
            $data[$list] = array_values(array_filter($data[$list] ?? [], fn ($r) => filled($r['name'] ?? null)));
        }
        $audit->update(['basic_info' => $data]);
        $audit->log('basic_info', 'Basic information updated');
        return back()->with('status', 'Basic information saved.');
    }

    public function prefill(Audit $audit, PrefillProvider $prefill)
    {
        $this->editable($audit);
        $current = $audit->basic_info ?? [];
        foreach ($prefill->basicInfo($audit) as $key => $value) {
            if (blank($current[$key] ?? null) && filled($value)) {
                $current[$key] = $value;
            }
        }
        $audit->update(['basic_info' => $current]);
        return back()->with('status', 'Empty fields filled from '.$prefill->name().'. Please review before saving.');
    }
}
