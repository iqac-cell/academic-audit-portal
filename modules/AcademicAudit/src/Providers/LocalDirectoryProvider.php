<?php

namespace Mmmut\AcademicAudit\Providers;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;

class LocalDirectoryProvider implements DirectoryProvider
{
    public function departments(): Collection
    {
        return Department::where('is_academic', true)->orderBy('name')->get();
    }

    public function faculty(Department $department): Collection
    {
        return Faculty::where('department_id', $department->id)->where('is_active', true)->orderBy('name')->get();
    }

    public function regularFacultyCount(Department $department, Cycle $cycle): int
    {
        // Academic year "2025-26" ends on 30 June 2026; count faculty in position during the year.
        $startYear = (int) substr($cycle->academic_year, 0, 4);
        $yearStart = "{$startYear}-07-01";
        $yearEnd = ($startYear + 1).'-06-30';

        return Faculty::where('department_id', $department->id)
            ->where('is_regular', true)
            ->where(fn ($q) => $q->whereNull('date_of_joining')->orWhere('date_of_joining', '<=', $yearEnd))
            ->where(fn ($q) => $q->whereNull('date_of_retirement')->orWhere('date_of_retirement', '>=', $yearStart))
            ->count();
    }
}
