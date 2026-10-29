<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'aa_audit_logs';
    protected $guarded = ['id'];
    public const UPDATED_AT = null;

    public function user()
    {
        return $this->belongsTo(config('academic-audit.user_model'), 'user_id');
    }
}
