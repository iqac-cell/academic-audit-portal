<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Services\WorkflowService;

class WorkflowController extends Controller
{
    public function handle(Request $request, Audit $audit, string $action, WorkflowService $workflow)
    {
        $this->viewable($audit);
        $access = $this->access();

        switch ($action) {
            case 'submit':
                abort_unless($access->canEdit($audit), 403);
                $workflow->submit($audit);
                return back()->with('status', 'Audit submitted to IQAC.');
            case 'return':
                $this->iqac();
                $remark = $request->validate(['remark' => 'required|string|max:2000'])['remark'];
                $workflow->returnToHod($audit, $remark);
                return back()->with('status', 'Audit returned to HOD.');
            case 'verify':
                $this->iqac();
                $workflow->verify($audit);
                return back()->with('status', 'Audit verified and forwarded to auditors.');
            case 'auditor-done':
                $slot = $access->auditorSlot($audit);
                abort_unless($slot && $access->canMark($audit), 403);
                $workflow->auditorDone($audit, $slot);
                return back()->with('status', 'Your marks are finalised.');
            case 'reopen':
                $this->iqac();
                $workflow->reopenMarking($audit);
                return back()->with('status', 'Marking reopened.');
            case 'lock':
                $this->iqac();
                $workflow->lock($audit);
                return back()->with('status', 'Audit locked.');
            case 'unlock':
                $this->iqac();
                $workflow->unlock($audit);
                return back()->with('status', 'Audit unlocked.');
        }
        abort(404);
    }
}
