<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\Access;
use Tests\TestCase;

class LocalLoginTest extends TestCase
{
    use FakesQSphere, RefreshDatabase;

    private User $iqac;
    private User $hod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        $this->iqac = User::factory()->create(['email' => 'iqac@mmmut.ac.in']);
        UserRole::create(['user_id' => $this->iqac->id, 'role' => 'iqac']);
        $this->hod = User::factory()->create(['email' => 'hod@mmmut.ac.in']);
        UserRole::create(['user_id' => $this->hod->id, 'role' => 'hod', 'department_id' => Department::where('short_name', 'CSE')->value('id')]);
    }

    public function test_emergency_form_is_shown_when_sso_is_on(): void
    {
        $this->enableSso();
        $this->get('/login/local')->assertOk()->assertSee('Emergency login')->assertSee('central login');
    }

    public function test_iqac_can_use_local_login_when_sso_is_on(): void
    {
        $this->enableSso();
        $this->withSession([Access::SESSION_KEY => 'hod:1'])
            ->post('/login/local', ['email' => 'IQAC@mmmut.ac.in', 'password' => 'password'])
            ->assertRedirect(route('audit.dashboard'));

        $this->assertAuthenticatedAs($this->iqac);
        $this->assertNull(session(Access::SESSION_KEY));
        $this->assertSame('local', session('auth.via'));
    }

    public function test_non_iqac_cannot_use_local_login_when_sso_is_on(): void
    {
        $this->enableSso();
        $this->from('/login/local')->post('/login/local', ['email' => 'hod@mmmut.ac.in', 'password' => 'password'])
            ->assertRedirect('/login/local')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->enableSso();
        $this->post('/login/local', ['email' => 'iqac@mmmut.ac.in', 'password' => 'nope'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_local_logout_returns_to_local_login(): void
    {
        $this->enableSso();
        $this->post('/login/local', ['email' => 'iqac@mmmut.ac.in', 'password' => 'password']);
        $this->post(route('logout'))->assertRedirect(route('login.local'));
        $this->assertGuest();
    }

    public function test_without_sso_everyone_logs_in_locally_as_before(): void
    {
        config(['sso.enabled' => false]);
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/sso/login')->assertNotFound();
        $this->post('/login/local', ['email' => 'hod@mmmut.ac.in', 'password' => 'password'])->assertRedirect(route('audit.dashboard'));
        $this->assertAuthenticatedAs($this->hod);
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_otp_reset_page_links_back_to_local_login(): void
    {
        $this->enableSso();
        $this->get('/forgot-password')->assertOk()->assertSee(route('login.local'), false);
    }
}
