<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Parameter;
use Mmmut\AcademicAudit\Services\CycleService;

/** IQAC maintains max marks and evaluation slabs (per cycle). */
class ParameterController extends Controller
{
    public function index(Cycle $cycle)
    {
        $this->iqac();
        $cycle->load('parameters.slabs');
        return $this->view('admin.parameters', ['cycle' => $cycle, 'sectionMax' => $cycle->sectionMax()]);
    }

    public function edit(Parameter $parameter)
    {
        $this->iqac();
        return $this->view('admin.parameter-edit', ['param' => $parameter->load('slabs', 'cycle'), 'def' => $parameter->definition()]);
    }

    public function update(Request $request, Parameter $parameter, CycleService $cycles)
    {
        $this->iqac();
        $this->guardLocked($parameter->cycle);
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'max_marks' => 'required|numeric|min:0|max:1000',
            'notes' => 'nullable|string|max:2000',
            'slabs' => 'array',
            'slabs.*.op' => 'nullable|in:gt,gte',
            'slabs.*.threshold' => 'nullable|numeric',
            'slabs.*.marks' => 'nullable|numeric|min:0',
        ]);
        $cycles->updateParameter($parameter, $data);
        return redirect()->route('audit.admin.parameters', $parameter->cycle_id)->with('status', "Parameter {$parameter->code} updated.");
    }

    public function reset(Cycle $cycle, CycleService $cycles)
    {
        $this->iqac();
        $this->guardLocked($cycle);
        $cycles->seedParameters($cycle, true);
        return back()->with('status', 'Parameters reset to the printed evaluation scheme.');
    }

    private function guardLocked(Cycle $cycle): void
    {
        abort_if(
            Audit::where('cycle_id', $cycle->id)->whereIn('status', [Audit::AUDITED, Audit::LOCKED])->exists(),
            422,
            'Some audits of this cycle are already audited; marks scheme can no longer be changed.'
        );
    }
}
