<?php

namespace Mmmut\AcademicAudit\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\DepartmentAlias;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Models\UserRole;
use Mmmut\AcademicAudit\Notifications\AuditMail;
use Mmmut\AcademicAudit\Support\DepartmentList;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Creates logins and roles in bulk from an .xlsx sheet (Name, Email, Roles, Department, Designation, Mobile).
 * Also used by the single "Assign a role" form, so both paths behave the same.
 */
class UserImporter
{
    public const HEADERS = ['Name', 'Email', 'Roles', 'Department', 'Designation', 'Mobile'];
    public const MAX_ROWS = 1000;

    private const ROLE_WORDS = [
        'faculty' => UserRole::FACULTY, 'teacher' => UserRole::FACULTY,
        'hod' => UserRole::HOD, 'head' => UserRole::HOD, 'headofdepartment' => UserRole::HOD,
        'iqac' => UserRole::IQAC, 'admin' => UserRole::IQAC,
        'auditor' => UserRole::AUDITOR, 'academicauditor' => UserRole::AUDITOR,
    ];

    public function __construct(private SettingsService $settings) {}

    public function template(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Users');
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->fromArray(['Dr. Asha Verma', 'asha.cse@mmmut.ac.in', 'Faculty, HOD', 'CSE', 'Professor', '9999999999'], null, 'A2');
        foreach (range(1, 6) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setWidth([28, 32, 22, 36, 18, 14][$i - 1]);
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A2:F2')->getFont()->setItalic(true)->getColor()->setRGB('777777');

        $help = $book->createSheet()->setTitle('Help');
        $help->fromArray([['Column', 'What to write'],
            ['Name', 'Full name (required)'],
            ['Email', 'Official email, used as login (required)'],
            ['Roles', 'One or more of: Faculty, HOD, IQAC, Auditor — separate with commas (required)'],
            ['Department', 'Required for Faculty and HOD. Use the name or short name from the list below'],
            ['Designation / Mobile', 'Optional'],
            ['', 'Row 2 of the Users sheet is an example — replace or delete it.'],
            ['', ''],
            ['Departments', 'Short name']], null, 'A1');
        $r = 10;
        foreach (Department::orderBy('name')->get() as $d) {
            $help->fromArray([$d->name, $d->short_name], null, "A{$r}");
            $r++;
        }
        $help->getColumnDimension('A')->setWidth(48);
        $help->getColumnDimension('B')->setWidth(80);
        $help->getStyle('A1:B1')->getFont()->setBold(true);
        $help->getStyle('A9:B9')->getFont()->setBold(true);
        $book->setActiveSheetIndex(0);
        return $book;
    }

    /**
     * Reads the sheet and checks every row without changing anything.
     *
     * @return array<int, array{row: int, name: string, email: string, roles: array, department_id: ?int, department: ?string, designation: ?string, mobile: ?string, status: string, messages: array}>
     */
    public function preview(string $path): array
    {
        $rows = IOFactory::load($path)->getSheet(0)->toArray(null, true, true, false);
        $header = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $rows[0] ?? []);
        $col = [];
        foreach (self::HEADERS as $h) {
            $i = array_search(mb_strtolower($h), $header, true);
            $col[$h] = $i === false ? null : $i;
        }
        if ($col['Name'] === null || $col['Email'] === null || $col['Roles'] === null) {
            throw new \RuntimeException('The first row must have the headings Name, Email and Roles (download the template).');
        }

        $deptIndex = $this->departmentIndex();
        $model = config('academic-audit.user_model');
        $out = [];
        $seen = [];
        foreach (array_slice($rows, 1, self::MAX_ROWS + 1, true) as $i => $cells) {
            $get = fn ($h) => $col[$h] === null ? '' : trim(preg_replace('/\s+/', ' ', (string) ($cells[$col[$h]] ?? '')));
            if ($get('Name') === '' && $get('Email') === '' && $get('Roles') === '') {
                continue;
            }
            if (count($out) >= self::MAX_ROWS) {
                throw new \RuntimeException('At most '.self::MAX_ROWS.' users can be imported at a time.');
            }
            $email = mb_strtolower($get('Email'));
            $row = ['row' => $i + 1, 'name' => $get('Name'), 'email' => $email, 'roles' => [], 'department_id' => null,
                'department' => $get('Department') ?: null, 'designation' => $get('Designation') ?: null, 'mobile' => $get('Mobile') ?: null,
                'status' => 'new', 'messages' => []];
            if ($email === 'asha.cse@mmmut.ac.in' && $row['name'] === 'Dr. Asha Verma') {
                continue; // example row of the template
            }

            if ($row['name'] === '') {
                $row['messages'][] = 'Name is missing.';
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $row['messages'][] = 'Email is missing or not valid.';
            } elseif (isset($seen[$email])) {
                $row['messages'][] = "Same email as row {$seen[$email]}.";
            }
            $seen[$email] ??= $row['row'];

            foreach (preg_split('/[,;\/]+/', $get('Roles')) as $word) {
                $key = preg_replace('/[^a-z]/', '', mb_strtolower($word));
                if ($key === '') {
                    continue;
                }
                isset(self::ROLE_WORDS[$key])
                    ? $row['roles'][] = self::ROLE_WORDS[$key]
                    : $row['messages'][] = "Unknown role '{$word}'.";
            }
            $row['roles'] = array_values(array_unique($row['roles']));
            if (! $row['roles'] && ! array_filter($row['messages'], fn ($m) => str_starts_with($m, 'Unknown role'))) {
                $row['messages'][] = 'Roles are missing.';
            }

            if ($row['department']) {
                $row['department_id'] = $deptIndex[DepartmentList::key($row['department'])] ?? $deptIndex['short:'.mb_strtolower($row['department'])] ?? null;
                if (! $row['department_id']) {
                    $row['messages'][] = "Unknown department '{$row['department']}'.";
                }
            } elseif (array_intersect($row['roles'], [UserRole::HOD, UserRole::FACULTY])) {
                $row['messages'][] = 'Department is required for Faculty / HOD.';
            }

            if ($row['messages']) {
                $row['status'] = 'error';
            } elseif ($model::where('email', $email)->exists()) {
                $row['status'] = 'existing';
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Creates users / roles for the valid preview rows.
     *
     * @return array{created: int, updated: int, roles: int, mailed: int}
     */
    public function import(array $rows, bool $sendMail = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'roles' => 0, 'mailed' => 0];
        $newUsers = [];
        DB::transaction(function () use ($rows, &$result, &$newUsers) {
            foreach ($rows as $r) {
                if ($r['status'] === 'error') {
                    continue;
                }
                [$user, $created] = $this->ensureUser($r['name'], $r['email']);
                $result[$created ? 'created' : 'updated']++;
                if ($created) {
                    $newUsers[] = $user;
                }
                foreach ($r['roles'] as $role) {
                    $result['roles'] += $this->grant($user, $role, $r['department_id']) ? 1 : 0;
                }
                if (in_array(UserRole::FACULTY, $r['roles']) || in_array(UserRole::HOD, $r['roles'])) {
                    $this->linkFaculty($user, $r);
                }
            }
        });
        if ($sendMail && $this->settings->get('mail.enabled')) {
            foreach ($newUsers as $u) {
                try {
                    $u->notify(new AuditMail('Your login for the Academic Audit portal', [
                        'An account has been created for you on the MMMUT Internal Academic Audit portal.',
                        "Login: {$u->email}",
                        'Password: '.config('academic-audit.default_password').' (you will be asked to change it after the first login)',
                    ], 'Open portal', route('login')));
                    $result['mailed']++;
                } catch (\Throwable $e) {
                    Log::warning('Welcome mail failed: '.$e->getMessage());
                }
            }
        }
        return $result;
    }

    /** @return array{0: object, 1: bool} user and whether it was created */
    public function ensureUser(string $name, string $email): array
    {
        $model = config('academic-audit.user_model');
        $email = mb_strtolower(trim($email));
        if ($user = $model::where('email', $email)->first()) {
            return [$user, false];
        }
        $user = new $model;
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => Hash::make(config('academic-audit.default_password'))]);
        if (Schema::hasColumn($user->getTable(), 'must_change_password') && $this->settings->get('security.force_change')) {
            $user->forceFill(['must_change_password' => true]);
        }
        $user->save();
        return [$user, true];
    }

    /** Adds a role; returns false when the user already had it. */
    public function grant(object $user, string $role, ?int $departmentId): bool
    {
        $deptId = in_array($role, [UserRole::HOD, UserRole::FACULTY]) ? $departmentId : null;
        return UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $role, 'department_id' => $deptId])->wasRecentlyCreated;
    }

    private function linkFaculty(object $user, array $r): void
    {
        $faculty = Faculty::where('user_id', $user->id)->first() ?? Faculty::where('email', $user->email)->first();
        if (! $faculty) {
            $salutation = preg_match('/^(Dr|Prof|Mr|Mrs|Ms|Er)\.?\s+/i', $r['name'], $m) ? rtrim($m[1], '.').'.' : null;
            Faculty::create([
                'department_id' => $r['department_id'], 'user_id' => $user->id, 'email' => $user->email,
                'salutation' => $salutation, 'name' => $salutation ? trim(substr($r['name'], strlen($m[0]))) : $r['name'],
                'designation' => $r['designation'], 'mobile' => $r['mobile'], 'is_regular' => true, 'is_active' => true, 'source' => 'import',
            ]);
            return;
        }
        $faculty->update(array_filter(['user_id' => $user->id, 'department_id' => $faculty->department_id ?? $r['department_id'],
            'designation' => $faculty->designation ?? $r['designation'], 'mobile' => $faculty->mobile ?? $r['mobile']]));
    }

    private function departmentIndex(): array
    {
        $idx = [];
        foreach (Department::all() as $d) {
            $idx[DepartmentList::key($d->name)] = $d->id;
            if ($d->short_name) {
                $idx['short:'.mb_strtolower($d->short_name)] = $d->id;
            }
        }
        foreach (DepartmentAlias::all() as $a) {
            $idx[DepartmentList::key($a->alias)] = $a->department_id;
        }
        return $idx;
    }
}
