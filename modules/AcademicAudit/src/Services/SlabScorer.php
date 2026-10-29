<?php

namespace Mmmut\AcademicAudit\Services;

/**
 * Converts an indicator value into marks using the evaluation-scheme slabs.
 * Slabs are checked in order; the first one satisfied gives the marks, none => 0.
 */
class SlabScorer
{
    /**
     * @param  string  $scoring  slab | yes_no | manual
     * @param  iterable<array{op: string, threshold: float, marks: float}>  $slabs
     */
    public function score(string $scoring, ?float $value, float $max, iterable $slabs): ?float
    {
        if ($scoring === 'manual') {
            return null;
        }
        if ($value === null) {
            return 0.0;
        }
        if ($scoring === 'yes_no') {
            return $value >= 1 ? $max : 0.0;
        }
        foreach ($slabs as $s) {
            $ok = ($s['op'] ?? 'gt') === 'gte' ? $value >= (float) $s['threshold'] : $value > (float) $s['threshold'];
            if ($ok) {
                return min((float) $s['marks'], $max);
            }
        }
        return 0.0;
    }
}
