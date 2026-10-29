<?php

namespace Mmmut\AcademicAudit\Support;

use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Models\Audit;

/** Validation rules and option lists for a parameter's data table. */
class ItemForm
{
    public static function rules(array $def, string $prefix = 'data.'): array
    {
        $rules = [];
        foreach ($def['columns'] as $i => $c) {
            $r = [$i === 0 ? 'required' : 'nullable'];
            $r = array_merge($r, match ($c['type']) {
                'number' => ['numeric', 'min:0'],
                'date' => ['date'],
                'url' => ['string', 'max:1000'],
                'yn' => ['in:Y,N'],
                'select' => [\Illuminate\Validation\Rule::in($c['options'])],
                'textarea' => ['string', 'max:3000'],
                default => ['string', 'max:500'],
            });
            $rules[$prefix.$c['key']] = $r;
        }
        return $rules;
    }

    public static function attributes(array $def, string $prefix = 'data.'): array
    {
        $out = [];
        foreach ($def['columns'] as $c) {
            $out[$prefix.$c['key']] = $c['label'];
        }
        return $out;
    }

    /** Only the defined columns, trimmed, empty strings dropped to null. */
    public static function clean(array $def, array $input): array
    {
        $out = [];
        foreach ($def['columns'] as $c) {
            $v = $input[$c['key']] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            $out[$c['key']] = $v === '' ? null : $v;
        }
        return $out;
    }

    public static function programs(Audit $audit): array
    {
        return collect($audit->basic_info['programs'] ?? [])->pluck('name')->filter()->values()->all();
    }

    public static function facultyNames(Audit $audit): array
    {
        return app(DirectoryProvider::class)->faculty($audit->department)->pluck('display_name')->all();
    }

    public static function display(array $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return match ($column['type']) {
            'yn' => $value === 'Y' ? 'Yes' : 'No',
            'date' => rescue(fn () => \Carbon\Carbon::parse($value)->format('d-m-Y'), (string) $value, false),
            default => (string) $value,
        };
    }
}
