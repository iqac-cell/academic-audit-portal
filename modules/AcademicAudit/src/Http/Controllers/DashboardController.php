<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Services\ScoreService;

class DashboardController extends Controller
{
    public function index(Request $request, ScoreService $scores)
    {
        $access = $this->access();
        $cycles = Cycle::orderByDesc('academic_year')->get();
        $cycle = $request->filled('cycle') ? $cycles->firstWhere('id', (int) $request->cycle) : Cycle::current();

        if (! $cycle) {
            return $this->view('dashboard', ['cycle' => null, 'cycles' => $cycles, 'rows' => collect()]);
        }

        $query = Audit::with('department', 'cycle', 'auditor1', 'auditor2')->where('cycle_id', $cycle->id);
        if (! $access->isIqac()) {
            $deptIds = array_filter(array_merge($access->hodDepartmentIds(), [$access->faculty()?->department_id]));
            $query->where(fn ($q) => $q->whereIn('department_id', $deptIds ?: [0])
                ->orWhere('auditor1_user_id', $access->userId())
                ->orWhere('auditor2_user_id', $access->userId()));
        }
        $audits = $query->get()->sortBy('department.name');

        $rows = $audits->map(function (Audit $a) use ($scores) {
            $eval = $scores->evaluate($a);
            return [
                'audit' => $a,
                'summary' => $scores->summary($a, $eval),
                'filled' => $eval->filter(fn ($e) => $e['definition']['columns'] ? $e['rows'] > 0 : ($e['value'] !== null && ! $e['missing']))->count(),
                'params' => $eval->count(),
                'pending' => Entry::where('audit_id', $a->id)->where('status', Entry::PENDING)->count(),
            ];
        });

        $myPending = $access->faculty()
            ? Entry::where('faculty_id', $access->faculty()->id)->whereIn('audit_id', $audits->pluck('id'))->get()->groupBy('status')->map->count()
            : collect();

        return $this->view('dashboard', compact('cycle', 'cycles', 'rows', 'myPending'));
    }
}
