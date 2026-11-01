<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\DepartmentAlias;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Providers\LocalDirectoryProvider;
use Mmmut\AcademicAudit\Providers\QSphereDirectoryProvider;
use Tests\TestCase;

class QSphereDirectoryProviderTest extends TestCase
{
    use RefreshDatabase;

    private Department $cse;
    private Department $ce;
    private Department $me;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        $this->cse = Department::where('short_name', 'CSE')->first();
        $this->ce = Department::where('short_name', 'CE')->first();
        $this->me = Department::where('short_name', 'ME')->first();
        DepartmentAlias::create(['department_id' => $this->ce->id, 'alias' => 'CIVIL']);

        config(['database.connections.qsphere' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('qsphere');
        Schema::connection('qsphere')->create('sdg_departments', function (Blueprint $t) {
            $t->string('code');
            $t->string('name');
            $t->string('type')->nullable();
            $t->boolean('is_active');
        });
        Schema::connection('qsphere')->create('sdg_users', function (Blueprint $t) {
            $t->string('email');
            $t->string('name');
            $t->string('employee_id')->nullable();
            $t->string('dept_code')->nullable();
            $t->string('office_code')->nullable();
            $t->boolean('is_active');
            $t->string('designation')->nullable();
        });

        DB::connection('qsphere')->table('sdg_departments')->insert([
            ['code' => 'cse', 'name' => 'Department of Computer Science', 'type' => 'academic', 'is_active' => 1],
            ['code' => 'CIVIL', 'name' => 'Civil', 'type' => 'academic', 'is_active' => 1],
            ['code' => 'MED', 'name' => 'Mechanical Engg. Deptt.', 'type' => 'academic', 'is_active' => 1],
            ['code' => 'EE', 'name' => 'Electrical Engineering', 'type' => 'academic', 'is_active' => 0],
            ['code' => 'REG', 'name' => 'Registrar Office', 'type' => 'office', 'is_active' => 1],
        ]);
        DB::connection('qsphere')->table('sdg_users')->insert([
            ['email' => 'Asha@mmmut.ac.in', 'name' => 'Asha Verma', 'employee_id' => 'E1', 'dept_code' => 'cse', 'office_code' => null, 'is_active' => 1, 'designation' => 'Professor'],
            ['email' => 'ravi@mmmut.ac.in', 'name' => 'Ravi Kumar', 'employee_id' => 'E2', 'dept_code' => 'cse', 'office_code' => null, 'is_active' => 1, 'designation' => 'Assistant Professor'],
            ['email' => 'guest@mmmut.ac.in', 'name' => 'Guest Teacher', 'employee_id' => 'E3', 'dept_code' => 'cse', 'office_code' => null, 'is_active' => 1, 'designation' => 'Assistant Professor (Contract)'],
            ['email' => 'clerk@mmmut.ac.in', 'name' => 'Office Clerk', 'employee_id' => 'E4', 'dept_code' => 'cse', 'office_code' => null, 'is_active' => 1, 'designation' => 'Clerk'],
            ['email' => 'old@mmmut.ac.in', 'name' => 'Retired Person', 'employee_id' => 'E5', 'dept_code' => 'cse', 'office_code' => null, 'is_active' => 0, 'designation' => 'Professor'],
            ['email' => 'civil@mmmut.ac.in', 'name' => 'Civil Teacher', 'employee_id' => 'E6', 'dept_code' => 'CIVIL', 'office_code' => null, 'is_active' => 1, 'designation' => 'Associate Professor'],
        ]);

        Faculty::create(['name' => 'Asha Verma', 'salutation' => 'Dr.', 'email' => 'asha@mmmut.ac.in', 'qualification' => 'PhD',
            'department_id' => $this->cse->id, 'is_regular' => true, 'date_of_joining' => '2010-07-01']);
        Faculty::create(['name' => 'Guest Teacher', 'email' => 'guest@mmmut.ac.in', 'department_id' => $this->cse->id, 'is_regular' => false]);
        Faculty::create(['name' => 'Local Only', 'email' => 'local@mmmut.ac.in', 'department_id' => $this->cse->id, 'is_regular' => true]);
    }

    private function provider(): QSphereDirectoryProvider
    {
        return app(QSphereDirectoryProvider::class);
    }

    public function test_codes_map_to_local_departments_by_short_name_alias_and_name(): void
    {
        $this->assertSame(['cse' => $this->cse->id, 'CIVIL' => $this->ce->id, 'MED' => $this->me->id], $this->provider()->departmentCodes());
        $this->assertEqualsCanonicalizing(['CSE', 'CE', 'ME'], $this->provider()->departments()->pluck('short_name')->all());
    }

    public function test_faculty_are_active_teaching_staff_merged_with_local_records(): void
    {
        $faculty = $this->provider()->faculty($this->cse);

        $this->assertSame(['Asha Verma', 'Guest Teacher', 'Ravi Kumar'], $faculty->pluck('name')->all());
        $asha = $faculty->firstWhere('name', 'Asha Verma');
        $this->assertTrue($asha->exists);
        $this->assertSame('Dr. Asha Verma', $asha->display_name);
        $this->assertSame('PhD', $asha->qualification);
        $ravi = $faculty->firstWhere('name', 'Ravi Kumar');
        $this->assertFalse($ravi->exists);
        $this->assertSame('E2', $ravi->employee_code);
        $this->assertSame('Assistant Professor', $ravi->designation);
        $this->assertSame(['Civil Teacher'], $this->provider()->faculty($this->ce)->pluck('name')->all());
    }

    public function test_regular_faculty_count_uses_local_regular_flag(): void
    {
        $cycle = Cycle::firstOrFail();
        $this->assertSame(2, $this->provider()->regularFacultyCount($this->cse, $cycle));
        $this->assertSame(0, $this->provider()->regularFacultyCount(Department::where('short_name', 'EE')->first(), $cycle));
    }

    public function test_falls_back_to_local_directory_when_qsphere_is_unreachable(): void
    {
        config(['database.connections.qsphere' => ['driver' => 'sqlite', 'database' => base_path('does/not/exist.sqlite'), 'prefix' => '']]);
        DB::purge('qsphere');
        $local = app(LocalDirectoryProvider::class);

        $this->assertNull($this->provider()->departmentCodes());
        $this->assertEquals($local->departments()->pluck('id'), $this->provider()->departments()->pluck('id'));
        $this->assertEquals($local->faculty($this->cse)->pluck('name'), $this->provider()->faculty($this->cse)->pluck('name'));
    }

    public function test_live_qsphere_codes_map_and_check_command_lists_unmapped(): void
    {
        $live = ['CED' => 'Civil', 'CESD' => 'Chemistry & Env', 'CHED' => 'Chemical', 'CSED' => 'CSE', 'ECED' => 'ECE',
            'EED' => 'Electrical', 'HSSD' => 'Humanities', 'IKSD' => 'Indian Knowledge Systems', 'ITCAD' => 'IT & Computer Application',
            'MED' => 'Mechanical', 'MSCD' => 'Mathematics & Sci Computing', 'MSD' => 'Management Studies',
            'PHSTD' => 'Pharmaceutical Science & Technology', 'PMSD' => 'Physics & Material Science'];
        DB::connection('qsphere')->table('sdg_departments')->delete();
        DB::connection('qsphere')->table('sdg_departments')->insert(collect($live)
            ->map(fn ($name, $code) => ['code' => $code, 'name' => $name, 'type' => 'department', 'is_active' => 1])->values()->all());
        DB::connection('qsphere')->table('sdg_users')->update(['dept_code' => 'CSED']);

        $codes = $this->provider()->departmentCodes();
        $short = collect($codes)->map(fn ($id) => Department::find($id)->short_name)->all();
        $this->assertEquals(\Mmmut\AcademicAudit\Support\DepartmentList::QSPHERE_CODES, array_intersect_key($short, \Mmmut\AcademicAudit\Support\DepartmentList::QSPHERE_CODES));
        $this->assertCount(12, $codes);
        $this->assertSame(['IKSD', 'PHSTD'], array_keys($this->provider()->unmappedDepartments()));
        $this->assertSame(12, $this->provider()->departments()->count());

        $aliases = DepartmentAlias::count();
        $this->artisan('audit:directory-check')
            ->expectsOutputToContain('Unmapped Q-Sphere departments')
            ->expectsOutputToContain('PHSTD')
            ->expectsOutputToContain('IKSD')
            ->expectsOutputToContain('Nothing was changed')
            ->assertSuccessful();
        $this->assertSame($aliases, DepartmentAlias::count());
        $this->assertSame(12, Department::count());
    }

    public function test_check_command_fails_when_qsphere_is_unreachable(): void
    {
        config(['database.connections.qsphere' => ['driver' => 'sqlite', 'database' => base_path('does/not/exist.sqlite'), 'prefix' => '']]);
        DB::purge('qsphere');
        $this->artisan('audit:directory-check')->expectsOutputToContain('cannot be read')->assertFailed();
    }

    public function test_provider_is_selected_from_config(): void
    {
        $this->assertInstanceOf(LocalDirectoryProvider::class, app(DirectoryProvider::class));
        $this->app->forgetInstance(DirectoryProvider::class);
        config(['academic-audit.directory_provider' => QSphereDirectoryProvider::class]);
        $this->assertInstanceOf(QSphereDirectoryProvider::class, app(DirectoryProvider::class));
    }
}
