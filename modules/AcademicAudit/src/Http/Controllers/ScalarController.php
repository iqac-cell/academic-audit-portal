<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Services\ScoreService;
use Mmmut\AcademicAudit\Support\Catalogue;

/** Single-value inputs and denominators (graduates, eligible students, PhD seats, Yes/No items). */
class ScalarController extends Controller
{
    public function edit(Audit $audit, ScoreService $scores, PrefillProvider $prefill)
    {
        $this->viewable($audit);
        return $this->view('audit.inputs', [
            'audit' => $audit,
            'values' => $audit->scalars ?? [],
            'resolved' => $scores->scalars($audit),
            'suggested' => $prefill->scalars($audit),
            'canEdit' => $this->access()->canEdit($audit),
        ]);
    }

    public function update(Request $request, Audit $audit)
    {
        $this->editable($audit);
        $rules = [];
        foreach (Catalogue::SCALARS as $key => $def) {
            $rules[$key] = match ($def['type']) {
                'number' => 'nullable|numeric|min:0',
                'yn' => 'nullable|in:Y,N',
                'url' => 'nullable|string|max:500',
                default => 'nullable|string|max:500',
            };
        }
        $data = $request->validate($rules);
        $audit->update(['scalars' => array_merge($audit->scalars ?? [], $data)]);
        $audit->log('inputs', 'Denominators / Yes-No inputs updated');
        return back()->with('status', 'Inputs saved.');
    }
}
