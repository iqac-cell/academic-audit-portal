<?php

namespace Mmmut\AcademicAudit\Console;

use Illuminate\Console\Command;
use Mmmut\AcademicAudit\Services\ErpImporter;

class ImportErpCommand extends Command
{
    protected $signature = 'audit:import-erp {file : Samarth ERP employee export (.xlsx)} {--no-users : Do not create login accounts}';
    protected $description = 'Import teaching faculty from the Samarth ERP employee export';

    public function handle(ErpImporter $importer): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }
        $r = $importer->import($file, ! $this->option('no-users'));

        $this->table(['Result', 'Count'], [
            ['Faculty created', $r['created']],
            ['Faculty updated', $r['updated']],
            ['Login accounts created', $r['users_created']],
            ['Non-teaching rows skipped', $r['skipped_non_teaching']],
        ]);
        if ($r['unmatched_departments']) {
            $this->warn('Departments not matched (add an alias in Admin → Departments, then re-run):');
            foreach ($r['unmatched_departments'] as $name => $n) {
                $this->line("  - {$name} ({$n})");
            }
        }
        if ($r['duplicate_email']) {
            $this->warn('Same official email used by two different people in ERP (second person imported without login — correct the email in Admin → Faculty):');
            foreach ($r['duplicate_email'] as $d) {
                $this->line("  - {$d}");
            }
        }
        if ($r['missing_email']) {
            $this->warn('No official email (no login created): '.implode(', ', $r['missing_email']));
        }
        return self::SUCCESS;
    }
}
