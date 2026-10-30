<?php

namespace Mmmut\AcademicAudit\Services\Ai;

use Illuminate\Support\Collection;
use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Models\Entry;
use Mmmut\AcademicAudit\Services\ScoreService;
use Mmmut\AcademicAudit\Services\SettingsService;
use Mmmut\AcademicAudit\Support\Catalogue;

/**
 * Offline consistency checks on a department's audit data. Advisory only: issues are
 * shown to HOD / IQAC, nothing is changed or blocked (except proof when IQAC requires it,
 * which is enforced on entry).
 */
class DataQualityChecker
{
    /** Year columns that must fall in the academic year (code => columns). */
    private const YEAR_COLUMNS = [
        'A.i' => ['year'], 'C.e' => ['year'], 'C.g' => ['award_year'], 'C.h' => ['year'], 'C.i' => ['year'],
    ];

    /** Date columns that may legitimately lie before the academic year. */
    private const DATE_EXEMPT = ['C.f' => ['published_on'], 'C.j' => ['launched_on']];

    public function __construct(
        private ScoreService $scores,
        private DirectoryProvider $directory,
        private SettingsService $settings,
    ) {}

    /**
     * @return Collection<int, array{code: string, entry_id: ?int, severity: string, message: string}>
     */
    public function check(Audit $audit): Collection
    {
        $audit->loadMissing('cycle', 'department');
        $issues = collect();
        $add = function (string $code, ?int $entryId, string $severity, string $message) use ($issues) {
            $issues->push(['code' => $code, 'entry_id' => $entryId, 'severity' => $severity, 'message' => $message]);
        };

        $start = (int) substr($audit->cycle->academic_year, 0, 4);
        $from = "{$start}-07-01";
        $to = ($start + 1).'-06-30';
        $today = now()->toDateString();
        $entries = Entry::where('audit_id', $audit->id)->where('status', '!=', Entry::REJECTED)->orderBy('id')->get()->groupBy('parameter_code');
        $scalars = $this->scores->scalars($audit);
        $facultyKeys = $this->directory->faculty($audit->department)->map(fn ($f) => $this->personKey($f->name))->filter()->all();
        $proofMode = $this->settings->get('workflow.proof_mode');
        $attachments = $audit->attachments()->pluck('slot')->unique()->flip();

        foreach (Catalogue::all() as $code => $def) {
            $rows = $entries[$code] ?? collect();
            $seen = [];
            foreach ($rows->values() as $rowNo => $e) {
                $d = $e->data;
                // duplicates
                $key = $this->rowKey($def, $d);
                if ($key !== '' && isset($seen[$key])) {
                    $add($code, $e->id, 'warning', 'Looks like a duplicate of row '.$seen[$key].'.');
                }
                $seen[$key] ??= $rowNo + 1;

                foreach ($def['columns'] as $c) {
                    $v = $d[$c['key']] ?? null;
                    if ($v === null || $v === '') {
                        continue;
                    }
                    if ($c['type'] === 'date') {
                        if ($v > $today) {
                            $add($code, $e->id, 'error', "{$c['label']} ({$v}) is in the future.");
                        } elseif (! in_array($c['key'], self::DATE_EXEMPT[$code] ?? []) && ($v < $from || $v > $to)) {
                            $add($code, $e->id, 'warning', "{$c['label']} ({$v}) is outside academic year {$audit->cycle->academic_year}.");
                        }
                    }
                    if ($c['type'] === 'url' && ! preg_match('#^https?://\S+\.\S+#i', (string) $v)) {
                        $add($code, $e->id, 'warning', "{$c['label']} is not a valid web link (should start with http:// or https://).");
                    }
                    if ($c['key'] === 'issn' && ! preg_match('/^\d{4}-?\d{3}[\dXx]$/', trim((string) $v))) {
                        $add($code, $e->id, 'warning', "ISSN '{$v}' does not look valid (format 1234-567X).");
                    }
                    if ($c['type'] === 'faculty' && $facultyKeys && ! $this->knownPerson((string) $v, $facultyKeys)) {
                        $add($code, $e->id, 'warning', "'{$v}' is not in the department faculty list.");
                    }
                }
                foreach (self::YEAR_COLUMNS[$code] ?? [] as $col) {
                    $y = (int) ($d[$col] ?? 0);
                    if ($y && ! in_array($y, [$start, $start + 1])) {
                        $add($code, $e->id, 'warning', "Year {$y} is outside academic year {$audit->cycle->academic_year}.");
                    }
                }
                $this->rowRules($code, $d, fn ($sev, $msg) => $add($code, $e->id, $sev, $msg));

                if ($proofMode !== 'none' && ! $e->proof_path && ! isset($attachments[$code])) {
                    $add($code, $e->id, $proofMode === 'require' ? 'error' : 'warning', 'No proof document attached.');
                }
            }
        }

        // parameter level
        $graduates = (float) ($scalars['graduates'] ?? 0);
        foreach (['A.e', 'A.f', 'A.g', 'A.h', 'A.i'] as $code) {
            $n = count($entries[$code] ?? []);
            if ($graduates > 0 && in_array($code, ['A.f', 'A.g']) && $n > $graduates) {
                $add($code, null, 'error', "{$n} students listed but only {$graduates} graduating as per sanctioned intake.");
            }
        }
        if (($scalars['int_completed'] ?? null) !== null && (float) ($scalars['int_completed'] ?? 0) > (float) ($scalars['int_eligible'] ?? INF)) {
            $add('B.b', null, 'error', 'Students completed internship is more than eligible students.');
        }
        foreach ($this->scores->evaluate($audit) as $code => $e) {
            if ($e['missing'] && ($e['rows'] > 0 || ! $e['definition']['columns'])) {
                $add($code, null, 'warning', $e['missing']);
            }
        }

        return $issues->sortBy(fn ($i) => sprintf('%d-%03d', $i['severity'] === 'error' ? 0 : 1, Catalogue::get($i['code'])['order']))->values();
    }

    private function rowRules(string $code, array $d, callable $add): void
    {
        $num = fn ($k) => is_numeric($d[$k] ?? null) ? (float) $d[$k] : null;
        switch ($code) {
            case 'A.a':
            case 'A.b':
                if ($num('n1') !== null && $num('n') !== null && $num('n1') > $num('n')) {
                    $add('error', 'N1 (completed) cannot be more than N (admitted).');
                }
                break;
            case 'A.c':
                if ($num('cgpa') !== null && ($num('cgpa') > 10 || $num('cgpa') <= 0)) {
                    $add('error', 'Average CGPA must be between 0 and 10.');
                }
                if ($num('passed') !== null && $num('students') !== null && $num('passed') > $num('students')) {
                    $add('error', 'Students passed is more than number of students.');
                }
                break;
            case 'A.g':
                if ($num('package') !== null && ($num('package') < 50000 || $num('package') > 20000000)) {
                    $add('warning', 'Pay package looks unusual — enter the annual amount in rupees.');
                }
                break;
            case 'C.p':
                $days = $num('days');
                if ($days === null && ! empty($d['from']) && ! empty($d['to'])) {
                    $days = (strtotime($d['to']) - strtotime($d['from'])) / 86400 + 1;
                }
                if ($days !== null && $days < 5) {
                    $add('warning', 'Shorter than one week — FDP/STTP under 5 days is not counted.');
                }
                break;
            case 'C.l':
                if ($num('revenue') !== null && $num('revenue') > 500) {
                    $add('warning', 'Revenue is entered in lakhs here — please check the amount.');
                }
                break;
        }
        if (! empty($d['from']) && ! empty($d['to']) && $d['to'] < $d['from']) {
            $add('error', '"To" date is before "From" date.');
        }
    }

    private function rowKey(array $def, array $d): string
    {
        $parts = [];
        foreach ($def['columns'] as $i => $c) {
            if ($i === 0 || in_array($c['key'], ['faculty', 'year', 'number', 'student', 'reg_no'])) {
                $parts[] = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) ($d[$c['key']] ?? '')));
            }
        }
        return trim(implode('|', $parts), '|');
    }

    private function personKey(string $name): string
    {
        $n = mb_strtolower($name);
        $n = preg_replace('/\b(dr|prof|mr|mrs|ms|er|shri|smt)\b\.?/', ' ', $n);
        return preg_replace('/[^a-z]/', '', $n);
    }

    private function knownPerson(string $value, array $keys): bool
    {
        foreach (preg_split('/[,;&]|\band\b/i', $value) as $part) {
            $k = $this->personKey($part);
            if ($k === '') {
                continue;
            }
            foreach ($keys as $fk) {
                if ($fk !== '' && (str_contains($k, $fk) || str_contains($fk, $k))) {
                    return true;
                }
            }
        }
        return false;
    }
}
