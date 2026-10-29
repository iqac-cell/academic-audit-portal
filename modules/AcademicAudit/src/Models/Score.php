<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    protected $table = 'aa_scores';
    protected $guarded = ['id'];
    protected $casts = [
        'metric_value' => 'float',
        'claimed_marks' => 'float',
        'auditor1_marks' => 'float',
        'auditor2_marks' => 'float',
        'awarded_marks' => 'float',
    ];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }
}
