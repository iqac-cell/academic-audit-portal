<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    public const IQAC = 'iqac';
    public const HOD = 'hod';
    public const FACULTY = 'faculty';
    public const AUDITOR = 'auditor';

    public const LABELS = [
        self::IQAC => 'IQAC',
        self::HOD => 'Head of Department',
        self::FACULTY => 'Faculty',
        self::AUDITOR => 'Academic Auditor',
    ];

    protected $table = 'aa_user_roles';
    protected $guarded = ['id'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function user()
    {
        return $this->belongsTo(config('academic-audit.user_model'), 'user_id');
    }
}
