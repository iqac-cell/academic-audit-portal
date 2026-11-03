<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Notifications\AuditMail;
use Mmmut\AcademicAudit\Services\SettingsService;
use Mmmut\AcademicAudit\Services\UserImporter;
use Mmmut\AcademicAudit\Support\Passwords;
use Tests\TestCase;

class NoDefaultPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAuditSeeder::class);
    }

    private function assertNoKnownPassword(User $u): void
    {
        $this->assertFalse(Hash::check(Passwords::LEGACY_DEFAULT, $u->password));
        $this->assertTrue((bool) $u->must_change_password);
    }

    public function test_new_users_get_no_shared_password(): void
    {
        [$a] = app(UserImporter::class)->ensureUser('A', 'a@mmmut.ac.in');
        [$b] = app(UserImporter::class)->ensureUser('B', 'b@mmmut.ac.in');
        $this->artisan('audit:grant', ['email' => 'c@mmmut.ac.in', 'role' => 'auditor'])->assertSuccessful();
        $c = User::where('email', 'c@mmmut.ac.in')->first();

        foreach ([$a->fresh(), $b->fresh(), $c] as $u) {
            $this->assertNoKnownPassword($u);
        }
        $this->assertNotSame($a->fresh()->password, $b->fresh()->password);
    }

    public function test_welcome_mail_contains_no_password(): void
    {
        Notification::fake();
        app(SettingsService::class)->set('mail.enabled', true);
        $u = User::factory()->create();
        UserRole::create(['user_id' => $u->id, 'role' => 'iqac']);
        $importer = app(UserImporter::class);

        $rows = [['name' => 'New One', 'email' => 'new.one@mmmut.ac.in', 'roles' => ['auditor'], 'department_id' => null, 'designation' => null, 'mobile' => null, 'status' => 'new', 'messages' => []]];
        $importer->import($rows, true);

        $new = User::where('email', 'new.one@mmmut.ac.in')->first();
        Notification::assertSentTo($new, AuditMail::class, function (AuditMail $mail) use ($new) {
            $text = implode(' ', $mail->toMail($new)->introLines);
            return ! str_contains($text, Passwords::LEGACY_DEFAULT) && ! str_contains(strtolower($text), 'password:');
        });
    }

    public function test_iqac_reset_gives_a_random_temporary_password(): void
    {
        $iqac = User::factory()->create();
        UserRole::create(['user_id' => $iqac->id, 'role' => 'iqac']);
        $hod = User::factory()->create(['email' => 'hod@mmmut.ac.in']);

        $res = $this->actingAs($iqac)->post(route('audit.admin.users.reset', $hod->id));
        $status = session('status');
        $this->assertMatchesRegularExpression('/Temporary password for .*: ([A-Za-z0-9]{12}) /', $status);
        preg_match('/: ([A-Za-z0-9]{12}) /', $status, $m);
        $hod->refresh();
        $this->assertTrue(Hash::check($m[1], $hod->password));
        $this->assertFalse(Hash::check(Passwords::LEGACY_DEFAULT, $hod->password));
        $this->assertTrue((bool) $hod->must_change_password);
        $res->assertRedirect();
    }

    public function test_default_password_check_counts_without_changing_anything(): void
    {
        $old = User::factory()->create(['email' => 'old@mmmut.ac.in', 'password' => Hash::make(Passwords::LEGACY_DEFAULT)]);
        User::factory()->create(['email' => 'fine@mmmut.ac.in']);
        $hash = $old->password;

        $this->artisan('audit:default-password-check', ['--list' => true])
            ->expectsOutputToContain('1 of 2 accounts still have the old default password.')
            ->expectsOutputToContain('old@mmmut.ac.in')
            ->assertSuccessful();
        $this->assertSame($hash, $old->fresh()->password);
    }
}
