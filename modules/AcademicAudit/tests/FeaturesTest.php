<?php

namespace Mmmut\AcademicAudit\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Notifications\AuditMail;
use Mmmut\AcademicAudit\Services\Ai\DataQualityChecker;
use Mmmut\AcademicAudit\Services\Ai\DocumentExtractor;
use Mmmut\AcademicAudit\Services\Ai\ExtractionException;
use Mmmut\AcademicAudit\Services\Ai\ScoreAdvisor;
use Mmmut\AcademicAudit\Services\CycleService;
use Mmmut\AcademicAudit\Services\SettingsService;
use Mmmut\AcademicAudit\Support\Access;
use Tests\TestCase;

class FeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Department $cse;
    private Department $ece;
    private Audit $cseAudit;
    private Audit $eceAudit;
    private User $multi;   // HOD of CSE + auditor of ECE + faculty of CSE
    private User $iqac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
        Storage::fake('local');
        $this->cse = Department::where('short_name', 'CSE')->first();
        $this->ece = Department::where('short_name', 'ECE')->first();
        $this->cseAudit = Audit::where('department_id', $this->cse->id)->first();
        $this->eceAudit = Audit::where('department_id', $this->ece->id)->first();

        $this->iqac = User::factory()->create();
        UserRole::create(['user_id' => $this->iqac->id, 'role' => 'iqac']);

        $this->multi = User::factory()->create(['email' => 'multi@mmmut.ac.in']);
        UserRole::create(['user_id' => $this->multi->id, 'role' => 'hod', 'department_id' => $this->cse->id]);
        UserRole::create(['user_id' => $this->multi->id, 'role' => 'auditor']);
        Faculty::create(['name' => 'Multi Role', 'department_id' => $this->cse->id, 'user_id' => $this->multi->id, 'is_regular' => true]);
        $this->eceAudit->update(['auditor1_user_id' => $this->multi->id, 'status' => Audit::VERIFIED]);
    }

    private function settings(array $values): void
    {
        foreach ($values as $k => $v) {
            app(SettingsService::class)->set($k, $v);
        }
    }

    // ---------------- role switching ----------------

    public function test_one_login_switches_between_roles(): void
    {
        $this->actingAs($this->multi);
        $roles = app(Access::class)->availableRoles()->pluck('key')->all();
        $this->assertSame(['hod:'.$this->cse->id, 'auditor:', 'faculty:'.$this->cse->id], $roles);

        // default = HOD: can edit CSE, cannot see ECE
        $this->get(route('audit.show', $this->cseAudit))->assertOk();
        $this->get(route('audit.show', $this->eceAudit))->assertForbidden();
        $this->post(route('audit.items.store', [$this->cseAudit, 'A.g']), ['data' => ['student' => 'X']])->assertRedirect();

        // as auditor: sees ECE, not CSE
        $this->post(route('audit.role.switch'), ['role' => 'auditor:'])->assertRedirect(route('audit.dashboard'));
        $this->get(route('audit.show', $this->eceAudit))->assertOk();
        $this->get(route('audit.show', $this->cseAudit))->assertForbidden();
        $this->get(route('audit.dashboard'))->assertSee($this->ece->name)->assertDontSee('Computer Science &amp; Engineering</a>', false);

        // as faculty: own rows go to HOD for approval
        $this->post(route('audit.role.switch'), ['role' => 'faculty:'.$this->cse->id]);
        $this->post(route('audit.items.store', [$this->cseAudit, 'C.h']), ['data' => ['title' => 'P1']])->assertRedirect();
        $this->assertSame(Entry::PENDING, Entry::where('parameter_code', 'C.h')->first()->status);
        $this->post(route('audit.items.store', [$this->cseAudit, 'A.g']), ['data' => ['student' => 'Y']])->assertForbidden();

        // cannot switch to a role the user does not hold
        $this->post(route('audit.role.switch'), ['role' => 'iqac:'])->assertForbidden();
        $this->get(route('audit.dashboard'))->assertSee('Faculty – CSE');
    }

    public function test_faculty_contributions_can_be_switched_off(): void
    {
        $this->settings(['workflow.faculty_contributions' => false]);
        $this->actingAs($this->multi)->post(route('audit.role.switch'), ['role' => 'faculty:'.$this->cse->id]);
        $this->post(route('audit.items.store', [$this->cseAudit, 'C.h']), ['data' => ['title' => 'P1']])->assertForbidden();
    }

    // ---------------- settings ----------------

    public function test_settings_save_and_secrets_are_encrypted(): void
    {
        $this->actingAs($this->iqac);
        foreach (array_keys(\Mmmut\AcademicAudit\Support\SettingsRegistry::GROUPS) as $tab) {
            $this->get(route('audit.admin.settings', ['tab' => $tab]))->assertOk();
        }
        $this->post(route('audit.admin.settings.update', 'mail'), [
            'mail__enabled' => '1', 'mail__host' => 'smtp.gmail.com', 'mail__port' => 587, 'mail__encryption' => 'tls',
            'mail__username' => 'iqac@mmmut.ac.in', 'mail__password' => 'app-pass-123', 'mail__from_address' => 'iqac@mmmut.ac.in', 'mail__from_name' => 'IQAC',
        ])->assertRedirect();

        $raw = DB::table('aa_settings')->where('key', 'mail.password')->first();
        $this->assertTrue((bool) $raw->is_encrypted);
        $this->assertNotSame('app-pass-123', $raw->value);
        $this->assertSame('app-pass-123', app(SettingsService::class)->get('mail.password'));

        // blank password keeps the saved one
        $this->post(route('audit.admin.settings.update', 'mail'), ['mail__enabled' => '1', 'mail__host' => 'smtp.gmail.com', 'mail__password' => ''])->assertRedirect();
        $this->assertSame('app-pass-123', app(SettingsService::class)->get('mail.password'));

        app(SettingsService::class)->applyToConfig();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));

        $this->actingAs($this->multi)->get(route('audit.admin.settings'))->assertForbidden();
    }

    public function test_new_features_are_off_by_default(): void
    {
        $s = app(SettingsService::class);
        foreach (['mail.enabled', 'security.otp_reset', 'notify.submitted', 'ai.quality_checks', 'ai.suggestions', 'ai.prev_year_prefill', 'ai.document_autofill'] as $k) {
            $this->assertFalse($s->enabled($k), $k);
        }
        $this->assertTrue($s->enabled('workflow.faculty_contributions'));
        $this->get(route('login'))->assertDontSee(route('password.request'));
    }

    // ---------------- OTP password reset ----------------

    public function test_password_reset_with_email_otp(): void
    {
        $this->get(route('password.otp.verify'))->assertNotFound();
        $this->settings(['mail.enabled' => true, 'security.otp_reset' => true, 'security.otp_attempts' => 2]);
        Notification::fake();

        $this->get(route('login'))->assertSee('Forgot password?');
        $this->post(route('password.email'), ['email' => 'nobody@mmmut.ac.in'])->assertRedirect(route('password.otp.verify'));
        Notification::assertNothingSent();

        $code = null;
        $this->post(route('password.email'), ['email' => 'multi@mmmut.ac.in'])->assertRedirect(route('password.otp.verify'));
        Notification::assertSentTo($this->multi, AuditMail::class, function (AuditMail $n) use (&$code) {
            preg_match('/(\d{6})/', implode(' ', $n->lines), $m);
            $code = $m[1] ?? null;
            return true;
        });
        $this->assertNotNull($code);

        $wrong = $code === '000000' ? '111111' : '000000';
        $form = ['email' => 'multi@mmmut.ac.in', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'];
        $this->post(route('password.otp.reset'), $form + ['code' => $wrong])->assertSessionHasErrors('code');
        $this->post(route('password.otp.reset'), $form + ['code' => $code])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('NewPass123', $this->multi->fresh()->password));
        // used code cannot be reused
        $this->post(route('password.otp.reset'), $form + ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_otp_locks_after_too_many_attempts_and_expires(): void
    {
        $this->settings(['mail.enabled' => true, 'security.otp_reset' => true, 'security.otp_attempts' => 1]);
        Notification::fake();
        $this->post(route('password.email'), ['email' => 'multi@mmmut.ac.in']);
        $form = ['email' => 'multi@mmmut.ac.in', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'];
        $this->post(route('password.otp.reset'), $form + ['code' => 'x'])->assertSessionHasErrors('code');
        $this->post(route('password.otp.reset'), $form + ['code' => 'x'])->assertSessionHasErrors(['code' => 'Too many wrong attempts. Request a new code.']);

        DB::table('aa_password_otps')->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $this->post(route('password.otp.reset'), $form + ['code' => 'x'])->assertSessionHasErrors(['code' => 'The code is invalid or has expired. Request a new code.']);
    }

    // ---------------- notifications ----------------

    public function test_workflow_notifications_follow_settings(): void
    {
        Notification::fake();
        $this->actingAs($this->multi)->post(route('audit.workflow', [$this->cseAudit, 'submit']))->assertSessionHasNoErrors();
        Notification::assertNothingSent();

        $this->settings(['mail.enabled' => true, 'notify.enabled' => true]);
        $this->actingAs($this->iqac)->post(route('audit.workflow', [$this->cseAudit, 'return']), ['remark' => 'Fix A.g'])->assertRedirect();
        Notification::assertSentTo($this->multi, AuditMail::class, fn ($n) => str_contains($n->subject, 'returned'));

        $this->settings(['notify.submitted' => false]);
        $this->actingAs($this->multi)->post(route('audit.workflow', [$this->cseAudit, 'submit']));
        Notification::assertNotSentTo($this->iqac, AuditMail::class);
    }

    // ---------------- AI: quality checks + suggestions ----------------

    public function test_data_quality_checks(): void
    {
        $add = fn ($code, $data) => Entry::create(['audit_id' => $this->cseAudit->id, 'parameter_code' => $code, 'data' => $data]);
        $add('A.c', ['program' => 'B.Tech', 'year' => 'First', 'students' => 50, 'passed' => 60, 'cgpa' => 11]);
        $add('A.a', ['program' => 'B.Tech', 'n' => 40, 'n1' => 45]);
        $add('C.h', ['title' => 'Same paper', 'year' => 2019, 'issn' => 'abc']);
        $add('C.h', ['title' => 'Same  Paper', 'year' => 2025]);
        $add('C.h', ['title' => 'same paper.', 'year' => 2025]);
        $add('C.p', ['faculty' => 'X', 'title' => 'Short FDP', 'from' => '2025-08-01', 'to' => '2025-08-02']);
        $add('D.a', ['faculty' => 'X', 'date' => now()->addYear()->toDateString()]);

        $issues = app(DataQualityChecker::class)->check($this->cseAudit);
        $msgs = $issues->pluck('message')->implode("\n");
        foreach (['CGPA must be between', 'passed is more than', 'N1 (completed) cannot be more', 'duplicate', 'outside academic year', 'ISSN', 'Shorter than one week', 'in the future'] as $needle) {
            $this->assertStringContainsString($needle, $msgs);
        }
        $this->assertSame('error', $issues->first()['severity']);
    }

    public function test_score_advisor_next_slab(): void
    {
        foreach (range(1, 3) as $i) {
            Faculty::create(['name' => "F{$i}", 'department_id' => $this->cse->id, 'is_regular' => true]);
        }
        // 4 regular faculty; 1 paper = 25 % → 10 marks; next slab Paper > 40 % → 20 marks: need 2 papers (50 %)
        Entry::create(['audit_id' => $this->cseAudit->id, 'parameter_code' => 'C.h', 'data' => ['title' => 'P']]);
        // 3 new courses → 15 marks; NC > 3 → 20 marks needs 1 more
        foreach (range(1, 3) as $i) {
            Entry::create(['audit_id' => $this->cseAudit->id, 'parameter_code' => 'B.a', 'data' => ['code' => "C{$i}"]]);
        }
        $s = app(ScoreAdvisor::class)->suggestions($this->cseAudit);
        $this->assertSame(1, $s['C.h']['need']);
        $this->assertSame(10.0, (float) $s['C.h']['gain']);
        $this->assertSame(1, $s['B.a']['need']);
        $this->assertStringContainsString('+50', $s['B.g']['message']);
        $this->assertTrue($s['A.e']['missing']); // graduates denominator not entered
    }

    public function test_pages_render_with_all_features_on(): void
    {
        $this->settings(['ai.enabled' => true, 'ai.quality_checks' => true, 'ai.suggestions' => true, 'ai.prev_year_prefill' => true, 'ai.document_autofill' => true, 'workflow.proof_mode' => 'warn']);
        Entry::create(['audit_id' => $this->cseAudit->id, 'parameter_code' => 'C.h', 'data' => ['title' => 'P', 'year' => 2019]]);
        $this->actingAs($this->multi);
        $this->get(route('audit.show', $this->cseAudit))->assertOk()->assertSee('Top actions to gain marks')->assertSee('Data quality');
        $this->get(route('audit.items', [$this->cseAudit, 'C.h']))->assertOk()->assertSee('Fill from document')->assertSee('Data checks');
        $this->get(route('audit.evaluation', $this->cseAudit))->assertOk();
        $this->actingAs($this->iqac)->get(route('audit.admin.users'))->assertOk()->assertSee('multi@mmmut.ac.in');
    }

    // ---------------- AI: previous-year prefill ----------------

    public function test_previous_year_prefill(): void
    {
        $this->cseAudit->update(['basic_info' => ['vision' => 'Old vision', 'programs' => [['name' => 'B.Tech CSE', 'intake' => 120]]], 'scalars' => ['phd_seats' => 24]]);
        Entry::create(['audit_id' => $this->cseAudit->id, 'parameter_code' => 'C.n', 'data' => ['faculty' => 'F1', 'body' => 'IEEE', 'nature' => 'Life']]);
        Entry::create(['audit_id' => $this->cseAudit->id, 'parameter_code' => 'C.d', 'data' => ['scheme' => 'SERB', 'faculty' => 'F1', 'funds' => 30]]);
        $next = app(CycleService::class)->create('2026-27');
        $newAudit = Audit::where('cycle_id', $next->id)->where('department_id', $this->cse->id)->first();

        $this->actingAs($this->multi);
        $this->get(route('audit.items', [$newAudit, 'C.n']))->assertDontSee('Prefill from');
        $this->settings(['ai.enabled' => true, 'ai.prev_year_prefill' => true]);
        $this->get(route('audit.items', [$newAudit, 'C.n']))->assertSee('Prefill from previous year');

        $this->post(route('audit.items.prefill', [$newAudit, 'C.n']))->assertRedirect();
        $this->post(route('audit.items.prefill', [$newAudit, 'C.n'])); // no duplicates
        $this->post(route('audit.items.prefill', [$newAudit, 'C.d']));
        $this->assertSame(1, Entry::where('audit_id', $newAudit->id)->where('parameter_code', 'C.n')->count());
        $project = Entry::where('audit_id', $newAudit->id)->where('parameter_code', 'C.d')->first();
        $this->assertSame('previous', $project->source);
        $this->assertNull($project->data['funds']); // amounts are not carried over
        $this->assertSame('SERB', $project->data['scheme']);

        $this->post(route('audit.basic.prefill', $newAudit));
        $this->assertSame('Old vision', $newAudit->fresh()->basic_info['vision']);
    }

    // ---------------- AI: fill from document ----------------

    public function test_fill_from_document_with_fake_extractor(): void
    {
        $this->app->bind(DocumentExtractor::class, fn () => new class implements DocumentExtractor {
            public function extract(array $definition, string $path, string $mimeType): array
            {
                return ['data' => ['title' => 'Deep learning for crops', 'journal' => 'IEEE Access', 'year' => 2025, 'issn' => '2169-3536', 'link' => 'not a url but ok', 'bogus' => 'x'],
                    'model' => 'fake', 'input_tokens' => 100, 'output_tokens' => 20];
            }

            public function ping(): string
            {
                return 'ok';
            }
        });
        $pdf = fn () => UploadedFile::fake()->create('paper.pdf', 50, 'application/pdf');
        $this->actingAs($this->multi);
        $url = route('audit.items.extract', [$this->cseAudit, 'C.h']);

        $this->postJson($url, ['document' => $pdf()])->assertStatus(422); // off by default
        $this->settings(['ai.enabled' => true, 'ai.document_autofill' => true, 'ai.daily_limit' => 1]);

        $res = $this->postJson($url, ['document' => $pdf()])->assertOk()->json();
        $this->assertSame('Deep learning for crops', $res['data']['title']);
        $this->assertArrayNotHasKey('bogus', $res['data']);
        $this->assertNotEmpty($res['proof_token']);
        $this->postJson($url, ['document' => $pdf()])->assertStatus(429); // daily limit

        // saving the row attaches the document as proof
        $this->post(route('audit.items.store', [$this->cseAudit, 'C.h']), ['data' => $res['data'], 'ai_proof_token' => $res['proof_token']])->assertRedirect();
        $entry = Entry::where('parameter_code', 'C.h')->first();
        $this->assertNotNull($entry->proof_path);
        Storage::disk('local')->assertExists($entry->proof_path);
        $this->assertSame(1, DB::table('aa_ai_logs')->where('status', 'ok')->count());
    }

    public function test_fill_from_document_reports_errors(): void
    {
        $this->settings(['ai.enabled' => true, 'ai.document_autofill' => true]);
        $this->app->bind(DocumentExtractor::class, fn () => new class implements DocumentExtractor {
            public function extract(array $d, string $p, string $m): array
            {
                throw new ExtractionException('No Claude API key saved.');
            }

            public function ping(): string
            {
                throw new ExtractionException('x');
            }
        });
        $this->actingAs($this->multi)
            ->postJson(route('audit.items.extract', [$this->cseAudit, 'C.h']), ['document' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJson(['message' => 'No Claude API key saved.']);
    }

    public function test_claude_schema_is_built_from_columns(): void
    {
        $schema = app(\Mmmut\AcademicAudit\Services\Ai\ClaudeDocumentExtractor::class)->schema(\Mmmut\AcademicAudit\Support\Catalogue::get('C.d'));
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['scheme', 'faculty', 'agency', 'type', 'year', 'funds', 'duration'], $schema['required']);
        $this->assertSame(['Government', 'Non-Government'], $schema['properties']['type']['anyOf'][0]['enum']);
        $this->assertSame('null', $schema['properties']['funds']['anyOf'][1]['type']);
    }
}
