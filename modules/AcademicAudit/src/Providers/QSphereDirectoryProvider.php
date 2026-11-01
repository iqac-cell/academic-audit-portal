<?php

namespace Mmmut\AcademicAudit\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\DepartmentAlias;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Support\DepartmentList;

/**
 * Departments and faculty mastered in the Q-Sphere IQAC portal, read from its views
 * sdg_departments(code, name, type, is_active) and sdg_users(email, name, employee_id, dept_code,
 * office_code, is_active [, designation]) over the read-only connection "qsphere".
 *
 * Q-Sphere department codes are matched to aa_departments.short_name, then to aa_department_aliases,
 * then by name. Local aa_faculty rows with the same email add what Q-Sphere does not hold
 * (qualification, joining date, regular/contract). When Q-Sphere cannot be read, the local
 * directory (ERP Excel import) is used instead.
 */
class QSphereDirectoryProvider implements DirectoryProvider
{
    private ?array $departmentCodes = null;
    private array $faculty = [];

    public function __construct(private LocalDirectoryProvider $local) {}

    public function departments(): Collection
    {
        $codes = $this->departmentCodes();
        if ($codes === null) {
            return $this->local->departments();
        }

        $ids = array_unique(array_values($codes));
        return Department::whereIn('id', $ids)->where('is_academic', true)->orderBy('name')->get();
    }

    public function faculty(Department $department): Collection
    {
        if (array_key_exists($department->id, $this->faculty)) {
            return $this->faculty[$department->id];
        }

        $codes = $this->departmentCodes();
        if ($codes === null) {
            return $this->faculty[$department->id] = $this->local->faculty($department);
        }
        $deptCodes = array_keys(array_filter($codes, fn ($id) => $id === $department->id));
        if ($deptCodes === []) {
            return $this->faculty[$department->id] = collect();
        }

        try {
            $rows = $this->db()->table('sdg_users')->whereIn('dept_code', $deptCodes)->where('is_active', 1)->get();
        } catch (\Throwable $e) {
            $this->unavailable($e);
            return $this->faculty[$department->id] = $this->local->faculty($department);
        }

        $pattern = config('academic-audit.qsphere.faculty_designations');
        $rows = $rows->filter(fn ($r) => ! $pattern || blank($r->designation ?? null) || preg_match($pattern, (string) $r->designation));

        $emails = $rows->pluck('email')->filter()->map(fn ($e) => mb_strtolower(trim($e)))->all();
        $localByEmail = Faculty::whereIn(DB::raw('LOWER(email)'), $emails ?: [''])->get()
            ->keyBy(fn ($f) => mb_strtolower(trim($f->email)));

        return $this->faculty[$department->id] = $rows
            ->map(function ($r) use ($department, $localByEmail) {
                $email = mb_strtolower(trim((string) $r->email));
                $faculty = $localByEmail->get($email) ?? new Faculty([
                    'department_id' => $department->id,
                    'employee_code' => $r->employee_id ?? null,
                    'name' => $r->name,
                    'designation' => $r->designation ?? null,
                    'email' => $email ?: null,
                    'is_regular' => true,
                    'is_active' => true,
                    'source' => 'qsphere',
                ]);
                $faculty->setRelation('department', $department);
                return $faculty;
            })
            ->sortBy(fn ($f) => mb_strtolower($f->name))
            ->values();
    }

    public function regularFacultyCount(Department $department, Cycle $cycle): int
    {
        $startYear = (int) substr($cycle->academic_year, 0, 4);
        $yearStart = "{$startYear}-07-01";
        $yearEnd = ($startYear + 1).'-06-30';

        return $this->faculty($department)
            ->filter(fn (Faculty $f) => $f->is_regular
                && (! $f->date_of_joining || $f->date_of_joining->format('Y-m-d') <= $yearEnd)
                && (! $f->date_of_retirement || $f->date_of_retirement->format('Y-m-d') >= $yearStart))
            ->count();
    }

    /**
     * Active Q-Sphere department code → aa_departments.id, or null when Q-Sphere cannot be read.
     *
     * @return array<string, int>|null
     */
    public function departmentCodes(): ?array
    {
        if ($this->departmentCodes !== null) {
            return $this->departmentCodes;
        }

        try {
            $rows = $this->db()->table('sdg_departments')->where('is_active', 1)->get(['code', 'name', 'type']);
        } catch (\Throwable $e) {
            $this->unavailable($e);
            return null;
        }

        [$byCode, $byName] = $this->localIndex();
        $map = [];
        foreach ($rows as $r) {
            $code = trim((string) $r->code);
            $id = $byCode[mb_strtoupper($code)] ?? $byName[DepartmentList::key((string) $r->name)] ?? null;
            if ($id) {
                $map[$code] = $id;
            }
        }

        return $this->departmentCodes = $map;
    }

    /** [upper-case short name / alias → id, loose name key → id] for local departments. */
    private function localIndex(): array
    {
        $byCode = [];
        $byName = [];
        foreach (Department::all() as $d) {
            if ($d->short_name) {
                $byCode[mb_strtoupper(trim($d->short_name))] = $d->id;
            }
            $byName[DepartmentList::key($d->name)] = $d->id;
        }
        foreach (DepartmentAlias::all() as $a) {
            $byCode[mb_strtoupper(trim($a->alias))] ??= $a->department_id;
            $byName[DepartmentList::key($a->alias)] ??= $a->department_id;
        }

        return [$byCode, $byName];
    }

    private function db()
    {
        return DB::connection(config('academic-audit.qsphere.connection'));
    }

    private function unavailable(\Throwable $e): void
    {
        Log::warning('Q-Sphere directory unavailable, using the local faculty directory: '.$e->getMessage());
    }
}
