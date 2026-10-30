<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Mmmut\AcademicAudit\Services\PasswordOtpService;
use Mmmut\AcademicAudit\Services\SettingsService;

/** "Forgot password" with a one-time code sent by email (enabled from IQAC Settings). */
class PasswordResetController extends Controller
{
    public function __construct(private PasswordOtpService $otp, private SettingsService $settings) {}

    public function request()
    {
        return view('auth.forgot-password', ['enabled' => $this->otp->enabled(), 'contact' => $this->settings->get('general.contact_email')]);
    }

    public function send(Request $request)
    {
        abort_unless($this->otp->enabled(), 404);
        $email = $request->validate(['email' => 'required|email|max:200'])['email'];
        $this->otp->issue($email, $request->ip());
        session(['otp_email' => mb_strtolower($email)]);
        return redirect()->route('password.otp.verify')
            ->with('status', 'If this email is registered, a code has been sent to it. Check your inbox (and spam folder).');
    }

    public function verifyForm()
    {
        abort_unless($this->otp->enabled(), 404);
        return view('auth.reset-password', ['email' => session('otp_email'), 'min' => (int) $this->settings->get('security.password_min')]);
    }

    public function reset(Request $request)
    {
        abort_unless($this->otp->enabled(), 404);
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|max:12',
            'password' => ['required', 'confirmed', Password::min(max(6, (int) $this->settings->get('security.password_min')))->letters()->numbers()],
        ]);
        if ($error = $this->otp->reset($data['email'], $data['code'], $data['password'])) {
            return back()->withErrors(['code' => $error])->onlyInput('email');
        }
        session()->forget('otp_email');
        return redirect()->route('login')->with('status', 'Password changed. Please log in with your new password.');
    }
}
