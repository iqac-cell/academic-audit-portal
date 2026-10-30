<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Services\ScoreService;

class AuditController extends Controller
{
    public function show(Audit $audit, ScoreService $scores, \Mmmut\AcademicAudit\Services\SettingsService $settings,
        \Mmmut\AcademicAudit\Services\Ai\DataQualityChecker $quality, \Mmmut\AcademicAudit\Services\Ai\ScoreAdvisor $advisor)
    {
        $this->viewable($audit);
        $eval = $scores->evaluate($audit);
        $pending = Entry::where('audit_id', $audit->id)->where('status', Entry::PENDING)->get()->groupBy('parameter_code')->map->count();

        return $this->view('audit.show', [
            'audit' => $audit,
            'eval' => $eval,
            'summary' => $scores->summary($audit, $eval),
            'pending' => $pending,
            'attachments' => $audit->attachments()->get()->groupBy('slot'),
            'logs' => $audit->logs()->with('user')->limit(15)->get(),
            'issues' => $settings->enabled('ai.quality_checks') && ! $this->access()->isFaculty() ? $quality->check($audit) : null,
            'suggestions' => $settings->enabled('ai.suggestions') && ! $this->access()->isAuditor() ? $advisor->suggestions($audit, $eval) : null,
        ]);
    }
}
