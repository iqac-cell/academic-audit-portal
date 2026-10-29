<?php

namespace Mmmut\AcademicAudit\Services;

/**
 * Computes the evaluation indicator of a parameter (SE, HS, P, NFD, FSM, Paper, ...).
 * Pure function of the catalogue metric definition, the rows and the scalar inputs,
 * so it is unit-testable without a database.
 */
class MetricCalculator
{
    /**
     * @param  array  $metric  Catalogue metric definition
     * @param  array<int, array>  $rows  data arrays of approved entries
     * @param  array  $scalars  Catalogue::SCALARS values incl. resolved 'faculty'
     * @return array{value: ?float, formula: string, missing: ?string}
     */
    public function compute(array $metric, array $rows, array $scalars): array
    {
        $n = count($rows);
        $den = fn (string $key) => $this->number($scalars[$key] ?? null);

        switch ($metric['type']) {
            case 'manual':
                return $this->result(null, 'Awarded by auditor on verification of records');

            case 'count':
                return $this->result($n, "{$n} entries");

            case 'count_pct':
                return $this->pct($n, $den($metric['den']), $metric['den']);

            case 'count_per':
                if (isset($metric['min_days'])) {
                    $rows = array_values(array_filter($rows, fn ($r) => $this->days($r) >= $metric['min_days']));
                    $n = count($rows);
                }
                return $this->per($n, $den($metric['den']), $metric['den']);

            case 'distinct_pct':
                $distinct = count(array_unique(array_filter(array_map(
                    fn ($r) => mb_strtolower(trim((string) ($r[$metric['col']] ?? ''))), $rows))));
                return $this->pct($distinct, $den($metric['den']), $metric['den']);

            case 'nonempty_pct':
                $c = 0;
                foreach ($rows as $r) {
                    foreach ($metric['cols'] as $col) {
                        $c += filled($r[$col] ?? null) ? 1 : 0;
                    }
                }
                return $this->pct($c, $den($metric['den']), $metric['den']);

            case 'sum':
                $sum = array_sum(array_map(fn ($r) => $this->number($r[$metric['col']] ?? 0) ?? 0, $rows)) * ($metric['scale'] ?? 1);
                return $this->result(round($sum, 4), 'Total = '.$this->fmt($sum));

            case 'sum_per':
                $sum = array_sum(array_map(fn ($r) => $this->number($r[$metric['col']] ?? 0) ?? 0, $rows));
                return $this->per($sum, $den($metric['den']), $metric['den']);

            case 'ratio_sum':
                $num = array_sum(array_map(fn ($r) => $this->number($r[$metric['num']] ?? 0) ?? 0, $rows));
                $d = array_sum(array_map(fn ($r) => $this->number($r[$metric['den']] ?? 0) ?? 0, $rows));
                if ($d <= 0) {
                    return $this->result(null, '', 'Enter N for at least one program');
                }
                return $this->result(round($num / $d, 4), $this->fmt($num).' / '.$this->fmt($d));

            case 'weighted_avg':
                $w = 0; $s = 0;
                foreach ($rows as $r) {
                    $v = $this->number($r[$metric['value']] ?? null);
                    $wt = $this->number($r[$metric['weight']] ?? null) ?? 0;
                    if ($v !== null && $wt > 0) {
                        $s += $v * $wt;
                        $w += $wt;
                    }
                }
                if ($w <= 0) {
                    return $this->result(null, '', 'Enter number of students and average CGPA');
                }
                return $this->result(round($s / $w, 4), 'Weighted by '.$this->fmt($w).' students');

            case 'scalar_pct':
                return $this->pct($den($metric['num']) ?? 0, $den($metric['den']), $metric['den']);

            case 'yes_no':
                $v = $scalars[$metric['key']] ?? null;
                return $v === null || $v === ''
                    ? $this->result(null, '', 'Select Yes / No')
                    : $this->result($v === 'Y' ? 1 : 0, $v === 'Y' ? 'Yes' : 'No');

            case 'any_yes':
                $yes = collect($rows)->contains(fn ($r) => ($r[$metric['col']] ?? null) === 'Y');
                return $this->result($yes ? 1 : 0, $yes ? 'Yes' : 'No');
        }

        throw new \InvalidArgumentException("Unknown metric type {$metric['type']}");
    }

    private function pct(float $num, ?float $den, string $denKey): array
    {
        if (! $den) {
            return $this->result(null, '', "Enter denominator: {$this->denLabel($denKey)}");
        }
        return $this->result(round($num / $den * 100, 4), $this->fmt($num).' / '.$this->fmt($den).' × 100');
    }

    private function per(float $num, ?float $den, string $denKey): array
    {
        if (! $den) {
            return $this->result(null, '', "Enter denominator: {$this->denLabel($denKey)}");
        }
        return $this->result(round($num / $den, 4), $this->fmt($num).' / '.$this->fmt($den));
    }

    private function result(?float $value, string $formula, ?string $missing = null): array
    {
        return ['value' => $value, 'formula' => $formula, 'missing' => $missing];
    }

    private function denLabel(string $key): string
    {
        return $key === 'faculty' ? 'Number of regular faculty' : (\Mmmut\AcademicAudit\Support\Catalogue::SCALARS[$key]['label'] ?? $key);
    }

    private function days(array $r): float
    {
        if ($d = $this->number($r['days'] ?? null)) {
            return $d;
        }
        if (! empty($r['from']) && ! empty($r['to'])) {
            try {
                return (new \DateTime($r['from']))->diff(new \DateTime($r['to']))->days + 1;
            } catch (\Exception) {
            }
        }
        return 0;
    }

    private function number(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace([',', ' '], '', (string) $v);
        return is_numeric($v) ? (float) $v : null;
    }

    private function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
