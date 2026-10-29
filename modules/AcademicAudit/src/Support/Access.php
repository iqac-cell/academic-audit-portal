<?php

namespace Mmmut\AcademicAudit\Support;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;

/** Role checks for the logged-in user. Resolved per request. */
class Access
{
    private ?Collection $roles = null;
    private Faculty|false|null $faculty = null;
    private ?int $cachedFor = null;

    /** Drop cached roles when the authenticated user changed (queue workers, tests, Octane). */
    private function fresh(): void
    {
        if ($this->cachedFor !== $this->userId()) {
            $this->roles = null;
            $this->faculty = null;
            $this->cachedFor = $this->userId();
        }
    }

    public function userId(): ?int
    {
        return auth()->id();
    }

    public function roles(): Collection
    {
        $this->fresh();
        return $this->roles ??= $this->userId()
            ? UserRole::where('user_id', $this->userId())->get()
            : collect();
    }

    public function has(string $role): bool
    {
        return $this->roles()->contains('role', $role);
    }

    public function isIqac(): bool
    {
        return $this->has(UserRole::IQAC);
    }

    public function isAuditor(): bool
    {
        return $this->has(UserRole::AUDITOR);
    }

    public function hodDepartmentIds(): array
    {
        return $this->roles()->where('role', UserRole::HOD)->pluck('department_id')->filter()->values()->all();
    }

    public function isHodOf(Audit $audit): bool
    {
        return in_array($audit->department_id, $this->hodDepartmentIds());
    }

    public function isAuditorOf(Audit $audit): bool
    {
        return $this->userId() && in_array($this->userId(), [$audit->auditor1_user_id, $audit->auditor2_user_id]);
    }

    /** 1 or 2 when the user is Auditor-1 / Auditor-2 of the audit. */
    public function auditorSlot(Audit $audit): ?int
    {
        return match ($this->userId()) {
            null => null,
            $audit->auditor1_user_id => 1,
            $audit->auditor2_user_id => 2,
            default => null,
        };
    }

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
        return $this->faculty()?->department_id === $audit->department_id;
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
        return $audit->isEditable() && $audit->cycle->status === 'open' && $this->isFacultyOf($audit);
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
