<?php

namespace Mmmut\AcademicAudit\Console;

use Illuminate\Console\Command;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Services\CycleService;

class NewCycleCommand extends Command
{
    protected $signature = 'audit:new-cycle {year : Academic year, e.g. 2026-27}';
    protected $description = 'Open a new audit cycle (copies marks/slabs from the previous cycle) and create department audits';

    public function handle(CycleService $cycles): int
    {
        if (Cycle::where('academic_year', $this->argument('year'))->exists()) {
            $this->error('Cycle already exists.');
            return self::FAILURE;
        }
        $cycle = $cycles->create($this->argument('year'));
        $this->info("Cycle {$cycle->academic_year} created with {$cycle->parameters()->count()} parameters and {$cycle->audits()->count()} department audits.");
        return self::SUCCESS;
    }
}
