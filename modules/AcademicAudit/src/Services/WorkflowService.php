<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Validation\ValidationException;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Models\Score;

/**
 * draft → submitted → (returned → submitted)* → verified → audited → locked
 */
class WorkflowService
{
    public function __construct(private ScoreService $scores, private Notifier $notifier) {}

    public function submit(Audit $audit): void
    {
        $this->expect($audit, [Audit::DRAFT, Audit::RETURNED]);
        $pending = Entry::where('audit_id', $audit->id)->where('status', Entry::PENDING)->count();
        if ($pending) {
            throw ValidationException::withMessages(['submit' => "{$pending} faculty entries are awaiting HOD approval. Approve or reject them before submitting."]);
        }
        $this->scores->store($audit);
        $audit->update(['status' => Audit::SUBMITTED, 'submitted_at' => now(), 'hod_user_id' => auth()->id()]);
        $audit->log('submitted', 'Submitted to IQAC');
        $this->notifier->submitted($audit);
    }

    public function returnToHod(Audit $audit, string $remark): void
    {
        $this->expect($audit, [Audit::SUBMITTED, Audit::VERIFIED]);
        $audit->update(['status' => Audit::RETURNED, 'iqac_remark' => $remark, 'verified_at' => null]);
        $audit->log('returned', $remark);
        $this->notifier->returned($audit);
    }

    public function verify(Audit $audit): void
    {
        $this->expect($audit, [Audit::SUBMITTED]);
        if (! $audit->auditor1_user_id || ! $audit->auditor2_user_id) {
            throw ValidationException::withMessages(['verify' => 'Assign Auditor-1 and Auditor-2 before marking the audit verified.']);
        }
        Score::where('audit_id', $audit->id)->whereNull('iqac_status')->update(['iqac_status' => 'verified']);
        $audit->update(['status' => Audit::VERIFIED, 'verified_at' => now()]);
        $audit->log('verified', 'Verified by IQAC, forwarded to auditors');
        $this->notifier->verified($audit);
    }

    public function auditorDone(Audit $audit, int $slot): void
    {
        $this->expect($audit, [Audit::VERIFIED]);
        $col = "auditor{$slot}_marks";
        $missing = Score::where('audit_id', $audit->id)->whereNull($col)->count();
        if ($missing) {
            throw ValidationException::withMessages(['marks' => "Marks not entered for {$missing} parameters."]);
        }
        $audit->update(["auditor{$slot}_done_at" => now()]);
        $audit->log("auditor{$slot}_done", "Auditor-{$slot} finalised marks");
        $audit->refresh();
        if ($audit->auditor1_done_at && $audit->auditor2_done_at) {
            $this->scores->computeAwarded($audit);
            $audit->update(['status' => Audit::AUDITED]);
            $audit->log('audited', 'Both auditors finalised; awarded marks computed');
        }
    }

    public function reopenMarking(Audit $audit): void
    {
        $this->expect($audit, [Audit::AUDITED]);
        $audit->update(['status' => Audit::VERIFIED, 'auditor1_done_at' => null, 'auditor2_done_at' => null]);
        $audit->log('reopened', 'Marking reopened by IQAC');
    }

    public function lock(Audit $audit): void
    {
        $this->expect($audit, [Audit::AUDITED]);
        $audit->update(['status' => Audit::LOCKED, 'locked_at' => now()]);
        $audit->log('locked', 'Audit locked by IQAC');
    }

    public function unlock(Audit $audit): void
    {
        $this->expect($audit, [Audit::LOCKED]);
        $audit->update(['status' => Audit::AUDITED, 'locked_at' => null]);
        $audit->log('unlocked', 'Audit unlocked by IQAC');
    }

    private function expect(Audit $audit, array $states): void
    {
        if (! in_array($audit->status, $states, true)) {
            throw ValidationException::withMessages(['status' => "Action not allowed while audit is '{$audit->statusLabel()}'."]);
        }
    }
}
