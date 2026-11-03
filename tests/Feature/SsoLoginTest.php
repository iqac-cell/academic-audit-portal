<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sso\Jwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\Access;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SsoLoginTest extends TestCase
{
    use FakesQSphere, RefreshDatabase;

    private User $hod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        $this->enableSso();
        $this->hod = User::factory()->create(['email' => 'hod.cse@mmmut.ac.in', 'name' => 'Old Name']);
        UserRole::create(['user_id' => $this->hod->id, 'role' => 'hod', 'department_id' => Department::where('short_name', 'CSE')->value('id')]);
    }

    public function test_login_redirects_to_qsphere_with_pkce(): void
    {
        $this->get('/login')->assertRedirect(route('sso.redirect'));

        $res = $this->get('/sso/login');
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith($this->issuer.'/oauth/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);

        $this->assertSame('code', $q['response_type']);
        $this->assertSame($this->clientId, $q['client_id']);
        $this->assertSame('https://sdg.mmmut.ac.in/academic-audit/sso/callback', $q['redirect_uri']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertStringContainsString('openid', $q['scope']);
        $pending = session('sso.pending');
        $this->assertSame($pending['state'], $q['state']);
        $this->assertSame($pending['nonce'], $q['nonce']);
        $this->assertSame(Jwt::b64encode(hash('sha256', $pending['verifier'], true)), $q['code_challenge']);
    }

    public function test_callback_logs_in_existing_user_by_email(): void
    {
        $res = $this->withSession([Access::SESSION_KEY => 'iqac:'])->loginWith(['email' => 'HOD.CSE@MMMUT.AC.IN']);

        $res->assertRedirect(route('audit.dashboard'));
        $this->assertAuthenticatedAs($this->hod);
        $this->assertNull(session(Access::SESSION_KEY));
        $this->assertSame('sso', session('auth.via'));
        $this->assertSame('qs-42', $this->hod->fresh()->sso_sub);
        $this->assertSame('Prof. HOD CSE', $this->hod->fresh()->name);
        $this->assertSame(1, User::count());

        $sent = $this->tokenRequests[0];
        $this->assertSame('authorization_code', $sent['grant_type']);
        $this->assertSame('abc123', $sent['code']);
        $this->assertSame('https://sdg.mmmut.ac.in/academic-audit/sso/callback', $sent['redirect_uri']);
        $this->assertSame(64, strlen($sent['code_verifier']));
        $this->assertDatabaseHas('sso_sessions', ['sid' => 'qs-session-1', 'user_id' => $this->hod->id]);
    }

    public function test_role_switcher_works_after_sso_login(): void
    {
        UserRole::create(['user_id' => $this->hod->id, 'role' => 'auditor']);
        $this->loginWith();

        $this->get(route('audit.dashboard'))->assertOk();
        $this->post(route('audit.role.switch'), ['role' => 'auditor:'])->assertRedirect();
        $this->assertSame('auditor:', session(Access::SESSION_KEY));
    }

    public function test_portal_switcher_is_in_the_header(): void
    {
        $this->loginWith();
        $this->get(route('audit.dashboard'))->assertOk()
            ->assertSee('<div data-portal-switcher data-current="academic-audit"', false)
            ->assertSee('<script src="/iqac/assets/js/portal-switcher.js" defer></script>', false);

        config(['sso.enabled' => false]);
        $this->get(route('audit.dashboard'))->assertOk()->assertDontSee('data-portal-switcher', false);
    }

    public function test_sso_user_is_not_forced_to_change_local_password(): void
    {
        $this->hod->forceFill(['must_change_password' => true])->save();
        $this->loginWith();
        $this->get(route('audit.dashboard'))->assertOk();
    }

    public function test_unknown_user_without_hod_or_faculty_role_is_told_to_ask_iqac(): void
    {
        $res = $this->loginWith(['email' => 'New.Person@mmmut.ac.in', 'name' => 'New Person', 'sub' => 'qs-77', 'qs_roles' => ['student']]);

        $res->assertStatus(403)->assertSee('ask the IQAC', false)->assertSee('new.person@mmmut.ac.in');
        $this->assertGuest();
        $user = User::where('email', 'new.person@mmmut.ac.in')->first();
        $this->assertNotNull($user);
        $this->assertSame('qs-77', $user->sso_sub);
        $this->assertFalse(Hash::check('Mmmut@123', $user->password));
        $this->assertSame(0, UserRole::where('user_id', $user->id)->count());
        $this->assertSame(0, Faculty::count());
    }

    public function test_unknown_faculty_gets_faculty_role_for_their_department(): void
    {
        $res = $this->loginWith(['email' => 'asha@mmmut.ac.in', 'name' => 'Asha Verma', 'sub' => 'qs-5', 'qs_roles' => ['faculty'], 'dept_code' => 'CSED', 'employee_id' => 'E5']);

        $res->assertRedirect(route('audit.dashboard'));
        $user = User::where('email', 'asha@mmmut.ac.in')->first();
        $this->assertAuthenticatedAs($user);
        $cse = Department::where('short_name', 'CSE')->value('id');
        $this->assertSame([['faculty', $cse]], UserRole::where('user_id', $user->id)->get()->map(fn ($r) => [$r->role, $r->department_id])->all());
        $this->assertDatabaseHas('aa_faculty', ['user_id' => $user->id, 'department_id' => $cse, 'email' => 'asha@mmmut.ac.in', 'employee_code' => 'E5', 'is_active' => 1]);
        $this->assertSame('faculty:'.$cse, app(Access::class)->active()['key']);
        $this->get(route('audit.dashboard'))->assertOk();
    }

    public function test_unknown_hod_gets_hod_role_but_never_iqac_or_auditor(): void
    {
        $ece = Department::where('short_name', 'ECE')->value('id');
        $this->loginWith(['email' => 'hod.ece@mmmut.ac.in', 'sub' => 'qs-8', 'qs_roles' => ['hod', 'faculty', 'iqac', 'auditor'], 'dept_code' => 'eced']);

        $user = User::where('email', 'hod.ece@mmmut.ac.in')->first();
        $this->assertAuthenticatedAs($user);
        $this->assertEqualsCanonicalizing(['hod', 'faculty'], UserRole::where('user_id', $user->id)->pluck('role')->all());
        $this->assertSame(0, UserRole::whereIn('role', ['iqac', 'auditor'])->where('user_id', $user->id)->count());
        $this->assertSame('hod:'.$ece, app(Access::class)->active()['key']);
        $this->assertSame($ece, Faculty::where('user_id', $user->id)->value('department_id'));
    }

    public function test_unknown_department_shows_hindi_message(): void
    {
        $this->loginWith(['email' => 'pharma@mmmut.ac.in', 'sub' => 'qs-9', 'qs_roles' => ['faculty'], 'dept_code' => 'PHSTD'])
            ->assertStatus(403)->assertSee('Q-Sphere में आपका विभाग दर्ज नहीं — IQAC से संपर्क करें');
        $this->assertGuest();
        $this->assertSame(1, UserRole::count());
    }

    public function test_existing_roles_are_kept_and_nothing_is_added(): void
    {
        $this->loginWith(['qs_roles' => ['faculty'], 'dept_code' => 'MED']);
        $this->assertAuthenticatedAs($this->hod);
        $this->assertSame(['hod'], UserRole::where('user_id', $this->hod->id)->pluck('role')->all());
    }

    public function test_inactive_faculty_record_gives_no_access(): void
    {
        Faculty::create(['name' => 'Left', 'email' => 'left@mmmut.ac.in', 'department_id' => Department::where('short_name', 'CSE')->value('id'), 'is_active' => false]);
        $this->loginWith(['email' => 'left@mmmut.ac.in', 'sub' => 'qs-10', 'qs_roles' => ['faculty'], 'dept_code' => 'CSED'])
            ->assertStatus(403)->assertSee('no longer active');
        $this->assertGuest();
    }

    public function test_login_with_a_different_sub_for_a_linked_email_is_refused(): void
    {
        $this->hod->forceFill(['sso_sub' => 'qs-42'])->save();
        $this->loginWith(['sub' => 'qs-999'])->assertStatus(400)->assertSee('different central');
        $this->assertGuest();
        $this->assertSame('qs-42', $this->hod->fresh()->sso_sub);
    }

    public function test_faculty_record_counts_as_access(): void
    {
        Faculty::create(['name' => 'Asha Verma', 'email' => 'asha@mmmut.ac.in', 'department_id' => Department::where('short_name', 'CSE')->value('id')]);
        $this->loginWith(['email' => 'asha@mmmut.ac.in', 'sub' => 'qs-9'])->assertRedirect(route('audit.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_state_mismatch_is_rejected(): void
    {
        $this->startLogin();
        $this->nextIdToken = 'unused';
        $this->get('/sso/callback?code=abc&state=forged')->assertStatus(400)->assertSee('Sign-in failed');
        $this->assertGuest();
        $this->assertSame([], $this->tokenRequests);
    }

    public function test_callback_without_pending_login_is_rejected(): void
    {
        $this->get('/sso/callback?code=abc&state=x')->assertStatus(400);
        $this->assertGuest();
    }

    public function test_provider_error_is_shown(): void
    {
        $this->startLogin();
        $this->get('/sso/callback?error=access_denied&error_description=User+cancelled')->assertStatus(400)->assertSee('User cancelled');
    }

    public static function badTokens(): array
    {
        return [
            'wrong signing key' => [[], 'other-key'],
            'wrong issuer' => [['iss' => 'https://evil.example/iqac'], null],
            'wrong audience' => [['aud' => 'sdg-portal'], null],
            'expired' => [['exp' => time() - 3600], null],
            'wrong nonce' => [['nonce' => 'replayed'], null],
            'no subject' => [['sub' => ''], null],
            'alg none' => [[], 'none'],
        ];
    }

    #[DataProvider('badTokens')]
    public function test_invalid_id_token_is_rejected(array $overrides, ?string $mode): void
    {
        $res = $this->loginWith($overrides, match ($mode) {
            'other-key' => fn ($c) => $this->signToken($c, 'sso-other-key', 'k1'),
            'none' => fn ($c) => Jwt::b64encode(json_encode(['alg' => 'none', 'kid' => 'k1'])).'.'.Jwt::b64encode(json_encode($c)).'.',
            default => null,
        });

        $res->assertStatus(400);
        $this->assertGuest();
        $this->assertNull($this->hod->fresh()->sso_sub);
    }

    public function test_logout_signs_out_at_qsphere(): void
    {
        $this->loginWith();
        $res = $this->post(route('logout'));

        $this->assertGuest();
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith($this->issuer.'/oauth/logout?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('https://sdg.mmmut.ac.in/academic-audit/', $q['post_logout_redirect_uri']);
        $this->assertSame(0, DB::table('sso_sessions')->count());
    }
}
