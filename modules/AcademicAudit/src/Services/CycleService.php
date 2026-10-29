<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Facades\DB;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Parameter;
use Mmmut\AcademicAudit\Support\Catalogue;

class CycleService
{
    public function __construct(private DirectoryProvider $directory) {}

    /**
     * Create a cycle. Parameters (max marks + slabs) are copied from the previous
     * cycle when one exists, so IQAC corrections carry forward; otherwise from the catalogue.
     */
    public function create(string $academicYear, array $attrs = []): Cycle
    {
        return DB::transaction(function () use ($academicYear, $attrs) {
            $previous = Cycle::orderByDesc('academic_year')->first();
            $cycle = Cycle::create(['academic_year' => $academicYear] + $attrs);
            $previous && $previous->parameters()->exists()
                ? $this->copyParameters($previous, $cycle)
                : $this->seedParameters($cycle);
            $this->ensureAudits($cycle);
            return $cycle;
        });
    }

    public function seedParameters(Cycle $cycle, bool $reset = false): void
    {
        if ($reset) {
            $cycle->parameters()->each(fn ($p) => $p->delete());
        }
        foreach (Catalogue::all() as $code => $def) {
            if ($cycle->parameters()->where('code', $code)->exists()) {
                continue;
            }
            $param = $cycle->parameters()->create([
                'code' => $code,
                'section' => $def['section'],
                'title' => $def['title'],
                'max_marks' => $def['max'],
                'scoring' => $def['scoring'],
                'notes' => $def['notes'],
                'sort_order' => $def['order'],
            ]);
            foreach ($def['slabs'] as $i => [$op, $threshold, $marks]) {
                $param->slabs()->create(['op' => $op, 'threshold' => $threshold, 'marks' => $marks, 'sort_order' => $i]);
            }
        }
    }

    private function copyParameters(Cycle $from, Cycle $to): void
    {
        foreach ($from->parameters()->with('slabs')->get() as $p) {
            $new = $to->parameters()->create($p->only(['code', 'section', 'title', 'max_marks', 'scoring', 'notes', 'sort_order']));
            foreach ($p->slabs as $s) {
                $new->slabs()->create($s->only(['op', 'threshold', 'marks', 'sort_order']));
            }
        }
        $this->seedParameters($to); // any parameters added to the catalogue since
    }

    /** One audit per academic department. */
    public function ensureAudits(Cycle $cycle): int
    {
        $created = 0;
        foreach ($this->directory->departments() as $dept) {
            $audit = Audit::firstOrCreate(['cycle_id' => $cycle->id, 'department_id' => $dept->id]);
            $created += $audit->wasRecentlyCreated ? 1 : 0;
        }
        return $created;
    }

    public function updateParameter(Parameter $param, array $data): void
    {
        DB::transaction(function () use ($param, $data) {
            $param->update(['max_marks' => $data['max_marks'], 'title' => $data['title'], 'notes' => $data['notes'] ?? null]);
            $param->slabs()->delete();
            $rows = collect($data['slabs'] ?? [])
                ->filter(fn ($s) => isset($s['threshold'], $s['marks']) && $s['threshold'] !== '' && $s['marks'] !== '')
                ->values();
            foreach ($rows as $i => $s) {
                $param->slabs()->create([
                    'op' => in_array($s['op'] ?? 'gt', ['gt', 'gte']) ? $s['op'] : 'gt',
                    'threshold' => (float) $s['threshold'],
                    'marks' => (float) $s['marks'],
                    'sort_order' => $i,
                ]);
            }
        });
    }
}
