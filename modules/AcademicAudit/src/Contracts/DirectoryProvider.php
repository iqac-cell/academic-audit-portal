<?php

namespace Mmmut\AcademicAudit\Contracts;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;

/**
 * Master data (departments, faculty) used by the audit.
 * Standalone: LocalDirectoryProvider (aa_ tables seeded from Samarth ERP).
 * After merge: an implementation reading the IQAC project's own tables.
 */
interface DirectoryProvider
{
    /** @return Collection<int, Department> academic departments that are audited */
    public function departments(): Collection;

    /** @return Collection<int, \Mmmut\AcademicAudit\Models\Faculty> active faculty of a department */
    public function faculty(Department $department): Collection;

    /** Number of regular faculty in the department during the cycle (denominator of Section C ratios). */
    public function regularFacultyCount(Department $department, Cycle $cycle): int;
}
