<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Services\CycleService;

class CycleController extends Controller
{
    public function index()
    {
        $this->iqac();
        return $this->view('admin.cycles', ['cycles' => Cycle::withCount('audits')->orderByDesc('academic_year')->get()]);
    }

    public function store(Request $request, CycleService $cycles)
    {
        $this->iqac();
        $data = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{2}$/', 'unique:aa_cycles,academic_year'],
            'opens_on' => 'nullable|date',
            'closes_on' => 'nullable|date|after_or_equal:opens_on',
        ], ['academic_year.regex' => 'Use the format 2026-27.']);
        $cycles->create($data['academic_year'], array_filter($data, fn ($k) => $k !== 'academic_year', ARRAY_FILTER_USE_KEY));
        return back()->with('status', 'Cycle created with department audits.');
    }

    public function update(Request $request, Cycle $cycle, CycleService $cycles)
    {
        $this->iqac();
        $data = $request->validate([
            'opens_on' => 'nullable|date',
            'closes_on' => 'nullable|date',
            'status' => 'required|in:open,closed',
            'awarding_rule' => 'required|in:average,min,max',
        ]);
        $cycle->update($data);
        $created = $cycles->ensureAudits($cycle);
        return back()->with('status', 'Cycle updated.'.($created ? " {$created} new department audit(s) created." : ''));
    }
}
