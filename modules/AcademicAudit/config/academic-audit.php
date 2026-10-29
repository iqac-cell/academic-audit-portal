<?php

return [
    // URL prefix and route-name prefix of the module.
    'route_prefix' => env('ACADEMIC_AUDIT_PREFIX', 'academic-audit'),

    // Middleware applied to all module routes. When merged into the IQAC project,
    // switch these to the host app's guard / middleware.
    'middleware' => ['web', 'auth'],

    // Layout the module views extend. Point this at the IQAC layout after merging
    // (it must yield 'content' and stack 'scripts').
    'layout' => 'audit::layouts.app',

    // Eloquent model of the host application's users (needs id, name, email).
    'user_model' => env('ACADEMIC_AUDIT_USER_MODEL', \App\Models\User::class),

    // Where department / faculty master data comes from.
    'directory_provider' => \Mmmut\AcademicAudit\Providers\LocalDirectoryProvider::class,

    // Source for pre-filling audit tables. Replace with IqacPrefillProvider after merge.
    'prefill_provider' => \Mmmut\AcademicAudit\Providers\NullPrefillProvider::class,

    // Disk + folder for proofs and annexures.
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

    // Default password for accounts created by audit:import-erp (forced change on first login).
    'default_password' => env('ACADEMIC_AUDIT_DEFAULT_PASSWORD', 'Mmmut@123'),
];
