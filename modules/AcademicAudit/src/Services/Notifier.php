<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Notifications\AuditMail;
use Mmmut\AcademicAudit\Support\Catalogue;

/**
 * Workflow emails. Each one is sent only when email + notifications + that event are enabled
 * in Settings, and a mail failure never interrupts the workflow action that triggered it.
 */
class Notifier
{
    public function __construct(private SettingsService $settings) {}

    public function submitted(Audit $audit): void
    {
        $this->send('notify.submitted', $this->usersWithRole(UserRole::IQAC),
            "Academic audit submitted: {$audit->department->name}",
            ["The Department of {$audit->department->name} has submitted its academic audit for {$audit->cycle->academic_year}.", 'Please verify the parameters and assign the academic auditors.'],
            route('audit.show', $audit));
    }

    public function returned(Audit $audit): void
    {
        $this->send('notify.returned', $this->usersWithRole(UserRole::HOD, $audit->department_id),
            "Academic audit returned for correction ({$audit->cycle->academic_year})",
            ['IQAC has returned your department\'s academic audit with the following remarks:', (string) $audit->iqac_remark, 'Please correct the data and submit again.'],
            route('audit.show', $audit));
    }

    public function verified(Audit $audit): void
    {
        $users = $this->usersWithRole(UserRole::HOD, $audit->department_id)->merge($this->auditors($audit))->unique('id');
        $this->send('notify.verified', $users,
            "Academic audit verified: {$audit->department->name}",
            ["IQAC has verified the academic audit of {$audit->department->name} ({$audit->cycle->academic_year}).", 'The academic auditors can now award marks.'],
            route('audit.evaluation', $audit));
    }

    public function auditorsAssigned(Audit $audit, array $newUserIds): void
    {
        $model = config('academic-audit.user_model');
        $this->send('notify.auditor_assigned', $model::whereIn('id', $newUserIds)->get(),
            "You are an academic auditor: {$audit->department->name}",
            ["IQAC has assigned you as academic auditor for the Department of {$audit->department->name} ({$audit->cycle->academic_year}).", 'You can enter marks once IQAC marks the audit as verified. Switch to the Auditor role after login.'],
            route('audit.evaluation', $audit));
    }

    public function entryReviewed(Entry $entry): void
    {
        $userId = $entry->faculty?->user_id ?? $entry->created_by;
        $model = config('academic-audit.user_model');
        $def = Catalogue::get($entry->parameter_code);
        $first = $def['columns'][0]['key'] ?? null;
        $approved = $entry->status === Entry::APPROVED;
        $lines = [($approved ? 'Your HOD has approved your entry under ' : 'Your HOD has returned your entry under ')."{$entry->parameter_code} {$def['title']}:", '“'.($entry->data[$first] ?? '').'”'];
        if (! $approved && $entry->remark) {
            $lines[] = "Remark: {$entry->remark}";
        }
        $this->send('notify.entry_reviewed', $userId ? $model::whereKey($userId)->get() : collect(),
            $approved ? 'Academic audit entry approved' : 'Academic audit entry needs correction',
            $lines, route('audit.items', [$entry->audit_id, $entry->parameter_code]));
    }

    public function reminder(Audit $audit, int $daysLeft): bool
    {
        return $this->send('notify.enabled', $this->usersWithRole(UserRole::HOD, $audit->department_id),
            "Reminder: academic audit closes in {$daysLeft} day(s)",
            ["The academic audit {$audit->cycle->academic_year} closes on {$audit->cycle->closes_on->format('d M Y')}.", "Your department's audit is still '{$audit->statusLabel()}'. Please complete and submit it before the deadline."],
            route('audit.show', $audit));
    }

    /** Test mail from Settings (ignores the notification toggles, needs mail configured). */
    public function test(object $user): void
    {
        $user->notify(new AuditMail('Test email from the Academic Audit portal', ['Email settings are working.']));
    }

    private function send(string $toggle, $users, string $subject, array $lines, ?string $url): bool
    {
        if (! $this->settings->enabled($toggle) || ! $this->settings->get('mail.enabled')) {
            return false;
        }
        $users = collect($users)->filter(fn ($u) => filled($u->email ?? null));
        if ($users->isEmpty()) {
            return false;
        }
        try {
            Notification::send($users, new AuditMail($subject, $lines, 'Open portal', $url));
            return true;
        } catch (\Throwable $e) {
            Log::warning('Academic audit mail failed: '.$e->getMessage(), ['subject' => $subject]);
            return false;
        }
    }

    private function usersWithRole(string $role, ?int $departmentId = null)
    {
        $model = config('academic-audit.user_model');
        $ids = UserRole::where('role', $role)->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))->pluck('user_id');
        return $model::whereIn('id', $ids)->get();
    }

    private function auditors(Audit $audit)
    {
        $model = config('academic-audit.user_model');
        return $model::whereIn('id', array_filter([$audit->auditor1_user_id, $audit->auditor2_user_id]))->get();
    }
}
