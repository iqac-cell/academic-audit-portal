<?php

namespace Mmmut\AcademicAudit\Support;

class Fmt
{
    public static function marks(?float $v): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** Indicator value as shown to users (Yes/No for yes-no parameters). */
    public static function indicator(array $e): string
    {
        $m = $e['definition']['metric'];
        if (in_array($m['type'], ['yes_no', 'any_yes'], true)) {
            return $e['value'] === null ? '—' : ($e['value'] >= 1 ? 'Yes' : 'No');
        }
        return $m['type'] === 'manual' ? 'Verified by auditor' : self::value($e['value'], $m['decimals'] ?? 2);
    }

    public static function value(?float $v, int $decimals = 2): string
    {
        return $v === null ? '—' : rtrim(rtrim(number_format($v, $decimals, '.', ''), '0'), '.');
    }
}
