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

    public function store(Request $request)
    {
        $this->iqac();
        $data = $request->validate([
            'email' => 'required|email|max:200',
            'name' => 'nullable|string|max:200',
            'role' => 'required|in:'.implode(',', array_keys(UserRole::LABELS)),
            'department_id' => 'nullable|required_if:role,hod,faculty|exists:aa_departments,id',
        ]);
        $model = config('academic-audit.user_model');
        $user = $model::where('email', $data['email'])->first();
        if (! $user) {
            $request->validate(['name' => 'required']);
            $user = new $model;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(config('academic-audit.default_password')),
            ]);
            if (\Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'must_change_password')
                && app(\Mmmut\AcademicAudit\Services\SettingsService::class)->get('security.force_change')) {
                $user->forceFill(['must_change_password' => true]);
            }
            $user->save();
        }
        $deptId = in_array($data['role'], [UserRole::HOD, UserRole::FACULTY]) ? $data['department_id'] : null;
        UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $data['role'], 'department_id' => $deptId]);
        return back()->with('status', "{$user->name} assigned as ".UserRole::LABELS[$data['role']].'.');
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
