<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * View read by the Q-Sphere Portal Home ("Academic Audit: HOD (CSE)").
 * One row per (email, role, department); faculty records count as the Faculty role, as in Support\Access.
 * Grant SELECT on it to iqac_reader with deploy/server/grant-iqac-reader.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS iqac_user_roles');
        DB::statement(<<<'SQL'
            CREATE VIEW iqac_user_roles AS
            SELECT LOWER(u.email) AS email, r.role AS role, d.short_name AS department
            FROM aa_user_roles r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN aa_departments d ON d.id = r.department_id
            UNION
            SELECT LOWER(COALESCE(u.email, f.email)) AS email, 'faculty' AS role, d.short_name AS department
            FROM aa_faculty f
            LEFT JOIN users u ON u.id = f.user_id
            LEFT JOIN aa_departments d ON d.id = f.department_id
            WHERE f.is_active = 1 AND COALESCE(u.email, f.email) IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS iqac_user_roles');
    }
};
