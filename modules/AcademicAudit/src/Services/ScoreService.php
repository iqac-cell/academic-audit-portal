<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\Score;
use Mmmut\AcademicAudit\Support\Catalogue;

class ScoreService
{
    public function __construct(
        private MetricCalculator $calculator,
        private SlabScorer $scorer,
        private DirectoryProvider $directory,
    ) {}

    /** Scalars with the faculty denominator resolved. */
    public function scalars(Audit $audit): array
    {
        $s = $audit->scalars ?? [];
        $s['faculty'] = filled($s['regular_faculty'] ?? null)
            ? $s['regular_faculty']
            : $this->directory->regularFacultyCount($audit->department, $audit->cycle);
        return $s;
    }

    /**
     * Live evaluation of every parameter: indicator, formula and claimed marks.
     *
     * @return Collection<string, array>
     */
    public function evaluate(Audit $audit): Collection
    {
        $audit->loadMissing('cycle.parameters.slabs', 'department');
        $entries = Entry::where('audit_id', $audit->id)->approved()->get()->groupBy('parameter_code');
        $scalars = $this->scalars($audit);
        $stored = Score::where('audit_id', $audit->id)->get()->keyBy('parameter_code');

        return $audit->cycle->parameters->mapWithKeys(function ($param) use ($entries, $scalars, $stored) {
            $def = Catalogue::get($param->code);
            $rows = ($entries[$param->code] ?? collect())->pluck('data')->all();
            $m = $this->calculator->compute($def['metric'], $rows, $scalars);
            $score = $stored[$param->code] ?? null;
            $claimed = $param->scoring === 'manual'
                ? $score?->claimed_marks
                : $this->scorer->score($param->scoring, $m['value'], $param->max_marks, $param->slabs->toArray());

            return [$param->code => [
                'parameter' => $param,
                'definition' => $def,
                'rows' => count($rows),
                'value' => $m['value'],
                'formula' => $m['formula'],
                'missing' => $m['missing'],
                'claimed' => $claimed,
                'score' => $score,
            ]];
        });
    }

    /** Persist indicator values and claimed marks (called on submit and on recalculation). */
    public function store(Audit $audit): Collection
    {
        $eval = $this->evaluate($audit);
        foreach ($eval as $code => $e) {
            $score = Score::firstOrNew(['audit_id' => $audit->id, 'parameter_code' => $code]);
            $score->metric_value = $e['value'];
            if ($e['parameter']->scoring !== 'manual') {
                $score->claimed_marks = $e['claimed'];
            }
            $score->save();
        }
        return $eval;
    }

    public function setManualClaim(Audit $audit, string $code, ?float $marks): void
    {
        $param = $audit->cycle->parameters->firstWhere('code', $code);
        abort_unless($param && $param->scoring === 'manual', 422);
        $marks = $marks === null ? null : max(0, min($marks, $param->max_marks));
        Score::updateOrCreate(['audit_id' => $audit->id, 'parameter_code' => $code], ['claimed_marks' => $marks]);
    }

    /** Final awarded marks from the two auditors' marks according to the cycle rule. */
    public function computeAwarded(Audit $audit): void
    {
        $rule = $audit->cycle->awarding_rule;
        foreach (Score::where('audit_id', $audit->id)->get() as $s) {
            $marks = array_values(array_filter([$s->auditor1_marks, $s->auditor2_marks], fn ($v) => $v !== null));
            $s->awarded_marks = $marks ? round(match ($rule) {
                'min' => min($marks),
                'max' => max($marks),
                default => array_sum($marks) / count($marks),
            }, 2) : null;
            $s->save();
        }
    }

    /** Section-wise totals: max, claimed, awarded. */
    public function summary(Audit $audit, ?Collection $eval = null): array
    {
        $eval ??= $this->evaluate($audit);
        $out = [];
        foreach (Catalogue::SECTIONS as $sec => $label) {
            $items = $eval->filter(fn ($e) => $e['parameter']->section === $sec);
            $out[$sec] = [
                'label' => $label,
                'max' => $items->sum(fn ($e) => $e['parameter']->max_marks),
                'claimed' => $items->sum(fn ($e) => (float) ($audit->status === Audit::DRAFT || $audit->status === Audit::RETURNED ? $e['claimed'] : ($e['score']?->claimed_marks ?? $e['claimed']))),
                'awarded' => $items->contains(fn ($e) => $e['score']?->awarded_marks !== null)
                    ? $items->sum(fn ($e) => (float) $e['score']?->awarded_marks) : null,
            ];
        }
        $out['total'] = [
            'label' => 'Total Marks',
            'max' => array_sum(array_column($out, 'max')),
            'claimed' => array_sum(array_column($out, 'claimed')),
            'awarded' => collect($out)->pluck('awarded')->filter(fn ($v) => $v !== null)->isEmpty() ? null : array_sum(array_column($out, 'awarded')),
        ];
        return $out;
    }
}
