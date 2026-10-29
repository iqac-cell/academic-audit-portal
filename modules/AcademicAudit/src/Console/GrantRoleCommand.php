<?php

namespace Mmmut\AcademicAudit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\UserRole;

class GrantRoleCommand extends Command
{
    protected $signature = 'audit:grant {email} {role : iqac|hod|faculty|auditor} {--department= : Department name or short name (for hod/faculty)} {--name= : Name, when creating the user} {--password= : Password, when creating the user}';
    protected $description = 'Give a user a role in the academic audit portal (creates the user if needed)';

    public function handle(): int
    {
        $role = $this->argument('role');
        if (! array_key_exists($role, UserRole::LABELS)) {
            $this->error('Role must be one of: '.implode(', ', array_keys(UserRole::LABELS)));
            return self::FAILURE;
        }
        $dept = null;
        if ($d = $this->option('department')) {
            $dept = Department::where('name', $d)->orWhere('short_name', $d)->first();
            if (! $dept) {
                $this->error("Department '{$d}' not found.");
                return self::FAILURE;
            }
        }
        if (in_array($role, [UserRole::HOD, UserRole::FACULTY]) && ! $dept) {
            $this->error('--department is required for this role.');
            return self::FAILURE;
        }

        $model = config('academic-audit.user_model');
        $user = $model::where('email', $this->argument('email'))->first();
        if (! $user) {
            $user = new $model;
            $user->forceFill([
                'name' => $this->option('name') ?: $this->argument('email'),
                'email' => $this->argument('email'),
                'password' => Hash::make($this->option('password') ?: config('academic-audit.default_password')),
            ])->save();
            $this->info('User created.');
        }
        UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $role, 'department_id' => $dept?->id]);
        $this->info("{$user->email} is now ".UserRole::LABELS[$role].($dept ? " ({$dept->name})" : '').'.');
        return self::SUCCESS;
    }
}
