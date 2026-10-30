<?php

namespace Mmmut\AcademicAudit\Support;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;

/**
 * Role checks for the logged-in user.
 *
 * A person may hold several roles (e.g. Faculty + HOD + Auditor). Exactly one of them is
 * "active" at a time (chosen with the role switcher, kept in the session) and every
 * permission check looks at the active role only.
 */
class Access
{
    public const SESSION_KEY = 'audit.active_role';

    /** Default role after login, first match wins. */
    private const PRIORITY = [UserRole::IQAC, UserRole::HOD, UserRole::AUDITOR, UserRole::FACULTY];

    private ?Collection $available = null;
    private Faculty|false|null $faculty = null;
    private ?int $cachedFor = null;

    /** Drop cached roles when the authenticated user changed (queue workers, tests, Octane). */
    private function fresh(): void
    {
        if ($this->cachedFor !== $this->userId()) {
            $this->available = null;
            $this->faculty = null;
            $this->cachedFor = $this->userId();
        }
    }

    public function userId(): ?int
    {
        return auth()->id();
    }

    /**
     * Every role the user holds, as ['key' => 'hod:3', 'role' => 'hod', 'department_id' => 3, 'label' => 'HOD – CSE'].
     * A linked faculty record counts as a faculty role even without an explicit aa_user_roles row.
     */
    public function availableRoles(): Collection
    {
        $this->fresh();
        if ($this->available !== null) {
            return $this->available;
        }
        if (! $this->userId()) {
            return $this->available = collect();
        }

        $rows = UserRole::with('department')->where('user_id', $this->userId())->get()
            ->map(fn (UserRole $r) => [$r->role, $r->department_id, $r->department]);
        if (($f = $this->faculty()) && ! $rows->contains(fn ($r) => $r[0] === UserRole::FACULTY && $r[1] === $f->department_id)) {
            $rows->push([UserRole::FACULTY, $f->department_id, $f->department]);
        }

        return $this->available = $rows
            ->map(fn ($r) => [
                'key' => $r[0].':'.($r[1] ?? ''),
                'role' => $r[0],
                'department_id' => $r[1],
                'label' => self::label($r[0], $r[2]),
            ])
            ->unique('key')
            ->sortBy(fn ($r) => array_search($r['role'], self::PRIORITY).'|'.$r['label'])
            ->values();
    }

    public static function label(string $role, ?Department $dept): string
    {
        $short = ['iqac' => 'IQAC', 'hod' => 'HOD', 'faculty' => 'Faculty', 'auditor' => 'Auditor'][$role] ?? $role;
        return $dept ? "{$short} – ".($dept->short_name ?: $dept->name) : $short;
    }

    /** The active role entry, or null when the user has no role at all. */
    public function active(): ?array
    {
        $roles = $this->availableRoles();
        if ($roles->isEmpty()) {
            return null;
        }
        $key = session(self::SESSION_KEY);
        return $roles->firstWhere('key', $key) ?? $roles->first();
    }

    public function switchTo(string $key): bool
    {
        if (! $this->availableRoles()->contains('key', $key)) {
            return false;
        }
        session([self::SESSION_KEY => $key]);
        return true;
    }

    /** Active role as a collection (kept for callers that list roles). */
    public function roles(): Collection
    {
        return collect(array_filter([$this->active()]));
    }

    public function has(string $role): bool
    {
        return ($this->active()['role'] ?? null) === $role;
    }

    public function holds(string $role): bool
    {
        return $this->availableRoles()->contains('role', $role);
    }

    public function isIqac(): bool
    {
        return $this->has(UserRole::IQAC);
    }

    public function isAuditor(): bool
    {
        return $this->has(UserRole::AUDITOR);
    }

    public function isFaculty(): bool
    {
        return $this->has(UserRole::FACULTY);
    }

    public function activeDepartmentId(): ?int
    {
        return $this->active()['department_id'] ?? null;
    }

    public function hodDepartmentIds(): array
    {
        return $this->has(UserRole::HOD) ? array_filter([$this->activeDepartmentId()]) : [];
    }

    public function isHodOf(Audit $audit): bool
    {
        return in_array($audit->department_id, $this->hodDepartmentIds());
    }

    public function isAuditorOf(Audit $audit): bool
    {
        return $this->isAuditor() && in_array($this->userId(), [$audit->auditor1_user_id, $audit->auditor2_user_id]);
    }

    /** 1 or 2 when the user is acting as Auditor-1 / Auditor-2 of the audit. */
    public function auditorSlot(Audit $audit): ?int
    {
        if (! $this->isAuditor()) {
            return null;
        }
        return match ($this->userId()) {
            null => null,
            $audit->auditor1_user_id => 1,
            $audit->auditor2_user_id => 2,
            default => null,
        };
    }

    /** Faculty record linked to the user (independent of the active role). */
    public function faculty(): ?Faculty
    {
        $this->fresh();
        if ($this->faculty === null) {
            $user = auth()->user();
            $this->faculty = $user
                ? (Faculty::where('user_id', $user->id)->first() ?? ($user->email ? Faculty::where('email', $user->email)->first() : null)) ?? false
                : false;
        }

        return $this->faculty ?: null;
    }

    public function isFacultyOf(Audit $audit): bool
    {
        return $this->isFaculty() && $this->activeDepartmentId() === $audit->department_id
            && $this->faculty()?->department_id === $audit->department_id;
    }

    public function canView(Audit $audit): bool
    {
        return $this->isIqac() || $this->isHodOf($audit) || $this->isAuditorOf($audit) || $this->isFacultyOf($audit);
    }

    public function canEdit(Audit $audit): bool
    {
        return $audit->isEditable() && ($this->isHodOf($audit) || $this->isIqac());
    }

    public function canContribute(Audit $audit): bool
    {
        return $audit->isEditable() && $audit->cycle->status === 'open' && $this->isFacultyOf($audit)
            && app(\Mmmut\AcademicAudit\Services\SettingsService::class)->enabled('workflow.faculty_contributions');
    }

    public function canVerify(Audit $audit): bool
    {
        return $this->isIqac() && in_array($audit->status, [Audit::SUBMITTED, Audit::VERIFIED], true);
    }

    public function canMark(Audit $audit): bool
    {
        return $audit->status === Audit::VERIFIED && $this->isAuditorOf($audit);
    }
}
