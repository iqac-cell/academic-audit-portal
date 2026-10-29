<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;
use Mmmut\AcademicAudit\Support\Catalogue;

class Parameter extends Model
{
    protected $table = 'aa_parameters';
    protected $guarded = ['id'];
    protected $casts = ['max_marks' => 'float'];

    public function slabs()
    {
        return $this->hasMany(ParameterSlab::class)->orderBy('sort_order');
    }

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }

    /** Static definition (columns, metric) from the catalogue. */
    public function definition(): array
    {
        return Catalogue::get($this->code);
    }
}
