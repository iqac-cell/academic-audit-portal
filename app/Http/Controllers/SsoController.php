<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Sso\OidcClient;
use App\Services\Sso\RoleProvisioner;
use App\Services\Sso\SsoException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mmmut\AcademicAudit\Support\Access;
use Mmmut\AcademicAudit\Support\Passwords;

/** Central login through Q-Sphere (OpenID Connect authorization code flow with PKCE). */
class SsoController extends Controller
{
    public const SESSION_VIA = 'auth.via';

    public function __construct(private OidcClient $oidc, private RoleProvisioner $roles) {}

    public function redirect(Request $request)
    {
        abort_unless($this->oidc->enabled(), 404);
        $auth = $this->oidc->authorizationRequest();
        $request->session()->put('sso.pending', [
            'state' => $auth['state'],
            'nonce' => $auth['nonce'],
            'verifier' => $auth['verifier'],
            'at' => time(),
        ]);

        return redirect()->away($auth['url']);
    }

    public function callback(Request $request)
    {
        abort_unless($this->oidc->enabled(), 404);
        $pending = $request->session()->pull('sso.pending');

        if ($request->filled('error')) {
            return $this->failed('The login server refused the sign-in: '.Str::limit((string) $request->query('error_description', $request->query('error')), 200));
        }
        if (! is_array($pending) || ! is_string($request->query('state'))
            || ! hash_equals($pending['state'], $request->query('state')) || time() - $pending['at'] > 900) {
            return $this->failed('The sign-in could not be matched to this browser. Please try again.');
        }
        if (! is_string($request->query('code')) || $request->query('code') === '') {
            return $this->failed('The login server did not return a sign-in code.');
        }

        try {
            $claims = $this->oidc->exchangeCode($request->query('code'), $pending['verifier'], $pending['nonce']);
        } catch (SsoException $e) {
            Log::warning('SSO callback failed: '.$e->getMessage());
            return $this->failed($e->getMessage());
        }

        $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failed('Your central account has no email address. Please contact the IQAC.');
        }

        $user = $this->findOrCreateUser($email, $claims);
        if (! $user) {
            return $this->failed('This email address is already linked to a different central (Q-Sphere) account. '
                .'Please contact the IQAC.');
        }
        $reason = $this->roles->hasAccess($user) ? null : $this->roles->provision($user, $claims);
        if ($reason !== null) {
            return response()->view('auth.no-access', [
                'email' => $email,
                'name' => $user->name,
                'reason' => $reason,
                'contact' => app(\Mmmut\AcademicAudit\Services\SettingsService::class)->get('general.contact_email'),
                'logoutUrl' => $this->oidc->logoutUrl(),
            ], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(Access::SESSION_KEY);
        $request->session()->put(self::SESSION_VIA, 'sso');
        if (filled($claims['sid'] ?? null)) {
            DB::table('sso_sessions')->insert(['sid' => (string) $claims['sid'], 'user_id' => $user->id, 'created_at' => now()]);
        }

        return redirect()->intended(route('audit.dashboard'));
    }

    /** OpenID Connect Back-Channel Logout: Q-Sphere tells us a user signed out there. */
    public function backchannelLogout(Request $request)
    {
        abort_unless($this->oidc->enabled(), 404);
        $headers = ['Cache-Control' => 'no-store'];
        $token = $request->input('logout_token');
        if (! is_string($token) || $token === '') {
            return response()->json(['error' => 'invalid_request'], 400, $headers);
        }

        try {
            $claims = $this->oidc->verifyLogoutToken($token);
        } catch (SsoException $e) {
            Log::warning('SSO back-channel logout rejected: '.$e->getMessage());
            return response()->json(['error' => 'invalid_request', 'error_description' => $e->getMessage()], 400, $headers);
        }

        $userIds = collect();
        if (filled($claims['sub'] ?? null)) {
            $userIds = $userIds->merge(User::where('sso_sub', (string) $claims['sub'])->pluck('id'));
        }
        if (filled($claims['sid'] ?? null)) {
            $userIds = $userIds->merge(DB::table('sso_sessions')->where('sid', (string) $claims['sid'])->pluck('user_id'));
            DB::table('sso_sessions')->where('sid', (string) $claims['sid'])->delete();
        }
        $userIds = $userIds->unique()->values();

        if ($userIds->isNotEmpty()) {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->whereIn('user_id', $userIds)->delete();
            User::whereIn('id', $userIds)->update(['remember_token' => null]);
            DB::table('sso_sessions')->whereIn('user_id', $userIds)->delete();
        }

        return response('', 200, $headers);
    }

    /**
     * The account for this email. The Q-Sphere "sub" is stored at the first central login and must match
     * afterwards, so an email address reassigned in Q-Sphere cannot take over an existing account (null).
     */
    private function findOrCreateUser(string $email, array $claims): ?User
    {
        $sub = (string) $claims['sub'];
        $name = trim((string) ($claims['name'] ?? '')) ?: Str::before($email, '@');
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $user = new User(['name' => $name, 'email' => $email]);
            Passwords::setUnknown($user);
        } elseif (filled($user->sso_sub) && ! hash_equals((string) $user->sso_sub, $sub)) {
            Log::warning("SSO login refused for {$email}: Q-Sphere sub {$sub} differs from the linked sub {$user->sso_sub}.");
            return null;
        } elseif (filled($claims['name'] ?? null)) {
            $user->name = $name;
        }
        $user->forceFill(['sso_sub' => $sub])->save();

        return $user;
    }

    private function failed(string $message)
    {
        return response()->view('auth.sso-error', ['message' => $message], 400);
    }
}
