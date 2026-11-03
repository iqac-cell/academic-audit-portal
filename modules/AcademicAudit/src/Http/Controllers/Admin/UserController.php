<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\UserRole;

class UserController extends Controller
{
    public function index(Request $request, \Mmmut\AcademicAudit\Services\PasswordOtpService $otp)
    {
        $this->iqac();
        $roles = UserRole::with('user', 'department')
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->get()
            ->filter(fn ($r) => $r->user);
        // one line per person with all of their roles
        $people = $roles->groupBy('user_id')->map(fn ($rs) => ['user' => $rs->first()->user, 'roles' => $rs->sortBy('role')->values()])
            ->sortBy(fn ($p) => mb_strtolower($p['user']->name))->values();

        return $this->view('admin.users', [
            'people' => $people,
            'departments' => Department::orderBy('name')->get(),
            'labels' => UserRole::LABELS,
            'otpEnabled' => $otp->enabled(),
        ]);
    }

    public function sendOtp(int $user, \Mmmut\AcademicAudit\Services\PasswordOtpService $otp)
    {
        $this->iqac();
        $model = config('academic-audit.user_model');
        $u = $model::findOrFail($user);
        abort_unless($otp->enabled(), 422, 'Password reset by email is not enabled.');
        return back()->with('status', $otp->issue($u->email, request()->ip())
            ? "Reset code sent to {$u->email}."
            : "Could not send the code to {$u->email} (check email settings).");
    }

    public function store(Request $request, \Mmmut\AcademicAudit\Services\UserImporter $importer)
    {
        $this->iqac();
        $data = $request->validate([
            'email' => 'required|email|max:200',
            'name' => 'nullable|string|max:200',
            'role' => 'required|in:'.implode(',', array_keys(UserRole::LABELS)),
            'department_id' => 'nullable|required_if:role,hod,faculty|exists:aa_departments,id',
        ]);
        $model = config('academic-audit.user_model');
        if (! $model::where('email', mb_strtolower($data['email']))->exists()) {
            $request->validate(['name' => 'required']);
        }
        [$user] = $importer->ensureUser($data['name'] ?? $data['email'], $data['email']);
        $importer->grant($user, $data['role'], isset($data['department_id']) ? (int) $data['department_id'] : null);
        return back()->with('status', "{$user->name} assigned as ".UserRole::LABELS[$data['role']].'.');
    }

    public function importForm()
    {
        $this->iqac();
        return $this->view('admin.users-import', ['rows' => session('audit.user_import'), 'mailEnabled' => app(\Mmmut\AcademicAudit\Services\SettingsService::class)->get('mail.enabled')]);
    }

    public function importTemplate(\Mmmut\AcademicAudit\Services\UserImporter $importer)
    {
        $this->iqac();
        $file = tempnam(sys_get_temp_dir(), 'aa');
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($importer->template(), 'Xlsx')->save($file);
        return response()->download($file, 'academic-audit-users-template.xlsx')->deleteFileAfterSend();
    }

    public function importPreview(Request $request, \Mmmut\AcademicAudit\Services\UserImporter $importer)
    {
        $this->iqac();
        $request->validate(['file' => 'required|file|mimes:xlsx|max:5120']);
        try {
            $rows = $importer->preview($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e instanceof \RuntimeException ? $e->getMessage() : 'The file could not be read as an Excel (.xlsx) sheet.']);
        }
        if (! $rows) {
            return back()->withErrors(['file' => 'No user rows found in the sheet.']);
        }
        session(['audit.user_import' => $rows]);
        return redirect()->route('audit.admin.users.import');
    }

    public function importConfirm(Request $request, \Mmmut\AcademicAudit\Services\UserImporter $importer)
    {
        $this->iqac();
        $rows = session()->pull('audit.user_import');
        if (! $rows) {
            return redirect()->route('audit.admin.users.import')->withErrors(['file' => 'Upload the sheet again.']);
        }
        $r = $importer->import($rows, $request->boolean('send_mail'));
        $skipped = collect($rows)->where('status', 'error')->count();
        return redirect()->route('audit.admin.users')->with('status',
            "Import done: {$r['created']} new user(s), {$r['updated']} existing user(s) updated, {$r['roles']} role(s) added"
            .($skipped ? ", {$skipped} row(s) with errors skipped" : '').($r['mailed'] ? ", {$r['mailed']} login email(s) sent" : '').'.');
    }

    public function importCancel()
    {
        $this->iqac();
        session()->forget('audit.user_import');
        return redirect()->route('audit.admin.users.import');
    }

    public function destroy(UserRole $role)
    {
        $this->iqac();
        abort_if($role->user_id === auth()->id() && $role->role === UserRole::IQAC, 422, 'You cannot remove your own IQAC role.');
        $role->delete();
        return back()->with('status', 'Role removed.');
    }

    public function resetPassword(int $user)
    {
        $this->iqac();
        $model = config('academic-audit.user_model');
        $u = $model::findOrFail($user);
        $password = \Mmmut\AcademicAudit\Support\Passwords::temporary();
        try {
            \Mmmut\AcademicAudit\Support\Passwords::setTemporary($u, $password)->save();
        } catch (\Throwable $e) {
            return $this->failed('reset the password', $e);
        }
        $this->endSessions($u->id);
        $this->audit("Password of user {$u->email} reset to a temporary password by ".auth()->user()?->email);
        return back()->with('status', "Temporary password for {$u->name} ({$u->email}): {$password} — shown only once. "
            .'Give it to them personally; they must change it at their next local login. People with a central (Q-Sphere) login do not need it.');
    }

    /** Admin sets a new password (typed, or generated and shown once). */
    public function setPassword(Request $request, int $user, \Mmmut\AcademicAudit\Services\SettingsService $settings)
    {
        $this->iqac();
        $model = config('academic-audit.user_model');
        $u = $model::findOrFail($user);
        $data = $request->validate([
            'mode' => 'required|in:type,generate',
            'password' => ['exclude_unless:mode,type', 'required', 'confirmed',
                \Illuminate\Validation\Rules\Password::min(max(6, (int) $settings->get('security.password_min')))->letters()->numbers()],
        ]);
        $password = $data['mode'] === 'generate' ? $this->generatePassword() : $data['password'];

        try {
            $u->forceFill(['password' => Hash::make($password)]);
            if (\Illuminate\Support\Facades\Schema::hasColumn($u->getTable(), 'must_change_password')) {
                $u->forceFill(['must_change_password' => $request->boolean('must_change')]);
            }
            $u->save();
        } catch (\Throwable $e) {
            return $this->failed('set the password', $e);
        }
        $this->endSessions($u->id);
        $this->audit("Password of user {$u->email} set by ".auth()->user()?->email);

        $back = back()->with('status', "New password set for {$u->name}.".($request->boolean('must_change') ? ' They must change it at next login.' : ''));
        return $data['mode'] === 'generate' ? $back->with('generated_password', ['name' => $u->name, 'email' => $u->email, 'password' => $password]) : $back;
    }

    public function destroyUser(int $user)
    {
        $this->iqac();
        $model = config('academic-audit.user_model');
        $u = $model::findOrFail($user);

        if ((int) $u->id === (int) auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        $iqacIds = UserRole::where('role', UserRole::IQAC)->pluck('user_id')->unique();
        if ($iqacIds->contains($u->id) && $iqacIds->count() <= 1) {
            return back()->withErrors(['user' => 'This is the last IQAC administrator and cannot be deleted.']);
        }
        $open = \Mmmut\AcademicAudit\Models\Audit::with('department', 'cycle')
            ->where(fn ($q) => $q->where('auditor1_user_id', $u->id)->orWhere('auditor2_user_id', $u->id))
            ->where('status', '!=', \Mmmut\AcademicAudit\Models\Audit::LOCKED)->get();
        if ($open->isNotEmpty()) {
            $depts = $open->map(fn ($a) => ($a->department->short_name ?: $a->department->name).' '.$a->cycle?->academic_year)->implode(', ');
            return back()->withErrors(['user' => "{$u->name} is an academic auditor of audits that are not locked yet ({$depts}). Assign another auditor first."]);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($u) {
                UserRole::where('user_id', $u->id)->delete();
                \Mmmut\AcademicAudit\Models\Faculty::where('user_id', $u->id)->update(['user_id' => null]);
                \Illuminate\Support\Facades\DB::table('aa_password_otps')->where('email', $u->email)->delete();
                $u->delete();
            });
        } catch (\Throwable $e) {
            return $this->failed('delete the user', $e);
        }
        $this->endSessions($u->id);
        $this->audit("User {$u->email} deleted by ".auth()->user()?->email);
        return back()->with('status', "{$u->name} ({$u->email}) deleted. Their entries in audits are kept.");
    }

    /**
     * Log the person out everywhere (sessions stored in the database). Best effort: a problem here
     * must not turn a completed password change or delete into an error page.
     */
    private function endSessions(int $userId): void
    {
        foreach ([config('session.table') ?: 'sessions', 'sso_sessions'] as $table) {
            rescue(function () use ($table, $userId) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::table($table)->where('user_id', $userId)->delete();
                }
            }, null, fn ($e) => rescue(fn () => report($e), null, false));
        }
    }

    /** Admin action trail in laravel.log; never fatal (e.g. when the log file is not writable). */
    private function audit(string $message): void
    {
        rescue(fn () => \Illuminate\Support\Facades\Log::info($message), null, false);
    }

    /** Show what went wrong on the (IQAC-only) page instead of a bare 500. */
    private function failed(string $what, \Throwable $e)
    {
        rescue(fn () => report($e), null, false);
        return back()->withErrors(['user' => "Could not {$what}: ".class_basename($e).': '.mb_substr($e->getMessage(), 0, 400)]);
    }

    private function generatePassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $pool = $letters.$digits;
        $chars = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
        while (count($chars) < 10) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }
        shuffle($chars);
        return implode('', $chars);
    }
}
