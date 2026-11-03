<?php

namespace Mmmut\AcademicAudit\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $iqac;
    private User $fac;
    private Faculty $faculty;
    private Audit $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        $cse = Department::where('short_name', 'CSE')->first();
        $this->audit = Audit::where('department_id', $cse->id)->first();
        $this->iqac = User::factory()->create(['email' => 'iqac@mmmut.ac.in']);
        UserRole::create(['user_id' => $this->iqac->id, 'role' => 'iqac']);
        $this->fac = User::factory()->create(['email' => 'fac@mmmut.ac.in']);
        UserRole::create(['user_id' => $this->fac->id, 'role' => 'faculty', 'department_id' => $cse->id]);
        UserRole::create(['user_id' => $this->fac->id, 'role' => 'hod', 'department_id' => $cse->id]);
        $this->faculty = Faculty::create(['name' => 'Fac One', 'department_id' => $cse->id, 'user_id' => $this->fac->id, 'is_regular' => true]);
    }

    public function test_iqac_deletes_a_user_and_keeps_audit_data(): void
    {
        $entry = Entry::create(['audit_id' => $this->audit->id, 'parameter_code' => 'C.h', 'data' => ['title' => 'P'], 'created_by' => $this->fac->id, 'faculty_id' => $this->faculty->id]);
        $this->audit->log('test', 'by faculty');
        DB::table('aa_audit_logs')->update(['user_id' => $this->fac->id]);

        $this->actingAs($this->fac)->delete(route('audit.admin.users.destroy', $this->iqac->id))->assertForbidden();
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $this->fac->id))->assertSessionHasNoErrors();

        $this->assertNull(User::find($this->fac->id));
        $this->assertSame(0, UserRole::where('user_id', $this->fac->id)->count());
        $this->assertNull($this->faculty->fresh()->user_id);
        $this->assertNotNull($entry->fresh());
        $this->get(route('audit.show', $this->audit))->assertOk()->assertSee('Deleted user');
    }

    public function test_delete_guards(): void
    {
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $this->iqac->id))->assertSessionHasErrors('user');

        // last IQAC admin, deleted by another IQAC admin who then loses the role
        $other = User::factory()->create();
        UserRole::create(['user_id' => $other->id, 'role' => 'iqac']);
        UserRole::where('user_id', $this->iqac->id)->delete();
        UserRole::create(['user_id' => $this->iqac->id, 'role' => 'iqac']);
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $other->id))->assertSessionHasNoErrors();
        $this->assertSame(1, UserRole::where('role', 'iqac')->count());

        // auditor of an unlocked audit
        $aud = User::factory()->create();
        UserRole::create(['user_id' => $aud->id, 'role' => 'auditor']);
        $this->audit->update(['auditor1_user_id' => $aud->id, 'status' => Audit::VERIFIED]);
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $aud->id))->assertSessionHasErrors('user');
        $this->assertNotNull(User::find($aud->id));

        $this->audit->update(['status' => Audit::LOCKED]);
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $aud->id))->assertSessionHasNoErrors();
        $this->assertNull(User::find($aud->id));
    }

    public function test_admin_sets_typed_password(): void
    {
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $this->fac->id, 'payload' => '', 'last_activity' => time()]);
        $url = route('audit.admin.users.password', $this->fac->id);

        $this->actingAs($this->iqac)->post($url, ['mode' => 'type', 'password' => 'abc', 'password_confirmation' => 'abc'])->assertSessionHasErrors('password');
        $this->actingAs($this->iqac)->post($url, ['mode' => 'type', 'password' => 'Secret123', 'password_confirmation' => 'Other1234'])->assertSessionHasErrors('password');
        $this->actingAs($this->iqac)->post($url, ['mode' => 'type', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'must_change' => 1])
            ->assertSessionHasNoErrors()->assertSessionMissing('generated_password');

        $this->assertTrue(Hash::check('Secret123', $this->fac->fresh()->password));
        $this->assertTrue((bool) $this->fac->fresh()->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->fac->id)->count());
        $this->actingAs($this->fac)->post(route('audit.admin.users.password', $this->iqac->id), ['mode' => 'generate'])->assertForbidden();
    }

    public function test_admin_generates_password_shown_once(): void
    {
        $res = $this->actingAs($this->iqac)->post(route('audit.admin.users.password', $this->fac->id), ['mode' => 'generate']);
        $gen = $res->getSession()->get('generated_password');
        $this->assertSame('fac@mmmut.ac.in', $gen['email']);
        $this->assertSame(10, strlen($gen['password']));
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $gen['password']);
        $this->assertMatchesRegularExpression('/\d/', $gen['password']);
        $this->assertFalse((bool) $this->fac->fresh()->must_change_password);
        $this->get(route('audit.admin.users'))->assertSee($gen['password']);
        $this->get(route('audit.admin.users'))->assertDontSee($gen['password']); // flashed once

        $this->post(route('logout'));
        $this->post('/login', ['email' => 'fac@mmmut.ac.in', 'password' => $gen['password']])->assertRedirect();
        $this->assertAuthenticatedAs($this->fac);
    }

    public function test_actions_still_work_when_the_log_file_is_not_writable(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('info')->andThrow(new \UnexpectedValueException('The stream or file "laravel.log" could not be opened in append mode'));
        \Illuminate\Support\Facades\Log::shouldReceive('error', 'warning', 'channel', 'stack')->andReturnNull();

        $this->actingAs($this->iqac)->post(route('audit.admin.users.password', $this->fac->id), ['mode' => 'generate'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('generated_password');
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $this->fac->id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(User::find($this->fac->id));
    }

    public function test_database_error_is_shown_instead_of_500(): void
    {
        \Illuminate\Support\Facades\Schema::drop('aa_password_otps');
        $this->actingAs($this->iqac)->delete(route('audit.admin.users.destroy', $this->fac->id))
            ->assertRedirect()->assertSessionHasErrors('user');
        // the transaction rolled back: nothing half-deleted
        $this->assertNotNull(User::find($this->fac->id));
        $this->assertSame(2, UserRole::where('user_id', $this->fac->id)->count());
        $this->assertSame($this->fac->id, $this->faculty->fresh()->user_id);
        $this->assertStringContainsString('Could not delete the user', session('errors')->first('user'));
    }
}
