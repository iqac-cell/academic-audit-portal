<?php

namespace Mmmut\AcademicAudit\Database\Seeders;

use Illuminate\Database\Seeder;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Services\CycleService;
use Mmmut\AcademicAudit\Support\DepartmentList;

/** Departments + aliases, and the first audit cycle with parameters from the format. */
class AcademicAuditSeeder extends Seeder
{
    public function run(CycleService $cycles): void
    {
        foreach (DepartmentList::DEPARTMENTS as $name => [$short, $aliases]) {
            $dept = Department::firstOrCreate(['name' => $name], ['short_name' => $short, 'is_academic' => true]);
            foreach ($aliases as $alias) {
                $dept->aliases()->firstOrCreate(['alias' => $alias]);
            }
            foreach (array_keys(DepartmentList::QSPHERE_CODES, $short, true) as $code) {
                \Mmmut\AcademicAudit\Models\DepartmentAlias::firstOrCreate(['alias' => $code], ['department_id' => $dept->id]);
            }
        }

        if (! Cycle::exists()) {
            $cycles->create('2025-26', ['opens_on' => '2026-10-15', 'closes_on' => '2026-11-30']);
        }
    }
}
