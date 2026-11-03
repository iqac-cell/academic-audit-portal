<?php

namespace Mmmut\AcademicAudit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Support\Passwords;

/** Read-only: counts accounts whose password is still the old shared default. Changes nothing. */
class DefaultPasswordCheckCommand extends Command
{
    protected $signature = 'audit:default-password-check {--list : Also print the email addresses}';
    protected $description = 'Count accounts that still use the old shared default password (read-only)';

    public function handle(): int
    {
        // The server may have set its own default through ACADEMIC_AUDIT_DEFAULT_PASSWORD; check that one too.
        $candidates = array_values(array_unique(array_filter([Passwords::LEGACY_DEFAULT, config('academic-audit.legacy_default_password')])));
        $model = config('academic-audit.user_model');
        $total = $model::count();
        $found = [];

        $this->info("Checking {$total} accounts (this takes a few seconds per hundred)...");
        $bar = $this->output->createProgressBar($total);
        $model::query()->select(['id', 'email', 'password'])->orderBy('id')->chunkById(200, function ($users) use ($candidates, &$found, $bar) {
            foreach ($users as $u) {
                foreach ($candidates as $p) {
                    if ($u->password && Hash::check($p, $u->password)) {
                        $found[] = $u->email;
                        break;
                    }
                }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine(2);

        $this->line(count($found)." of {$total} accounts still have the old default password.");
        if ($this->option('list') && $found) {
            foreach ($found as $email) {
                $this->line('  '.$email);
            }
        }
        if ($found) {
            $this->line('Nothing was changed. With central login on, these people sign in through Q-Sphere; the IQAC can give a');
            $this->line('local user a new temporary password with "Reset password" in Administration → Users & roles.');
        }

        return self::SUCCESS;
    }
}
