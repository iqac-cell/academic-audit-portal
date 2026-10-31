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
        $u->forceFill(['password' => Hash::make(config('academic-audit.default_password'))]);
        if (\Illuminate\Support\Facades\Schema::hasColumn($u->getTable(), 'must_change_password')) {
            $u->forceFill(['must_change_password' => true]);
        }
        $u->save();
        return back()->with('status', "Password of {$u->name} reset to the default password.");
    }
}
