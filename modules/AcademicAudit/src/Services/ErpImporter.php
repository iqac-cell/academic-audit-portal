<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\DepartmentAlias;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Support\DepartmentList;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

/**
 * Imports teaching staff from the Samarth ERP employee export.
 * Matches existing faculty by official email, else by name + date of birth (idempotent).
 */
class ErpImporter
{
    private const HEADERS = [
        'cadre' => 'Service Cadre',
        'dept' => 'Department / Section Office Name',
        'designation' => 'Designation',
        'nature' => 'Nature of Employment',
        'doj' => 'Date of Joining',
        'dor' => 'Date of Retired',
        'qualification' => 'Qualification At The Time of Appointment',
        'code' => 'Existing Employee Code',
        'salutation' => 'Salutation',
        'first' => 'First Name',
        'middle' => 'Middle Name',
        'last' => 'LastName',
        'dob' => 'Date of Birth',
        'mobile' => 'Mobile Number',
        'email' => 'Official Email',
        'personal_email' => 'Personal Email',
    ];

    public array $report = ['created' => 0, 'updated' => 0, 'users_created' => 0, 'skipped_non_teaching' => 0, 'unmatched_departments' => [], 'missing_email' => [], 'duplicate_email' => []];

    public function import(string $path, bool $createUsers = true): array
    {
        $sheet = IOFactory::load($path)->getSheet(0);
        $rows = $sheet->toArray(null, true, false, false);

        [$headerRow, $map] = $this->locateHeader($rows);
        $aliases = $this->aliasIndex();

        DB::transaction(function () use ($rows, $headerRow, $map, $aliases, $createUsers) {
            $seenEmails = [];
            foreach (array_slice($rows, $headerRow + 1) as $r) {
                $get = fn ($k) => isset($map[$k]) ? trim(preg_replace('/\s+/', ' ', (string) ($r[$map[$k]] ?? ''))) : '';
                if ($get('first') === '' || is_numeric($get('first'))) {
                    continue; // blank or the "1 2 3 ..." numbering row
                }
                if (mb_strtolower($get('cadre')) !== 'teaching') {
                    $this->report['skipped_non_teaching']++;
                    continue;
                }
                $deptName = $get('dept');
                $deptId = $aliases[DepartmentList::key($deptName)] ?? null;
                if (! $deptId) {
                    $this->report['unmatched_departments'][$deptName] = ($this->report['unmatched_departments'][$deptName] ?? 0) + 1;
                }
                $name = trim(implode(' ', array_filter([$get('first'), $get('middle'), $get('last')])));
                $email = mb_strtolower($get('email')) ?: null;
                $dob = $this->date($r[$map['dob']] ?? null);

                $byEmail = $email ? Faculty::where('email', $email)->first() : null;
                if ($byEmail && $byEmail->name !== $name && ! in_array($email, $seenEmails[$name] ?? [])) {
                    // Same official email recorded for two different people in ERP: keep both, no login for the second.
                    $this->report['duplicate_email'][] = "{$email} ({$byEmail->name} / {$name})";
                    $byEmail = null;
                    $email = null;
                }
                $faculty = $byEmail
                    ?? Faculty::where('name', $name)->when($dob, fn ($q) => $q->whereDate('date_of_birth', $dob))->first()
                    ?? new Faculty;
                if ($email) {
                    $seenEmails[$name][] = $email;
                }
                $faculty->fill([
                    'department_id' => $deptId ?? $faculty->department_id,
                    'employee_code' => $get('code') ?: $faculty->employee_code,
                    'salutation' => $get('salutation') ?: null,
                    'name' => $name,
                    'designation' => $get('designation') ?: null,
                    'qualification' => $get('qualification') ?: null,
                    'nature' => $get('nature') ?: null,
                    'date_of_joining' => $this->date($r[$map['doj']] ?? null),
                    'date_of_retirement' => $this->date($r[$map['dor']] ?? null),
                    'date_of_birth' => $dob,
                    'email' => $email ?? $faculty->email,
                    'mobile' => $get('mobile') ?: null,
                    'is_regular' => in_array(mb_strtolower($get('nature')), ['permanent', 'probation period', 'regular', '']),
                    'is_active' => true,
                    'source' => 'erp',
                ]);
                $this->report[$faculty->exists ? 'updated' : 'created']++;
                $faculty->save();

                if (! $email && ! $faculty->email) {
                    $this->report['missing_email'][] = $name;
                } elseif ($createUsers && $email) {
                    $this->linkUser($faculty);
                }
            }
        });

        return $this->report;
    }

    private function linkUser(Faculty $faculty): void
    {
        $model = config('academic-audit.user_model');
        $user = $model::where('email', $faculty->email)->first();
        if (! $user) {
            $user = new $model;
            $user->forceFill([
                'name' => $faculty->display_name,
                'email' => $faculty->email,
                'password' => Hash::make(config('academic-audit.default_password')),
            ]);
            if (\Illuminate\Support\Facades\Schema::hasColumn($user->getTable(), 'must_change_password')) {
                $user->forceFill(['must_change_password' => true]);
            }
            $user->save();
            $this->report['users_created']++;
        }
        $faculty->update(['user_id' => $user->id]);
        UserRole::firstOrCreate(['user_id' => $user->id, 'role' => UserRole::FACULTY, 'department_id' => $faculty->department_id]);
    }

    private function locateHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 20, true) as $i => $row) {
            $cells = array_map(fn ($c) => trim((string) $c), $row);
            if (in_array('First Name', $cells, true)) {
                $map = [];
                foreach (self::HEADERS as $key => $label) {
                    $idx = array_search($label, $cells, true);
                    if ($idx !== false) {
                        $map[$key] = $idx;
                    }
                }
                foreach (['cadre', 'dept', 'first'] as $required) {
                    if (! isset($map[$required])) {
                        throw new \RuntimeException("Column '".self::HEADERS[$required]."' not found in ERP sheet.");
                    }
                }
                return [$i, $map];
            }
        }
        throw new \RuntimeException('Header row with "First Name" not found in the first 20 rows.');
    }

    private function aliasIndex(): array
    {
        $idx = [];
        foreach (Department::all() as $d) {
            $idx[DepartmentList::key($d->name)] = $d->id;
        }
        foreach (DepartmentAlias::all() as $a) {
            $idx[DepartmentList::key($a->alias)] = $a->department_id;
        }
        return $idx;
    }

    private function date(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return XlsDate::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        $v = trim((string) $v);
        foreach (['d.m.Y', 'd-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.y'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $v);
            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }
}
