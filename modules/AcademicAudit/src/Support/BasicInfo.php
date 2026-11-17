<?php

namespace Mmmut\AcademicAudit\Support;

/**
 * Basic information tables of an audit, as row lists. The printed format shows fixed rows
 * (UG / PG / PhD); departments can add as many rows as they need, so every table is a list.
 * Data saved in the old shape (keyed by level) is converted when read.
 */
class BasicInfo
{
    public const LEVELS = ['UG' => 'B.Tech./B.Pharm/B.B.A.', 'PG' => 'M.Tech./MCA/MBA/MSc', 'PhD' => 'Ph.D.'];

    /** Rows shown on an empty form, as in the printed format. */
    private const DEFAULT_SANCTIONED = ['UG', 'PG'];

    public static function normalize(array $info): array
    {
        $info['programs'] = self::list($info['programs'] ?? []);
        $info['faculty'] = self::list($info['faculty'] ?? []);
        $info['sanctioned'] = self::sanctioned($info['sanctioned'] ?? []);
        $info['enrolled'] = self::enrolled($info['enrolled'] ?? []);
        return $info;
    }

    /** @return list<array{program: ?string, body: ?string, ts: mixed}> */
    public static function sanctioned(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        if (self::isLegacy($value)) {
            $rows = [];
            foreach ($value as $level => $r) {
                $rows[] = ['program' => self::LEVELS[$level] ?? $level, 'body' => $r['body'] ?? null, 'ts' => $r['ts'] ?? null];
            }
            $value = $rows;
        }
        return self::list($value);
    }

    /** @return list<array{program: ?string, counts: array<string, mixed>}> */
    public static function enrolled(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        if (self::isLegacy($value)) {
            $rows = [];
            foreach ($value as $level => $counts) {
                $rows[] = ['program' => self::LEVELS[$level] ?? $level, 'counts' => is_array($counts) ? $counts : []];
            }
            $value = $rows;
        }
        return array_map(fn ($r) => ['program' => $r['program'] ?? null, 'counts' => array_intersect_key($r['counts'] ?? [], array_flip(Catalogue::YEARS))], self::list($value));
    }

    /** Rows to show on the form: the saved rows, or the printed-format rows when nothing is saved yet. */
    public static function formSanctioned(array $info): array
    {
        return $info['sanctioned'] ?: array_map(fn ($l) => ['program' => self::LEVELS[$l], 'body' => null, 'ts' => null], self::DEFAULT_SANCTIONED);
    }

    public static function formEnrolled(array $info): array
    {
        return $info['enrolled'] ?: array_map(fn ($label) => ['program' => $label, 'counts' => []], array_values(self::LEVELS));
    }

    public static function total(array $counts): float|int
    {
        return array_sum(array_map(fn ($v) => is_numeric($v) ? $v + 0 : 0, $counts));
    }

    /** Old data used the level (UG / PG / PhD) as key instead of a list. */
    private static function isLegacy(array $value): bool
    {
        return $value !== [] && ! array_is_list($value) && array_intersect(array_keys($value), array_keys(self::LEVELS)) !== [];
    }

    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
