<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'aa_departments';
    protected $guarded = ['id'];
    protected $casts = ['is_academic' => 'boolean'];

    public function aliases()
    {
        return $this->hasMany(DepartmentAlias::class);
    }

    public function faculty()
    {
        return $this->hasMany(Faculty::class);
    }

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }
}
