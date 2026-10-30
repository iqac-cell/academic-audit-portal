<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mmmut\AcademicAudit\Support\SettingsRegistry;

/** Portal settings stored in aa_settings (secrets encrypted), cached, with registry defaults. */
class SettingsService
{
    private const CACHE_KEY = 'aa_settings_v1';
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $def = SettingsRegistry::get($key);
        $values = $this->load();
        if (! array_key_exists($key, $values)) {
            return $def['default'] ?? $default;
        }
        return $this->cast($def, $values[$key]);
    }

    public function enabled(string $key): bool
    {
        if (str_starts_with($key, 'ai.') && $key !== 'ai.enabled' && ! $this->get('ai.enabled')) {
            return false;
        }
        if (str_starts_with($key, 'notify.') && $key !== 'notify.enabled' && ! ($this->get('notify.enabled') && $this->get('mail.enabled'))) {
            return false;
        }
        if ($key === 'security.otp_reset' && ! $this->get('mail.enabled')) {
            return false;
        }
        return (bool) $this->get($key);
    }

    /** Has a secret been saved (without revealing it)? */
    public function isSet(string $key): bool
    {
        return filled($this->load()[$key] ?? null);
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $def = SettingsRegistry::get($key);
        if (! $def) {
            throw new \InvalidArgumentException("Unknown setting {$key}");
        }
        $stored = $def['type'] === 'bool' ? ($value ? '1' : '0') : (string) ($value ?? '');
        if ($def['secret'] && $stored !== '') {
            $stored = Crypt::encryptString($stored);
        }
        DB::table('aa_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $stored, 'is_encrypted' => $def['secret'] && $stored !== '', 'updated_by' => $userId, 'updated_at' => now()],
        );
        $this->flush();
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function load(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }
        try {
            return $this->values = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('aa_settings')) {
                    return [];
                }
                return DB::table('aa_settings')->get()->mapWithKeys(function ($row) {
                    $value = $row->value;
                    if ($row->is_encrypted && $value !== null && $value !== '') {
                        try {
                            $value = Crypt::decryptString($value);
                        } catch (\Throwable) {
                            $value = ''; // APP_KEY changed: secret must be entered again
                        }
                    }
                    return [$row->key => $value];
                })->all();
            });
        } catch (\Throwable) {
            return $this->values = []; // DB not reachable yet (e.g. during install)
        }
    }

    private function cast(?array $def, mixed $value): mixed
    {
        return match ($def['type'] ?? 'text') {
            'bool' => $value === '1' || $value === 1 || $value === true,
            'number' => is_numeric($value) ? $value + 0 : ($def['default'] ?? 0),
            default => $value,
        };
    }

    /** Push settings into Laravel config (mail transport, upload limits). Called at boot. */
    public function applyToConfig(): void
    {
        config([
            'academic-audit.max_upload_kb' => (int) $this->get('workflow.max_upload_mb') * 1024,
            'academic-audit.upload_mimes' => preg_replace('/[^a-z0-9,]/', '', strtolower((string) $this->get('workflow.upload_types'))),
        ]);
        if ($this->get('mail.enabled') && filled($this->get('mail.host'))) {
            $enc = $this->get('mail.encryption');
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $this->get('mail.host'),
                'mail.mailers.smtp.port' => (int) $this->get('mail.port'),
                'mail.mailers.smtp.scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => $this->get('mail.username') ?: null,
                'mail.mailers.smtp.password' => $this->get('mail.password') ?: null,
                'mail.from.address' => $this->get('mail.from_address') ?: $this->get('mail.username'),
                'mail.from.name' => $this->get('mail.from_name'),
            ]);
        }
    }
}
