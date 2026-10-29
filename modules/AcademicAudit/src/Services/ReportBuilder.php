<?php

namespace Mmmut\AcademicAudit\Services;

use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Support\Catalogue;
use Mmmut\AcademicAudit\Support\Fmt;
use Mmmut\AcademicAudit\Support\ItemForm;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportBuilder
{
    public function __construct(private ScoreService $scores) {}

    /** Everything the printed audit report needs. */
    public function data(Audit $audit): array
    {
        $audit->loadMissing('cycle.parameters.slabs', 'department', 'auditor1', 'auditor2', 'hod');
        $eval = $this->scores->evaluate($audit);

        return [
            'audit' => $audit,
            'info' => $audit->basic_info ?? [],
            'scalars' => $this->scores->scalars($audit),
            'eval' => $eval,
            'summary' => $this->scores->summary($audit, $eval),
            'entries' => Entry::where('audit_id', $audit->id)->approved()->orderBy('id')->get()->groupBy('parameter_code'),
            'sections' => Catalogue::bySection(),
            'annexures' => $audit->attachments()->get()->groupBy('slot'),
        ];
    }

    public function excel(Audit $audit): Spreadsheet
    {
        $d = $this->data($audit);
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Evaluation');

        $sheet->setCellValue('A1', 'Internal Academic Audit – '.$audit->department->name.' – '.$audit->cycle->academic_year);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $head = ['SN', 'Parameter', 'Indicator', 'Value', 'Max Mark', 'Marks Claimed', 'Auditor-1', 'Auditor-2', 'Marks Awarded', 'Remark'];
        $sheet->fromArray($head, null, 'A3');
        $r = 4;
        foreach (Catalogue::SECTIONS as $sec => $label) {
            $sheet->setCellValue("A{$r}", "SECTION {$sec}: ".strtoupper($label));
            $sheet->mergeCells("A{$r}:J{$r}");
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;
            foreach ($d['eval']->filter(fn ($e) => $e['parameter']->section === $sec) as $code => $e) {
                $s = $e['score'];
                $sheet->fromArray([
                    $e['definition']['letter'], $e['parameter']->title, $e['definition']['metric']['symbol'],
                    $e['value'], $e['parameter']->max_marks,
                    $audit->isEditable() ? $e['claimed'] : ($s?->claimed_marks ?? $e['claimed']),
                    $s?->auditor1_marks, $s?->auditor2_marks, $s?->awarded_marks,
                    trim(($s?->auditor1_remark ?? '').' '.($s?->auditor2_remark ?? '')),
                ], null, "A{$r}", true);
                $r++;
            }
            $sum = $d['summary'][$sec];
            $sheet->fromArray(['', "Sub-Total Marks ({$sec})", '', '', $sum['max'], $sum['claimed'], '', '', $sum['awarded'], ''], null, "A{$r}", true);
            $sheet->getStyle("A{$r}:J{$r}")->getFont()->setBold(true);
            $r++;
        }
        $t = $d['summary']['total'];
        $sheet->fromArray(['', 'Total Marks', '', '', $t['max'], $t['claimed'], '', '', $t['awarded'], ''], null, "A{$r}", true);
        $sheet->getStyle("A{$r}:J{$r}")->getFont()->setBold(true);
        $this->styleTable($sheet, 3, $r, 10, [6, 60, 14, 10, 10, 12, 10, 10, 12, 40]);

        foreach (Catalogue::all() as $code => $def) {
            if (! $def['columns']) {
                continue;
            }
            $ws = $book->createSheet()->setTitle(str_replace('.', '', $code));
            $ws->setCellValue('A1', "{$code}) {$def['title']} ({$d['eval'][$code]['parameter']->max_marks} Marks)");
            $ws->getStyle('A1')->getFont()->setBold(true);
            $ws->fromArray(array_merge(['SN'], array_column($def['columns'], 'label')), null, 'A3');
            $row = 4;
            foreach ($d['entries'][$code] ?? [] as $i => $entry) {
                $vals = [$i + 1];
                foreach ($def['columns'] as $c) {
                    $vals[] = ItemForm::display($c, $entry->data[$c['key']] ?? null);
                }
                $ws->fromArray($vals, null, "A{$row}", true);
                $row++;
            }
            $e = $d['eval'][$code];
            $ws->setCellValue('A'.($row + 1), $def['metric']['symbol'].' = '.Fmt::value($e['value'], 4).($e['formula'] ? "  ({$e['formula']})" : ''));
            $this->styleTable($ws, 3, max($row - 1, 3), count($def['columns']) + 1, array_merge([6], array_fill(0, count($def['columns']), 22)));
        }
        $book->setActiveSheetIndex(0);
        return $book;
    }

    public function universityExcel(Cycle $cycle): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('University Summary');
        $sheet->setCellValue('A1', "Internal Academic Audit {$cycle->academic_year} – Department-wise Summary");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $params = $cycle->parameters;
        $head = ['Department', 'Status'];
        foreach ($params as $p) {
            $head[] = $p->code;
        }
        foreach (Catalogue::SECTIONS as $sec => $l) {
            $head[] = "Section {$sec}";
        }
        $head = array_merge($head, ['Total Claimed', 'Total Awarded']);
        $sheet->fromArray($head, null, 'A3');
        $sheet->fromArray(array_merge(['Max marks', ''], $params->pluck('max_marks')->all()), null, 'A4');
        $r = 5;
        foreach ($cycle->audits()->with('department', 'cycle')->get()->sortBy('department.name') as $audit) {
            $eval = $this->scores->evaluate($audit);
            $sum = $this->scores->summary($audit, $eval);
            $useAwarded = $sum['total']['awarded'] !== null;
            $row = [$audit->department->name, $audit->statusLabel()];
            foreach ($params as $p) {
                $e = $eval[$p->code];
                $row[] = $useAwarded ? $e['score']?->awarded_marks : $e['claimed'];
            }
            foreach (Catalogue::SECTIONS as $sec => $l) {
                $row[] = $useAwarded ? $sum[$sec]['awarded'] : $sum[$sec]['claimed'];
            }
            $row[] = $sum['total']['claimed'];
            $row[] = $sum['total']['awarded'];
            $sheet->fromArray($row, null, "A{$r}", true);
            $r++;
        }
        $this->styleTable($sheet, 3, $r - 1, count($head), array_merge([38, 16], array_fill(0, count($head) - 2, 8)));
        $sheet->setCellValue('A'.($r + 1), 'Per-parameter columns show awarded marks where the audit is complete, otherwise claimed marks.');
        return $book;
    }

    private function styleTable(Worksheet $sheet, int $from, int $to, int $cols, array $widths): void
    {
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cols);
        $sheet->getStyle("A{$from}:{$last}{$from}")->getFont()->setBold(true);
        $sheet->getStyle("A{$from}:{$last}{$from}")->getFill()->setFillType('solid')->getStartColor()->setRGB('DDE5F0');
        $sheet->getStyle("A{$from}:{$last}{$to}")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $sheet->getStyle("A{$from}:{$last}{$to}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }
    }
}
