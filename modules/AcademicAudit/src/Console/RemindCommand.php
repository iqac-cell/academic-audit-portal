<?php

namespace Mmmut\AcademicAudit\Console;

use Illuminate\Console\Command;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Services\Notifier;
use Mmmut\AcademicAudit\Services\SettingsService;

/** Daily task: deadline reminders to HODs and optional automatic closing of the cycle. */
class RemindCommand extends Command
{
    protected $signature = 'audit:remind';
    protected $description = 'Send deadline reminders to HODs and auto-close cycles past their closing date (run daily)';

    public function handle(Notifier $notifier, SettingsService $settings): int
    {
        $days = array_filter(array_map('intval', explode(',', (string) $settings->get('notify.reminder_days'))));
        foreach (Cycle::where('status', 'open')->whereNotNull('closes_on')->get() as $cycle) {
            $left = (int) now()->startOfDay()->diffInDays($cycle->closes_on->copy()->startOfDay(), false);
            if ($left < 0 && $settings->get('workflow.auto_close')) {
                $cycle->update(['status' => 'closed']);
                $this->info("Cycle {$cycle->academic_year} closed.");
                continue;
            }
            if (! in_array($left, $days, true)) {
                continue;
            }
            $sent = 0;
            foreach ($cycle->audits()->with('department', 'cycle')->whereIn('status', [Audit::DRAFT, Audit::RETURNED])->get() as $audit) {
                $sent += $notifier->reminder($audit, $left) ? 1 : 0;
            }
            $this->info("Cycle {$cycle->academic_year}: {$left} day(s) left, {$sent} reminder(s) sent.");
        }
        return self::SUCCESS;
    }
}
