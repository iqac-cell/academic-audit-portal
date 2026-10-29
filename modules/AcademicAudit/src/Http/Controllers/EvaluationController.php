<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Score;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Services\ScoreService;

/** Evaluation sheet: claimed (HOD) → verification (IQAC) → marks (Auditor-1/2) → awarded. */
class EvaluationController extends Controller
{
    public function show(Audit $audit, ScoreService $scores)
    {
        $this->viewable($audit);
        $eval = $scores->evaluate($audit);
        $userModel = config('academic-audit.user_model');

        return $this->view('audit.evaluation', [
            'audit' => $audit->load('auditor1', 'auditor2'),
            'eval' => $eval,
            'summary' => $scores->summary($audit, $eval),
            'slot' => $this->access()->auditorSlot($audit),
            'canMark' => $this->access()->canMark($audit),
            'canVerify' => $this->access()->canVerify($audit),
            'canClaim' => $this->access()->canEdit($audit),
            'auditors' => $this->access()->isIqac()
                ? $userModel::whereIn('id', UserRole::where('role', UserRole::AUDITOR)->pluck('user_id'))->orderBy('name')->get()
                : collect(),
        ]);
    }

    /** HOD claims marks for manually-scored parameters (mentor-mentee). */
    public function claims(Request $request, Audit $audit, ScoreService $scores)
    {
        $this->editable($audit);
        $data = $request->validate(['claim' => 'array', 'claim.*' => 'nullable|numeric|min:0']);
        foreach ($data['claim'] ?? [] as $code => $marks) {
            $scores->setManualClaim($audit, $code, $marks === null ? null : (float) $marks);
        }
        return back()->with('status', 'Claimed marks saved.');
    }

    public function verify(Request $request, Audit $audit)
    {
        $this->viewable($audit);
        abort_unless($this->access()->canVerify($audit), 403);
        $data = $request->validate([
            'status' => 'array', 'status.*' => 'nullable|in:verified,returned',
            'remark' => 'array', 'remark.*' => 'nullable|string|max:1000',
        ]);
        foreach ($audit->cycle->parameters as $p) {
            Score::updateOrCreate(['audit_id' => $audit->id, 'parameter_code' => $p->code], [
                'iqac_status' => $data['status'][$p->code] ?? null,
                'iqac_remark' => $data['remark'][$p->code] ?? null,
            ]);
        }
        $audit->log('iqac_review', 'Parameter-wise verification saved');
        return back()->with('status', 'Verification saved.');
    }

    public function marks(Request $request, Audit $audit)
    {
        $this->viewable($audit);
        abort_unless($this->access()->canMark($audit), 403);
        $slot = $this->access()->auditorSlot($audit);
        abort_if($audit->{"auditor{$slot}_done_at"}, 422, 'You have already finalised your marks.');
        $data = $request->validate([
            'marks' => 'array', 'marks.*' => 'nullable|numeric|min:0',
            'remark' => 'array', 'remark.*' => 'nullable|string|max:1000',
        ]);
        foreach ($audit->cycle->parameters as $p) {
            $m = $data['marks'][$p->code] ?? null;
            Score::updateOrCreate(['audit_id' => $audit->id, 'parameter_code' => $p->code], [
                "auditor{$slot}_marks" => $m === null ? null : min((float) $m, $p->max_marks),
                "auditor{$slot}_remark" => $data['remark'][$p->code] ?? null,
            ]);
        }
        return back()->with('status', 'Marks saved. Use "Finalise my marks" when complete.');
    }

    public function auditors(Request $request, Audit $audit)
    {
        $this->iqac();
        $auditorIds = UserRole::where('role', UserRole::AUDITOR)->pluck('user_id')->all();
        $data = $request->validate([
            'auditor1_user_id' => ['nullable', 'in:'.implode(',', $auditorIds ?: [0])],
            'auditor2_user_id' => ['nullable', 'in:'.implode(',', $auditorIds ?: [0]), 'different:auditor1_user_id'],
        ]);
        $audit->update($data);
        $audit->log('auditors', 'Auditors assigned');
        return back()->with('status', 'Auditors assigned.');
    }
}
