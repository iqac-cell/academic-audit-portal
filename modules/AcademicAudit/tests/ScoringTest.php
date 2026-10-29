<?php

namespace Mmmut\AcademicAudit\Tests;

use Mmmut\AcademicAudit\Services\MetricCalculator;
use Mmmut\AcademicAudit\Services\SlabScorer;
use Mmmut\AcademicAudit\Support\Catalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Evaluation scheme (pp. 16–19 of the format) boundary checks. */
class ScoringTest extends TestCase
{
    private function marks(string $code, ?float $value): ?float
    {
        $def = Catalogue::get($code);
        $slabs = array_map(fn ($s) => ['op' => $s[0], 'threshold' => $s[1], 'marks' => $s[2]], $def['slabs']);
        return (new SlabScorer)->score($def['scoring'], $value, $def['max'], $slabs);
    }

    public static function slabCases(): array
    {
        return [
            // code, indicator value, expected marks
            ['A.a', 0.95, 10], ['A.a', 0.90, 8], ['A.a', 0.85, 8], ['A.a', 0.75, 6], ['A.a', 0.70, 0],
            ['A.c', 9.1, 40], ['A.c', 8.5, 35], ['A.c', 7.2, 30], ['A.c', 6.5, 25], ['A.c', 6.0, 0],
            ['A.e', 12, 10], ['A.e', 9, 8], ['A.e', 7, 6], ['A.e', 3, 5], ['A.e', 2, 0],
            ['A.g', 85, 10], ['A.g', 70, 8], ['A.g', 55, 6], ['A.g', 30, 5], ['A.g', 20, 0],
            ['A.i', 6, 15], ['A.i', 4.5, 12], ['A.i', 3.5, 10], ['A.i', 2, 8], ['A.i', 1, 0],
            ['B.a', 6, 30], ['B.a', 5, 25], ['B.a', 4, 20], ['B.a', 3, 15], ['B.a', 2, 10], ['B.a', 1, 10], ['B.a', 0, 0],
            ['B.b', 81, 10], ['B.b', 61, 8], ['B.b', 51, 6], ['B.b', 50, 0],
            ['B.d', 6, 20], ['B.d', 5, 15], ['B.d', 4, 10], ['B.d', 3, 5], ['B.d', 1, 2], ['B.d', 0, 0],
            ['B.e', 11, 10], ['B.e', 9, 8], ['B.e', 7, 6], ['B.e', 5, 4], ['B.e', 3, 2], ['B.e', 2, 0],
            ['C.a', 90, 10], ['C.a', 60, 5], ['C.a', 30, 3], ['C.a', 10, 2], ['C.a', 0, 0],
            ['C.b', 11, 30], ['C.b', 9, 25], ['C.b', 7, 20], ['C.b', 5, 15], ['C.b', 3, 10], ['C.b', 1, 5],
            ['C.c', 81, 30], ['C.c', 70, 25], ['C.c', 50, 20], ['C.c', 30, 15], ['C.c', 15, 10], ['C.c', 5, 5],
            ['C.d', 120, 40], ['C.d', 60, 30], ['C.d', 40, 20], ['C.d', 20, 10], ['C.d', 5, 5], ['C.d', 0, 0],
            ['C.h', 90, 40], ['C.h', 70, 30], ['C.h', 50, 20], ['C.h', 10, 10],
            ['C.k', 1200, 20], ['C.k', 500, 15], ['C.k', 200, 10], ['C.k', 50, 5],
            ['C.l', 25, 20], ['C.l', 10, 15], ['C.l', 3, 10], ['C.l', 0.5, 5],
            ['C.n', 6, 10], ['C.n', 4.5, 8], ['C.n', 3.5, 6], ['C.n', 2.5, 4], ['C.n', 1.5, 2], ['C.n', 0.5, 1],
            ['C.o', 3, 10], ['C.o', 1.5, 8], ['C.o', 0.5, 6],
            ['C.p', 3, 20], ['C.p', 1.5, 15], ['C.p', 0.5, 10],
            ['C.q', 2, 20], ['C.q', 0.5, 15], ['C.r', 1.2, 20], ['C.r', 1, 15],
            ['D.a', 3, 20], ['D.a', 2, 10], ['D.a', 1, 10], ['D.a', 0, 0],
            ['D.b', 21, 50], ['D.b', 16, 40], ['D.b', 11, 30], ['D.b', 6, 20], ['D.b', 3, 10], ['D.b', 0, 0],
            ['B.c', 1, 20], ['B.c', 0, 0], ['B.g', 1, 50], ['D.c', 1, 30], ['D.c', 0, 0],
        ];
    }

    #[DataProvider('slabCases')]
    public function test_slab(string $code, float $value, float $expected): void
    {
        $this->assertSame((float) $expected, $this->marks($code, $value), "{$code} at {$value}");
    }

    public function test_manual_parameter_has_no_auto_marks(): void
    {
        $this->assertNull($this->marks('A.d', 5));
    }

    public function test_section_totals_follow_evaluation_scheme(): void
    {
        $totals = [];
        foreach (Catalogue::all() as $p) {
            $totals[$p['section']] = ($totals[$p['section']] ?? 0) + $p['max'];
        }
        $this->assertSame(['A' => 150, 'B' => 200, 'C' => 350, 'D' => 100], $totals);
        $this->assertCount(38, Catalogue::all());
    }

    public function test_no_slab_exceeds_max(): void
    {
        foreach (Catalogue::all() as $code => $p) {
            foreach ($p['slabs'] as [, , $marks]) {
                $this->assertLessThanOrEqual($p['max'], $marks, $code);
            }
        }
    }

    public function test_metrics(): void
    {
        $calc = new MetricCalculator;
        $m = fn ($code, $rows, $scalars = []) => $calc->compute(Catalogue::get($code)['metric'], $rows, $scalars)['value'];

        $this->assertEqualsWithDelta(0.875, $m('A.a', [['n' => 60, 'n1' => 50], ['n' => 20, 'n1' => 20]]), 1e-9);
        $this->assertEqualsWithDelta(7.75, $m('A.c', [['students' => 30, 'cgpa' => 8], ['students' => 10, 'cgpa' => 7]]), 1e-9);
        $this->assertEqualsWithDelta(10.0, $m('A.e', array_fill(0, 6, []), ['graduates' => 60]), 1e-9);
        $this->assertNull($m('A.e', [[]], []));                                  // denominator missing
        $this->assertEqualsWithDelta(75.0, $m('B.b', [], ['int_completed' => 45, 'int_eligible' => 60]), 1e-9);
        $this->assertEqualsWithDelta(50.0, $m('C.a', [['faculty' => 'A'], ['faculty' => 'a '], ['faculty' => 'B']], ['seed_eligible' => 4]), 1e-9);
        $this->assertEqualsWithDelta(150.0, $m('C.f', [['published_on' => '2025-01-01', 'granted_on' => '2025-06-01'], ['published_on' => '2025-02-01']], ['faculty' => 2]), 1e-9);
        $this->assertEqualsWithDelta(55.5, $m('C.d', [['funds' => '50.5'], ['funds' => 5]]), 1e-9);
        $this->assertEqualsWithDelta(3.0, $m('C.m', [['revenue' => 300000]]), 1e-9);  // rupees → lakhs
        $this->assertEqualsWithDelta(150.0, $m('C.k', [['citations' => 200], ['citations' => 100]], ['faculty' => 2]), 1e-9);
        // FDP/STTP: only programmes of at least one week count
        $this->assertEqualsWithDelta(0.5, $m('C.p', [['days' => 7], ['from' => '2025-07-01', 'to' => '2025-07-02']], ['faculty' => 2]), 1e-9);
        $this->assertEqualsWithDelta(1.0, $m('B.c', [], ['feedback_hosted' => 'Y']), 1e-9);
        $this->assertEqualsWithDelta(1.0, $m('D.c', [['published' => 'N'], ['published' => 'Y']]), 1e-9);
    }
}
