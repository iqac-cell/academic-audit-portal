<?php

namespace Mmmut\AcademicAudit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Mmmut\AcademicAudit\Models\Cycle;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Providers\QSphereDirectoryProvider;

/** Dry run of ACADEMIC_AUDIT_DIRECTORY=qsphere: shows how Q-Sphere departments and faculty would map. Changes nothing. */
class DirectoryCheckCommand extends Command
{
    protected $signature = 'audit:directory-check';
    protected $description = 'Show how Q-Sphere departments and faculty map to the audit departments (read-only)';

    public function handle(): int
    {
        $connection = config('academic-audit.qsphere.connection');
        try {
            DB::connection($connection)->table('sdg_departments')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->error("Q-Sphere cannot be read over connection '{$connection}': ".$e->getMessage());
            $this->line('Check QSPHERE_DB_* in .env and that audit_reader has SELECT on sdg_departments and sdg_users.');
            return self::FAILURE;
        }

        $provider = new QSphereDirectoryProvider(app(\Mmmut\AcademicAudit\Providers\LocalDirectoryProvider::class));
        $codes = $provider->departmentCodes() ?? [];
        $cycle = Cycle::orderByDesc('academic_year')->first();
        $this->line('Directory now in use: '.class_basename(config('academic-audit.directory_provider')));

        $rows = [];
        foreach (Department::where('is_academic', true)->orderBy('name')->get() as $dept) {
            $deptCodes = array_keys($codes, $dept->id, true);
            $qs = $deptCodes ? $provider->faculty($dept) : collect();
            $rows[] = [
                $dept->short_name,
                $dept->name,
                $deptCodes ? implode(', ', $deptCodes) : 'NOT IN Q-SPHERE',
                $qs->count(),
                $qs->filter(fn ($f) => $f->exists)->count(),
                $deptCodes && $cycle ? $provider->regularFacultyCount($dept, $cycle) : '-',
                Faculty::where('department_id', $dept->id)->where('is_active', true)->count(),
            ];
        }
        $this->table(['Audit dept', 'Name', 'Q-Sphere code', 'Faculty from Q-Sphere', 'with local record', 'Regular ('.($cycle->academic_year ?? '-').')', 'Faculty now (local)'], $rows);

        $unmapped = $provider->unmappedDepartments();
        if ($unmapped) {
            $this->warn('Unmapped Q-Sphere departments (ignored; no audit department, nothing is created):');
            $this->table(['Q-Sphere code', 'Name', 'Type'], collect($unmapped)->map(fn ($d, $code) => [$code, $d['name'], $d['type']])->values()->all());
        } else {
            $this->info('Every active Q-Sphere department maps to an audit department.');
        }

        $missing = collect($rows)->filter(fn ($r) => $r[2] === 'NOT IN Q-SPHERE')->pluck(0);
        if ($missing->isNotEmpty()) {
            $this->warn('Audit departments without a Q-Sphere code get no audit and no faculty with the qsphere directory: '.$missing->implode(', '));
        }
        $this->line('Nothing was changed. To switch: ACADEMIC_AUDIT_DIRECTORY=qsphere in .env, then php artisan config:cache.');

        return self::SUCCESS;
    }
}
