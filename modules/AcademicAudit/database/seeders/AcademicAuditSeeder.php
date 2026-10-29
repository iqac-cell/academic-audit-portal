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
        }

        if (! Cycle::exists()) {
            $cycles->create('2025-26', ['opens_on' => '2026-10-15', 'closes_on' => '2026-11-30']);
        }
    }
}
