<?php

return [
    // URL prefix of the module. Set ACADEMIC_AUDIT_PREFIX= (empty) when the whole site
    // already lives under a sub-path such as https://sdg.mmmut.ac.in/academic-audit.
    'route_prefix' => env('ACADEMIC_AUDIT_PREFIX', 'academic-audit'),

    // Middleware applied to all module routes. When merged into the IQAC project,
    // switch these to the host app's guard / middleware.
    'middleware' => ['web', 'auth'],

    // Layout the module views extend. Point this at the IQAC layout after merging
    // (it must yield 'content' and stack 'scripts').
    'layout' => 'audit::layouts.app',

    // Eloquent model of the host application's users (needs id, name, email).
    'user_model' => env('ACADEMIC_AUDIT_USER_MODEL', \App\Models\User::class),

    // Where department / faculty master data comes from: ACADEMIC_AUDIT_DIRECTORY=local (aa_ tables filled by the
    // ERP Excel import) or qsphere (Q-Sphere views over the read-only "qsphere" connection, local data as fallback).
    'directory_provider' => match (env('ACADEMIC_AUDIT_DIRECTORY', 'local')) {
        'qsphere' => \Mmmut\AcademicAudit\Providers\QSphereDirectoryProvider::class,
        default => \Mmmut\AcademicAudit\Providers\LocalDirectoryProvider::class,
    },

    'qsphere' => [
        'connection' => env('ACADEMIC_AUDIT_QSPHERE_CONNECTION', 'qsphere'),
        // Only these designations count as faculty (when Q-Sphere provides a designation); empty = everyone.
        'faculty_designations' => env('ACADEMIC_AUDIT_FACULTY_DESIGNATIONS', '/professor|lecturer|teaching|faculty/i'),
    ],

    // Source for pre-filling audit tables. Replace with IqacPrefillProvider after merge.
    'prefill_provider' => \Mmmut\AcademicAudit\Providers\NullPrefillProvider::class,

    // Reads proof documents for "Fill from document" (AI). Swap for a fake in tests.
    'document_extractor' => \Mmmut\AcademicAudit\Services\Ai\ClaudeDocumentExtractor::class,

    // Disk + folder for proofs and annexures. Upload size / types are set in Admin → Settings.
    'disk' => env('ACADEMIC_AUDIT_DISK', 'local'),
    'upload_dir' => 'academic-audit',
    'max_upload_kb' => 10240,
    'upload_mimes' => 'pdf,xls,xlsx,csv,doc,docx,jpg,jpeg,png,zip',

    'annexures' => [
        'I' => 'Annexure I – Detailed student information (enrolment)',
        'II' => 'Annexure II – Mentor-mentee records',
        'III' => 'Annexure III – Internship / field / research project details',
        'IV' => 'Annexure IV – Attainment of PO-CO',
        'V' => 'Annexure V – POs and PSOs attainment level and actions for improvement',
    ],

    // No shared default password: new accounts get a random one (see Support\Passwords).
    // A server-specific old default, only so that audit:default-password-check can look for it.
    'legacy_default_password' => env('ACADEMIC_AUDIT_DEFAULT_PASSWORD'),
];
