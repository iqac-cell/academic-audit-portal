<?php

namespace Mmmut\AcademicAudit\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\Notice;
use Mmmut\AcademicAudit\Models\UserRole;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class NoticesAndImportTest extends TestCase
{
    use RefreshDatabase;

    private User $iqac;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        Storage::fake('local');
        $this->iqac = User::factory()->create(['email' => 'iqac@mmmut.ac.in']);
        UserRole::create(['user_id' => $this->iqac->id, 'role' => 'iqac']);
        $this->other = User::factory()->create();
        UserRole::create(['user_id' => $this->other->id, 'role' => 'auditor']);
    }

    // ---------------- session expiry (419) ----------------

    public function test_expired_session_redirects_to_login(): void
    {
        Route::middleware('web')->post('/_expired_test', fn () => throw new TokenMismatchException);

        $this->actingAs($this->iqac)->post('/_expired_test')
            ->assertRedirect(route('login'))
            ->assertSessionHas('expired', 'Your session expired. Please log in again.');
        $this->assertGuest();

        $this->postJson('/_expired_test')->assertStatus(419)->assertJson(['redirect' => route('login', ['expired' => 1])]);
        $this->get(route('login', ['expired' => 1]))->assertSee('Your session expired');
    }

    public function test_ping_and_session_setting(): void
    {
        $this->actingAs($this->iqac)->get(route('audit.ping'))->assertNoContent();
        app(\Mmmut\AcademicAudit\Services\SettingsService::class)->set('security.session_minutes', 30);
        app(\Mmmut\AcademicAudit\Services\SettingsService::class)->applyToConfig();
        $this->assertSame(30, config('session.lifetime'));
        $this->actingAs($this->iqac)->get(route('audit.dashboard'))->assertSee('const lifetime = 30 * 60 * 1000', false);
    }

    // ---------------- notices ----------------

    public function test_iqac_manages_notices_shown_on_login_and_dashboard(): void
    {
        $this->actingAs($this->other)->get(route('audit.admin.notices'))->assertForbidden();

        $this->actingAs($this->iqac)->post(route('audit.admin.notices.store'), [
            'title' => 'Last date for audit submission is 30 November', 'show_on' => 'both', 'is_active' => 1, 'is_new' => 1,
            'attachment' => UploadedFile::fake()->create('circular.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('audit.admin.notices'));
        $this->post(route('audit.admin.notices.store'), ['title' => 'Only on dashboard', 'show_on' => 'dashboard', 'is_active' => 1]);
        $this->post(route('audit.admin.notices.store'), ['title' => 'Expired notice', 'show_on' => 'both', 'is_active' => 1, 'ends_on' => now()->subDay()->toDateString()]);
        $this->post(route('audit.admin.notices.store'), ['title' => 'Hidden notice', 'show_on' => 'both']);
        $this->post(route('audit.admin.notices.store'), ['title' => 'Bad link', 'show_on' => 'both', 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');

        $first = Notice::where('title', 'like', 'Last date%')->first();
        $this->get(route('audit.dashboard'))->assertSee('Last date for audit submission')->assertSee('Only on dashboard')
            ->assertDontSee('Expired notice')->assertDontSee('Hidden notice');

        auth()->logout();
        $this->get(route('login'))->assertSee('Last date for audit submission')->assertSee('NEW')->assertDontSee('Only on dashboard');
        $this->get(route('audit.notices.file', $first))->assertOk();   // public download

        $this->actingAs($this->iqac)->post(route('audit.admin.notices.toggle', $first));
        auth()->logout();
        $this->get(route('audit.notices.file', $first))->assertNotFound();
        $this->get(route('login'))->assertDontSee('Last date for audit submission');
    }

    // ---------------- Excel user import ----------------

    private function sheet(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(array_merge([['Name', 'Email', 'Roles', 'Department', 'Designation', 'Mobile']], $rows));
        $file = tempnam(sys_get_temp_dir(), 'u').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($file);
        return new UploadedFile($file, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_import_users_from_excel(): void
    {
        $this->actingAs($this->other)->get(route('audit.admin.users.import'))->assertForbidden();
        $this->actingAs($this->iqac)->get(route('audit.admin.users.import.template'))->assertOk();

        $file = $this->sheet([
            ['Dr. Ravi Kumar', 'Ravi.CSE@mmmut.ac.in', 'Faculty, HOD', 'CSE', 'Professor', '9876543210'],
            ['Prof. Auditor One', 'aud1@mmmut.ac.in', 'Auditor', '', '', ''],
            ['IQAC Admin', 'iqac@mmmut.ac.in', 'IQAC, Auditor', '', '', ''],          // existing user
            ['No Dept', 'nodept@mmmut.ac.in', 'Faculty', '', '', ''],                  // error
            ['Bad Role', 'bad@mmmut.ac.in', 'Principal', 'CSE', '', ''],              // error
            ['Dup', 'ravi.cse@mmmut.ac.in', 'Faculty', 'Civil Engg. Deptt.', '', ''],  // duplicate email
            ['', '', '', '', '', ''],
        ]);
        $this->post(route('audit.admin.users.import.preview'), ['file' => $file])->assertRedirect(route('audit.admin.users.import'));
        $rows = collect(session('audit.user_import'));
        $this->assertSame(['new', 'new', 'existing', 'error', 'error', 'error'], $rows->pluck('status')->all());
        $this->assertStringContainsString('Department is required', implode(' ', $rows[3]['messages']));
        $this->assertStringContainsString("Unknown role 'Principal'", implode(' ', $rows[4]['messages']));
        $this->get(route('audit.admin.users.import'))->assertOk()->assertSee('Import 3 user(s)');

        $this->post(route('audit.admin.users.import.confirm'))->assertRedirect(route('audit.admin.users'));
        $ravi = User::where('email', 'ravi.cse@mmmut.ac.in')->first();
        $cse = Department::where('short_name', 'CSE')->first();
        $this->assertNotNull($ravi);
        $this->assertTrue((bool) $ravi->must_change_password);
        $this->assertEqualsCanonicalizing(['faculty', 'hod'], UserRole::where('user_id', $ravi->id)->pluck('role')->all());
        $fac = Faculty::where('user_id', $ravi->id)->first();
        $this->assertSame($cse->id, $fac->department_id);
        $this->assertSame('Ravi Kumar', $fac->name);
        $this->assertSame('Dr.', $fac->salutation);
        $this->assertTrue(UserRole::where('user_id', $this->iqac->id)->where('role', 'auditor')->exists());
        $this->assertNull(User::where('email', 'nodept@mmmut.ac.in')->first());

        // re-importing the same file adds nothing
        $count = [User::count(), UserRole::count(), Faculty::count()];
        $this->post(route('audit.admin.users.import.preview'), ['file' => $this->sheet([['Dr. Ravi Kumar', 'ravi.cse@mmmut.ac.in', 'Faculty, HOD', 'CSE', '', '']])]);
        $this->post(route('audit.admin.users.import.confirm'));
        $this->assertSame($count, [User::count(), UserRole::count(), Faculty::count()]);

        // the new user can log in and switch between HOD and Faculty
        $this->post(route('logout'));
        $this->post('/login', ['email' => 'ravi.cse@mmmut.ac.in', 'password' => config('academic-audit.default_password')])->assertRedirect();
        $this->assertAuthenticatedAs($ravi);
    }

    public function test_import_rejects_wrong_headings(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Foo', 'Bar'], ['x', 'y']]);
        $file = tempnam(sys_get_temp_dir(), 'u').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($file);
        $this->actingAs($this->iqac)->post(route('audit.admin.users.import.preview'), ['file' => new UploadedFile($file, 'x.xlsx', null, null, true)])
            ->assertSessionHasErrors('file');
    }
}
