<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Cycle extends Model
{
    protected $table = 'aa_cycles';
    protected $guarded = ['id'];
    protected $casts = ['opens_on' => 'date', 'closes_on' => 'date'];

    public function parameters()
    {
        return $this->hasMany(Parameter::class)->orderBy('sort_order');
    }

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }

    public static function current(): ?self
    {
        return static::where('status', 'open')->orderByDesc('academic_year')->first()
            ?? static::orderByDesc('academic_year')->first();
    }

    /** Max marks per section for this cycle. */
    public function sectionMax(): array
    {
        return $this->parameters->groupBy('section')->map(fn ($g) => (float) $g->sum('max_marks'))->all();
    }
}
