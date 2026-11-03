<?php

namespace App\Services\Sso;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\DepartmentAlias;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\DepartmentList;

/**
 * First central login of someone without any audit role: give HOD / Faculty from the Q-Sphere
 * claims (qs_roles, dept_code). Never IQAC or Auditor; roles are only added, never removed.
 */
class RoleProvisioner
{
    public const NO_ROLE = 'no_role';
    public const NO_DEPARTMENT = 'no_department';
    public const LEFT = 'left';

    public function hasAccess(User $user): bool
    {
        return UserRole::where('user_id', $user->id)->exists()
            || Faculty::where('is_active', true)->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]))->exists();
    }

    /**
     * Grant the default roles. Returns null when the user now has access, otherwise why not
     * (NO_ROLE: not hod/faculty in Q-Sphere; NO_DEPARTMENT: department unknown; LEFT: faculty record marked inactive).
     */
    public function provision(User $user, array $claims): ?string
    {
        $qsRoles = $this->qsRoles($claims['qs_roles'] ?? []);
        $wanted = array_values(array_intersect([UserRole::HOD, UserRole::FACULTY], $qsRoles));
        if ($wanted === []) {
            return self::NO_ROLE;
        }
        $dept = $this->department($claims['dept_code'] ?? null);
        if (! $dept) {
            Log::info("SSO: {$user->email} is ".implode('/', $wanted)." in Q-Sphere but dept_code '".($claims['dept_code'] ?? '')."' matches no audit department.");
            return self::NO_DEPARTMENT;
        }
        // hasAccess() was false, so any faculty record of this person is inactive (left / marked by IQAC)
        if (Faculty::whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])->orWhere('user_id', $user->id)->exists()) {
            return self::LEFT;
        }

        DB::transaction(function () use ($user, $claims, $wanted, $dept) {
            foreach ($wanted as $role) {
                UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $role, 'department_id' => $dept->id]);
            }
            Faculty::create([
                'department_id' => $dept->id,
                'user_id' => $user->id,
                'employee_code' => $claims['employee_id'] ?? null,
                'name' => $user->name,
                'email' => mb_strtolower($user->email),
                'is_regular' => true,
                'is_active' => true,
                'source' => 'qsphere',
            ]);
        });
        Log::info("SSO: gave {$user->email} the role(s) ".implode(', ', $wanted)." ({$dept->short_name}) from Q-Sphere.");

        return null;
    }

    /** Q-Sphere department code → academic audit department (code aliases, short name, built-in code list). */
    public function department(?string $code): ?Department
    {
        $code = mb_strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $id = DepartmentAlias::whereRaw('UPPER(alias) = ?', [$code])->value('department_id')
            ?? Department::whereRaw('UPPER(short_name) = ?', [$code])->value('id')
            ?? (isset(DepartmentList::QSPHERE_CODES[$code]) ? Department::where('short_name', DepartmentList::QSPHERE_CODES[$code])->value('id') : null);

        return $id ? Department::where('id', $id)->where('is_academic', true)->first() : null;
    }

    private function qsRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            $roles = preg_split('/[\s,]+/', $roles, -1, PREG_SPLIT_NO_EMPTY);
        }
        return array_map(fn ($r) => mb_strtolower(trim((string) $r)), is_array($roles) ? $roles : []);
    }
}
