<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Services\PasswordOtpService;
use Mmmut\AcademicAudit\Services\SettingsService;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_only_in_production_over_https(): void
    {
        $this->app['env'] = 'production';
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_storage_files_are_not_served_directly(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->get('/storage/academic-audit/x.pdf')->assertNotFound();
    }

    private function otpSetup(): void
    {
        Notification::fake();
        app(SettingsService::class)->set('mail.enabled', true);
        app(SettingsService::class)->set('security.otp_reset', true);
        app(SettingsService::class)->set('security.otp_attempts', 3);
        User::factory()->create(['email' => 'hod@mmmut.ac.in']);
    }

    public function test_new_otp_code_does_not_reset_wrong_attempts(): void
    {
        $this->otpSetup();
        $otp = app(PasswordOtpService::class);
        $otp->issue('hod@mmmut.ac.in');
        $otp->reset('hod@mmmut.ac.in', 'wrong1', 'Newpass123');
        $otp->reset('hod@mmmut.ac.in', 'wrong2', 'Newpass123');
        $otp->issue('hod@mmmut.ac.in');

        $this->assertSame(2, (int) DB::table('aa_password_otps')->whereNull('used_at')->value('attempts'));
        $otp->reset('hod@mmmut.ac.in', 'wrong3', 'Newpass123');
        $this->assertSame('Too many wrong attempts. Request a new code.', $otp->reset('hod@mmmut.ac.in', '000000', 'Newpass123'));
    }

    public function test_otp_codes_are_limited_per_email(): void
    {
        $this->otpSetup();
        $otp = app(PasswordOtpService::class);
        $sent = collect(range(1, 7))->map(fn () => $otp->issue('HOD@mmmut.ac.in'))->filter()->count();
        $this->assertSame(PasswordOtpService::ISSUES_PER_HOUR, $sent);
    }

    public function test_faculty_can_download_only_their_own_proofs(): void
    {
        $this->seed(AcademicAuditSeeder::class);
        Storage::fake('local');
        app(SettingsService::class)->set('workflow.faculty_contributions', true);
        $cse = Department::where('short_name', 'CSE')->first();
        $audit = Audit::where('department_id', $cse->id)->firstOrFail();
        $make = function (string $name) use ($cse) {
            $u = User::factory()->create();
            UserRole::create(['user_id' => $u->id, 'role' => 'faculty', 'department_id' => $cse->id]);
            return [$u, Faculty::create(['name' => $name, 'department_id' => $cse->id, 'user_id' => $u->id])];
        };
        [$asha, $ashaF] = $make('Asha');
        [$ravi] = $make('Ravi');
        Storage::disk('local')->put('academic-audit/p.pdf', 'x');
        $own = Entry::create(['audit_id' => $audit->id, 'parameter_code' => 'C.h', 'data' => ['title' => 'A'], 'faculty_id' => $ashaF->id, 'proof_path' => 'academic-audit/p.pdf']);
        $hodRow = Entry::create(['audit_id' => $audit->id, 'parameter_code' => 'C.h', 'data' => ['title' => 'H'], 'proof_path' => 'academic-audit/p.pdf']);

        $this->actingAs($asha)->get(route('audit.entries.proof', [$audit, $own]))->assertOk();
        $this->actingAs($asha)->get(route('audit.entries.proof', [$audit, $hodRow]))->assertForbidden();
        $this->actingAs($ravi)->get(route('audit.entries.proof', [$audit, $own]))->assertForbidden();
    }

    public function test_university_excel_without_cycle_is_404_not_500(): void
    {
        $iqac = User::factory()->create();
        UserRole::create(['user_id' => $iqac->id, 'role' => 'iqac']);
        Cycle::query()->delete();
        $this->actingAs($iqac)->get(route('audit.reports.university.xlsx'))->assertNotFound();
    }
}
