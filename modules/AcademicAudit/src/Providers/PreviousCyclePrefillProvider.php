<?php

namespace Mmmut\AcademicAudit\Providers;

use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Services\SettingsService;

/**
 * Carries forward data from the same department's audit of the previous academic year:
 * basic information, and items that usually continue (memberships, running projects,
 * mentor list, faculty list for citations). Amounts and counts are left blank so nothing
 * is claimed twice; every copied row is marked "previous year – verify".
 */
class PreviousCyclePrefillProvider implements PrefillProvider
{
    /** code => columns kept; null = all columns. */
    private const CARRY = [
        'C.n' => null,
        'C.d' => ['scheme', 'faculty', 'agency', 'type', 'year', 'duration'],
        'A.d' => ['faculty'],
        'C.k' => ['faculty'],
    ];

    public function __construct(private SettingsService $settings) {}

    public function name(): string
    {
        return 'previous year';
    }

    public function supports(): array
    {
        return $this->settings->enabled('ai.prev_year_prefill') ? array_keys(self::CARRY) : [];
    }

    public function previous(Audit $audit): ?Audit
    {
        return Audit::with('cycle')
            ->where('department_id', $audit->department_id)
            ->whereHas('cycle', fn ($q) => $q->where('academic_year', '<', $audit->cycle->academic_year))
            ->get()->sortByDesc('cycle.academic_year')->first();
    }

    public function rows(string $parameterCode, Audit $audit): iterable
    {
        if (! in_array($parameterCode, $this->supports()) || ! ($prev = $this->previous($audit))) {
            return [];
        }
        $keep = self::CARRY[$parameterCode];
        foreach (Entry::where('audit_id', $prev->id)->where('parameter_code', $parameterCode)->approved()->orderBy('id')->get() as $e) {
            $data = $keep === null ? $e->data : array_intersect_key($e->data, array_flip($keep));
            yield ['data' => $data, 'source_ref' => 'prev:'.$e->id, 'faculty_id' => $e->faculty_id, 'source' => 'previous'];
        }
    }

    public function scalars(Audit $audit): array
    {
        if (! $this->settings->enabled('ai.prev_year_prefill') || ! ($prev = $this->previous($audit))) {
            return [];
        }
        return array_intersect_key($prev->scalars ?? [], array_flip(['seed_eligible', 'phd_seats', 'recognised_guides', 'int_eligible']));
    }

    public function basicInfo(Audit $audit): array
    {
        if (! $this->settings->enabled('ai.prev_year_prefill') || ! ($prev = $this->previous($audit))) {
            return [];
        }
        return array_intersect_key($prev->basic_info ?? [], array_flip(['about', 'vision', 'mission', 'programs', 'sanctioned']));
    }
}
