<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Mmmut\AcademicAudit\Database\Seeders\AcademicAuditSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AcademicAuditSeeder::class);

        // First IQAC administrator (password must be changed after first login).
        if ($email = env('IQAC_ADMIN_EMAIL')) {
            Artisan::call('audit:grant', [
                'email' => $email,
                'role' => 'iqac',
                '--name' => env('IQAC_ADMIN_NAME', 'IQAC Administrator'),
                '--password' => env('IQAC_ADMIN_PASSWORD') ?: null,
            ]);
            \App\Models\User::where('email', $email)->update(['must_change_password' => true]);
        }
    }
}
