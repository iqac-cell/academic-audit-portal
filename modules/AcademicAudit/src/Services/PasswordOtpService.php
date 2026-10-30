<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Mmmut\AcademicAudit\Notifications\AuditMail;

/** One-time codes sent by email for resetting a forgotten password. */
class PasswordOtpService
{
    public function __construct(private SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->settings->enabled('security.otp_reset');
    }

    /** Sends a code if the account exists. Callers must not reveal whether it did. */
    public function issue(string $email, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        $email = mb_strtolower(trim($email));
        $model = config('academic-audit.user_model');
        $user = $model::where('email', $email)->first();
        if (! $user) {
            return false;
        }
        $digits = max(4, min(10, (int) $this->settings->get('security.otp_length')));
        $code = str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
        $minutes = max(1, (int) $this->settings->get('security.otp_expiry'));

        DB::table('aa_password_otps')->where('email', $email)->whereNull('used_at')->delete();
        DB::table('aa_password_otps')->insert([
            'email' => $email, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes($minutes),
            'attempts' => 0, 'ip' => $ip, 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $user->notify(new AuditMail('Password reset code – Academic Audit portal', [
                "Your one-time code is: {$code}",
                "It is valid for {$minutes} minutes. If you did not ask to reset your password, ignore this email.",
            ]));
        } catch (\Throwable $e) {
            Log::warning('OTP mail failed: '.$e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Checks the code and sets the new password.
     *
     * @return string|null error message, or null on success
     */
    public function reset(string $email, string $code, string $password): ?string
    {
        if (! $this->enabled()) {
            return 'Password reset by email is not enabled. Please contact IQAC.';
        }
        $email = mb_strtolower(trim($email));
        $otp = DB::table('aa_password_otps')->where('email', $email)->whereNull('used_at')->orderByDesc('id')->first();
        if (! $otp || now()->greaterThan($otp->expires_at)) {
            return 'The code is invalid or has expired. Request a new code.';
        }
        if ($otp->attempts >= max(1, (int) $this->settings->get('security.otp_attempts'))) {
            return 'Too many wrong attempts. Request a new code.';
        }
        if (! Hash::check(trim($code), $otp->code_hash)) {
            DB::table('aa_password_otps')->where('id', $otp->id)->increment('attempts');
            return 'The code is not correct.';
        }
        $model = config('academic-audit.user_model');
        $user = $model::where('email', $email)->first();
        if (! $user) {
            return 'The code is invalid or has expired. Request a new code.';
        }
        $user->forceFill(['password' => Hash::make($password)]);
        if (\Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'must_change_password')) {
            $user->forceFill(['must_change_password' => false]);
        }
        $user->save();
        DB::table('aa_password_otps')->where('id', $otp->id)->update(['used_at' => now()]);
        DB::table('aa_password_otps')->where('email', $email)->whereNull('used_at')->delete();
        return null;
    }
}
