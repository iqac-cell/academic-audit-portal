<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Services\ReportBuilder;
use Mmmut\AcademicAudit\Services\ScoreService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ReportController extends Controller
{
    public function show(Audit $audit, ReportBuilder $reports)
    {
        $this->viewable($audit);
        return view('audit::report.audit', $reports->data($audit) + ['pdf' => false]);
    }

    public function pdf(Audit $audit, ReportBuilder $reports)
    {
        $this->viewable($audit);
        return Pdf::loadView('audit::report.audit', $reports->data($audit) + ['pdf' => true])
            ->setPaper('a4')
            ->download($this->fileName($audit).'.pdf');
    }

    public function excel(Audit $audit, ReportBuilder $reports)
    {
        $this->viewable($audit);
        return $this->xlsx($reports->excel($audit), $this->fileName($audit).'.xlsx');
    }

    public function university(Request $request, ScoreService $scores)
    {
        abort_unless($this->access()->isIqac() || $this->access()->isAuditor(), 403);
        $cycle = $request->filled('cycle') ? Cycle::findOrFail($request->cycle) : Cycle::current();
        abort_unless($cycle, 404);
        $rows = $cycle->audits()->with('department', 'cycle')->get()->sortBy('department.name')
            ->map(fn ($a) => ['audit' => $a, 'summary' => $scores->summary($a)]);
        return $this->view('report.university', ['cycle' => $cycle, 'rows' => $rows, 'cycles' => Cycle::orderByDesc('academic_year')->get()]);
    }

    public function universityExcel(Request $request, ReportBuilder $reports)
    {
        abort_unless($this->access()->isIqac() || $this->access()->isAuditor(), 403);
        $cycle = $request->filled('cycle') ? Cycle::findOrFail($request->cycle) : Cycle::current();
        return $this->xlsx($reports->universityExcel($cycle), "academic-audit-{$cycle->academic_year}-summary.xlsx");
    }

    private function fileName(Audit $audit): string
    {
        return 'academic-audit-'.($audit->department->short_name ?: $audit->department->id).'-'.$audit->cycle->academic_year;
    }

    private function xlsx(Spreadsheet $book, string $name)
    {
        $file = tempnam(sys_get_temp_dir(), 'aa');
        IOFactory::createWriter($book, 'Xlsx')->save($file);
        return response()->download($file, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
