<?php

namespace Mmmut\AcademicAudit\Providers;

use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Models\Audit;

/** Asks several prefill sources in order (configured source first, then previous year). */
class CompositePrefillProvider implements PrefillProvider
{
    /** @param PrefillProvider[] $providers */
    public function __construct(private array $providers) {}

    public function name(): string
    {
        return implode(' / ', array_map(fn ($p) => $p->name(), array_filter($this->providers, fn ($p) => $p->supports())))
            ?: $this->providers[0]->name();
    }

    public function nameFor(string $code): ?string
    {
        $names = array_map(fn ($p) => $p->name(), array_filter($this->providers, fn ($p) => in_array($code, $p->supports())));
        return $names ? implode(' / ', $names) : null;
    }

    public function supports(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn ($p) => $p->supports(), $this->providers))));
    }

    public function rows(string $parameterCode, Audit $audit): iterable
    {
        $seen = [];
        foreach ($this->providers as $p) {
            if (! in_array($parameterCode, $p->supports())) {
                continue;
            }
            foreach ($p->rows($parameterCode, $audit) as $row) {
                if (! isset($seen[$row['source_ref']])) {
                    $seen[$row['source_ref']] = true;
                    yield $row;
                }
            }
        }
    }

    public function scalars(Audit $audit): array
    {
        $out = [];
        foreach ($this->providers as $p) {
            $out += array_filter($p->scalars($audit), fn ($v) => $v !== null && $v !== '');
        }
        return $out;
    }

    public function basicInfo(Audit $audit): array
    {
        $out = [];
        foreach ($this->providers as $p) {
            foreach ($p->basicInfo($audit) as $k => $v) {
                if (blank($out[$k] ?? null) && filled($v)) {
                    $out[$k] = $v;
                }
            }
        }
        return $out;
    }
}
