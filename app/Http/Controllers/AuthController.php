<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Mmmut\AcademicAudit\Services\SettingsService;

class AuthController extends Controller
{
    public function showLogin(SettingsService $settings)
    {
        return view('auth.login', [
            'otpEnabled' => $settings->enabled('security.otp_reset'),
            'notice' => $settings->get('general.login_notice'),
            'contact' => $settings->get('general.contact_email'),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->forget(\Mmmut\AcademicAudit\Support\Access::SESSION_KEY);
        return redirect()->intended(route('audit.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request, SettingsService $settings)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(max(6, (int) $settings->get('security.password_min')))->letters()->numbers()],
        ]);
        $request->user()->forceFill([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ])->save();
        return redirect()->route('audit.dashboard')->with('status', 'Password changed.');
    }
}
