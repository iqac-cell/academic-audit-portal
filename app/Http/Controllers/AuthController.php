<?php

namespace App\Http\Controllers;

use App\Services\Sso\OidcClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Services\SettingsService;
use Mmmut\AcademicAudit\Support\Access;

class AuthController extends Controller
{
    /** With central login on, /login goes straight to Q-Sphere; /login/local stays for IQAC emergencies. */
    public function showLogin(SettingsService $settings, OidcClient $oidc)
    {
        if ($oidc->enabled()) {
            return redirect()->route('sso.redirect');
        }
        return $this->showLocalLogin($settings, $oidc);
    }

    public function showLocalLogin(SettingsService $settings, OidcClient $oidc)
    {
        return view('auth.login', [
            'sso' => $oidc->enabled(),
            'otpEnabled' => $settings->enabled('security.otp_reset'),
            'notice' => $settings->get('general.login_notice'),
            'contact' => $settings->get('general.contact_email'),
            'notices' => \Mmmut\AcademicAudit\Models\Notice::visible('login')->get(),
        ]);
    }

    public function login(Request $request, OidcClient $oidc)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->onlyInput('email');
        }
        if ($oidc->enabled() && ! UserRole::where('user_id', Auth::id())->where('role', UserRole::IQAC)->exists()) {
            Auth::logout();
            return back()->withErrors(['email' => 'This login is only for IQAC administrators. Please use the central MMMUT login.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->forget(Access::SESSION_KEY);
        $request->session()->put(SsoController::SESSION_VIA, 'local');
        return redirect()->intended(route('audit.dashboard'));
    }

    public function logout(Request $request, OidcClient $oidc)
    {
        $viaSso = $request->session()->get(SsoController::SESSION_VIA) === 'sso';
        if ($id = Auth::id()) {
            DB::table('sso_sessions')->where('user_id', $id)->delete();
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($oidc->enabled() && $viaSso) {
            return redirect()->away($oidc->logoutUrl());
        }
        return redirect()->route($oidc->enabled() ? 'login.local' : 'login');
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
