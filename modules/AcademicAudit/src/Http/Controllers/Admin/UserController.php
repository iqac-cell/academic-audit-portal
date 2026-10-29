<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\UserRole;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->iqac();
        $roles = UserRole::with('user', 'department')
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->get()
            ->filter(fn ($r) => $r->user)
            ->sortBy([['role', 'asc'], ['department.name', 'asc'], ['user.name', 'asc']]);

        return $this->view('admin.users', [
            'roles' => $roles,
            'departments' => Department::orderBy('name')->get(),
            'labels' => UserRole::LABELS,
        ]);
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
            if (\Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'must_change_password')) {
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
