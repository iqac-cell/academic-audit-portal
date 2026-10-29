<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class ParameterSlab extends Model
{
    protected $table = 'aa_parameter_slabs';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $casts = ['threshold' => 'float', 'marks' => 'float'];

    public function label(string $symbol): string
    {
        return $symbol.' '.($this->op === 'gte' ? '≥' : '>').' '.rtrim(rtrim(number_format($this->threshold, 4, '.', ''), '0'), '.');
    }
}
