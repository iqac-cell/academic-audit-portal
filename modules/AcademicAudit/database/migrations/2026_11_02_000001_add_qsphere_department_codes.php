<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Mmmut\AcademicAudit\Support\DepartmentList;

/**
 * Q-Sphere department codes (view sdg_departments) as aliases of the audit departments, so that
 * QSphereDirectoryProvider maps them. Codes without an audit department (PHSTD, IKSD) are left out
 * on purpose; audit:directory-check lists them as unmapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DepartmentList::QSPHERE_CODES as $code => $short) {
            $deptId = DB::table('aa_departments')->where('short_name', $short)->value('id');
            if ($deptId && ! DB::table('aa_department_aliases')->where('alias', $code)->exists()) {
                DB::table('aa_department_aliases')->insert(['department_id' => $deptId, 'alias' => $code]);
            }
        }
    }

    public function down(): void
    {
        DB::table('aa_department_aliases')->whereIn('alias', array_keys(DepartmentList::QSPHERE_CODES))->delete();
    }
};
