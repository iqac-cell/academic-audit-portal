<?php

namespace Mmmut\AcademicAudit\Support;

/**
 * Every portal setting IQAC can change from Administration → Settings.
 * New features ship disabled; existing behaviour keeps its current default.
 *
 * type: text | textarea | number | bool | select | password | email
 */
class SettingsRegistry
{
    public const GROUPS = [
        'general' => ['General', 'bi-sliders'],
        'workflow' => ['Workflow & uploads', 'bi-diagram-3'],
        'mail' => ['Email (SMTP)', 'bi-envelope'],
        'security' => ['Login & password', 'bi-shield-lock'],
        'notify' => ['Notifications', 'bi-bell'],
        'ai' => ['AI assistance', 'bi-stars'],
    ];

    public static function all(): array
    {
        return [
            // General
            'general.portal_title' => ['general', 'Portal title', 'text', 'Internal Academic Audit'],
            'general.university' => ['general', 'University name', 'text', 'Madan Mohan Malaviya University of Technology, Gorakhpur'],
            'general.contact_email' => ['general', 'IQAC contact email (shown on login page)', 'email', ''],
            'general.login_notice' => ['general', 'Notice shown on login page', 'textarea', ''],

            // Workflow
            'workflow.faculty_contributions' => ['workflow', 'Faculty can add their own records (HOD approves)', 'bool', true],
            'workflow.proof_mode' => ['workflow', 'Proof document per row', 'select', 'none', ['none' => 'Optional', 'warn' => 'Warn when missing', 'require' => 'Required']],
            'workflow.max_upload_mb' => ['workflow', 'Maximum upload size (MB)', 'number', 10],
            'workflow.upload_types' => ['workflow', 'Allowed file types (comma separated)', 'text', 'pdf,xls,xlsx,csv,doc,docx,jpg,jpeg,png,zip'],
            'workflow.auto_close' => ['workflow', 'Close the cycle automatically after its closing date (audit:remind task)', 'bool', false],

            // Mail
            'mail.enabled' => ['mail', 'Send emails from the portal', 'bool', false],
            'mail.host' => ['mail', 'SMTP host', 'text', 'smtp.gmail.com', null, 'University SMTP server, or smtp.gmail.com for Gmail / Google Workspace.'],
            'mail.port' => ['mail', 'SMTP port', 'number', 587],
            'mail.encryption' => ['mail', 'Encryption', 'select', 'tls', ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None']],
            'mail.username' => ['mail', 'SMTP username', 'text', ''],
            'mail.password' => ['mail', 'SMTP password', 'password', '', null, 'For Gmail use an App Password (Google account → Security → App passwords).'],
            'mail.from_address' => ['mail', 'From address', 'email', ''],
            'mail.from_name' => ['mail', 'From name', 'text', 'IQAC, MMMUT'],

            // Security
            'security.otp_reset' => ['security', 'Allow "Forgot password" with email OTP (needs email enabled)', 'bool', false],
            'security.otp_length' => ['security', 'OTP digits', 'number', 6],
            'security.otp_expiry' => ['security', 'OTP valid for (minutes)', 'number', 10],
            'security.otp_attempts' => ['security', 'Wrong OTP attempts allowed', 'number', 5],
            'security.password_min' => ['security', 'Minimum password length', 'number', 8],
            'security.force_change' => ['security', 'New accounts must change password at first login', 'bool', true],
            'security.session_minutes' => ['security', 'Log out after inactivity (minutes)', 'number', 120, null, 'After this time without any activity the user is sent back to the login page.'],

            // Notifications
            'notify.enabled' => ['notify', 'Send email notifications (needs email enabled)', 'bool', false],
            'notify.submitted' => ['notify', 'Audit submitted → IQAC', 'bool', true],
            'notify.returned' => ['notify', 'Audit returned → HOD', 'bool', true],
            'notify.verified' => ['notify', 'Audit verified → HOD and auditors', 'bool', true],
            'notify.auditor_assigned' => ['notify', 'Auditor assigned → auditor', 'bool', true],
            'notify.entry_reviewed' => ['notify', 'Faculty row approved / rejected → faculty', 'bool', true],
            'notify.reminder_days' => ['notify', 'Deadline reminders to HODs, days before closing (comma separated)', 'text', '7,2'],

            // AI
            'ai.enabled' => ['ai', 'Enable AI assistance (master switch)', 'bool', false],
            'ai.quality_checks' => ['ai', 'Data quality checks (offline, rule-based)', 'bool', false],
            'ai.suggestions' => ['ai', 'Suggestions to improve marks (offline, rule-based)', 'bool', false],
            'ai.prev_year_prefill' => ['ai', 'Prefill from previous academic year', 'bool', false],
            'ai.document_autofill' => ['ai', 'Fill a row from an uploaded PDF / certificate (Claude API, online)', 'bool', false],
            'ai.api_key' => ['ai', 'Anthropic (Claude) API key', 'password', '', null, 'Create at console.anthropic.com. Documents uploaded for auto-fill are sent to Anthropic for processing.'],
            'ai.model' => ['ai', 'Claude model', 'select', 'claude-opus-5-5', ['claude-opus-5-5' => 'Claude Opus 5.5 (most accurate)', 'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (balanced)', 'claude-haiku-5-5' => 'Claude Haiku 5.5 (lowest cost)']],
            'ai.proxy' => ['ai', 'HTTPS proxy for outbound calls (optional)', 'text', '', null, 'e.g. http://proxy.mmmut.ac.in:3128 — leave blank for direct internet.'],
            'ai.daily_limit' => ['ai', 'Document auto-fills per user per day', 'number', 30],
        ];
    }

    /** Normalised definition: group, label, type, default, options, help, secret. */
    public static function get(string $key): ?array
    {
        $d = self::all()[$key] ?? null;
        if (! $d) {
            return null;
        }
        return [
            'key' => $key,
            'group' => $d[0],
            'label' => $d[1],
            'type' => $d[2],
            'default' => $d[3],
            'options' => $d[4] ?? null,
            'help' => $d[5] ?? null,
            'secret' => $d[2] === 'password',
        ];
    }

    public static function group(string $group): array
    {
        return array_values(array_filter(array_map([self::class, 'get'], array_keys(self::all())), fn ($d) => $d['group'] === $group));
    }
}
