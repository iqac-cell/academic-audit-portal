<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentAlias extends Model
{
    protected $table = 'aa_department_aliases';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
