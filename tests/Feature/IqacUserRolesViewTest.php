<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Tests\TestCase;

class IqacUserRolesViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_lists_roles_with_department_short_names(): void
    {
        $this->seed(AcademicAuditSeeder::class);
        $cse = Department::where('short_name', 'CSE')->first();
        $u = User::factory()->create(['email' => 'Hod.CSE@mmmut.ac.in']);
        UserRole::create(['user_id' => $u->id, 'role' => 'hod', 'department_id' => $cse->id]);
        UserRole::create(['user_id' => $u->id, 'role' => 'auditor']);
        Faculty::create(['name' => 'HOD CSE', 'user_id' => $u->id, 'department_id' => $cse->id]);
        Faculty::create(['name' => 'No Login', 'email' => 'nologin@mmmut.ac.in', 'department_id' => $cse->id]);
        Faculty::create(['name' => 'Left', 'email' => 'left@mmmut.ac.in', 'department_id' => $cse->id, 'is_active' => false]);

        $rows = DB::table('iqac_user_roles')->orderBy('email')->orderBy('role')->get()
            ->map(fn ($r) => "{$r->email}|{$r->role}|{$r->department}")->all();

        $this->assertSame([
            'hod.cse@mmmut.ac.in|auditor|',
            'hod.cse@mmmut.ac.in|faculty|CSE',
            'hod.cse@mmmut.ac.in|hod|CSE',
            'nologin@mmmut.ac.in|faculty|CSE',
        ], $rows);
    }
}
