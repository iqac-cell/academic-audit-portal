<?php

namespace Mmmut\AcademicAudit\Services\Ai;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Services\ScoreService;
use Mmmut\AcademicAudit\Support\Catalogue;
use Mmmut\AcademicAudit\Support\Fmt;

/**
 * Explains, per parameter, what the department would need for the next slab of the
 * evaluation scheme. Purely arithmetic on the department's own data; it never suggests
 * entering anything that is not genuinely available with proof.
 */
class ScoreAdvisor
{
    private const NOUNS = [
        'A.e' => 'qualified students', 'A.f' => 'students in higher studies', 'A.g' => 'placed students',
        'A.h' => 'awards', 'A.i' => 'research/innovation awards', 'B.a' => 'new courses', 'B.d' => 'upgraded lab facilities',
        'B.e' => 'new experiments', 'B.f' => 'ICT materials', 'C.a' => 'faculty with seed money', 'C.b' => 'fellowships',
        'C.c' => 'JRF/SRF scholars', 'C.e' => 'awards', 'C.f' => 'patents published/granted', 'C.g' => 'PhDs awarded',
        'C.h' => 'papers', 'C.i' => 'books/chapters', 'C.j' => 'e-content modules', 'C.n' => 'memberships',
        'C.o' => 'workshops/seminars attended', 'C.p' => 'FDPs/STTPs (≥ 1 week)', 'C.q' => 'programmes organised',
        'C.r' => 'resource-person talks', 'D.a' => 'outreach activities/visits', 'D.b' => 'student chapter activities',
    ];

    private const YES_NO_ACTIONS = [
        'B.c' => 'Analyse feedback, take action, host it on the department home page and set "Feedback hosted" = Yes.',
        'B.g' => 'Prepare PO-CO attainment for all courses, upload Annexure IV and set "PO-CO submitted" = Yes.',
        'B.h' => 'Prepare PO/PSO attainment with improvement actions, upload Annexure V and set "submitted" = Yes.',
        'D.c' => 'Publish the department magazine/newsletter on the website and add it with Published = Yes.',
    ];

    public function __construct(private ScoreService $scores) {}

    /**
     * @return Collection<string, array{code: string, gain: float, message: string, missing: bool}>
     */
    public function suggestions(Audit $audit, ?Collection $eval = null): Collection
    {
        $eval ??= $this->scores->evaluate($audit);
        $scalars = $this->scores->scalars($audit);
        $out = collect();

        foreach ($eval as $code => $e) {
            $p = $e['parameter'];
            $def = $e['definition'];
            $m = $def['metric'];
            $claimed = (float) ($e['claimed'] ?? 0);
            $sym = $m['symbol'];

            if ($p->scoring === 'yes_no') {
                if ($claimed < $p->max_marks) {
                    $out[$code] = ['code' => $code, 'gain' => $p->max_marks, 'missing' => false,
                        'message' => (self::YES_NO_ACTIONS[$code] ?? 'Complete this item').' (+'.Fmt::marks($p->max_marks).' marks)'];
                }
                continue;
            }
            if ($p->scoring === 'manual') {
                if ($e['score']?->claimed_marks === null) {
                    $out[$code] = ['code' => $code, 'gain' => 0, 'missing' => true,
                        'message' => 'Maintain mentor-mentee records (Annexure II) and enter claimed marks on the Evaluation page.'];
                }
                continue;
            }
            if ($e['missing']) {
                $out[$code] = ['code' => $code, 'gain' => $p->max_marks - $claimed, 'missing' => true,
                    'message' => $e['missing'].' — this parameter cannot be scored until then.'];
                continue;
            }

            // next better slab = the lowest threshold that gives more marks than now
            $next = $p->slabs->filter(fn ($s) => $s->marks > $claimed)->sortBy('threshold')->first();
            if (! $next) {
                continue; // already at the top slab
            }
            $gain = $next->marks - $claimed;
            $cmp = $next->op === 'gte' ? '≥' : '>';
            $target = "{$sym} {$cmp} ".Fmt::value($next->threshold, 3);
            $value = $e['value'] ?? 0;
            $noun = self::NOUNS[$code] ?? 'entries';
            $den = isset($m['den']) && ! in_array($m['type'], ['ratio_sum', 'scalar_pct']) ? (float) ($scalars[$m['den']] ?? 0) : null;

            $need = match ($m['type']) {
                'count' => $this->countNeeded($next->threshold, $next->op) - $e['rows'],
                'count_pct', 'nonempty_pct', 'distinct_pct' => $den ? $this->countNeeded($next->threshold * $den / 100, $next->op) - $this->currentCount($m, $value, $den) : null,
                'count_per' => $den ? $this->countNeeded($next->threshold * $den, $next->op) - $this->currentCount($m, $value, $den) : null,
                default => null,
            };

            if ($need !== null && $need > 0) {
                $msg = "{$need} more {$noun} would reach {$target} → ".Fmt::marks($next->marks).' marks (+'.Fmt::marks($gain).').';
            } elseif (in_array($m['type'], ['sum', 'sum_per'])) {
                $goal = $m['type'] === 'sum_per' && $den ? $next->threshold * $den : $next->threshold;
                $have = $m['type'] === 'sum_per' && $den ? $value * $den : $value;
                $msg = "{$sym} is ".Fmt::value($value, 2).'; total of '.($next->op === 'gte' ? 'at least ' : 'more than ').Fmt::value($goal, 2)
                    .' (now '.Fmt::value($have, 2).') gives '.Fmt::marks($next->marks).' marks (+'.Fmt::marks($gain).').';
            } else {
                $msg = "Currently {$sym} = ".Fmt::value($value, 3)."; reaching {$target} gives ".Fmt::marks($next->marks).' marks (+'.Fmt::marks($gain).').';
            }
            $out[$code] = ['code' => $code, 'gain' => $gain, 'missing' => false, 'message' => $msg, 'need' => $need];
        }

        return $out->sortBy([['missing', 'desc'], ['gain', 'desc']]);
    }

    private function countNeeded(float $threshold, string $op): int
    {
        return $op === 'gte' ? (int) ceil($threshold - 1e-9) : (int) floor($threshold + 1e-9) + 1;
    }

    private function currentCount(array $m, float $value, float $den): int
    {
        return (int) round($m['type'] === 'count_per' ? $value * $den : $value * $den / 100);
    }
}
