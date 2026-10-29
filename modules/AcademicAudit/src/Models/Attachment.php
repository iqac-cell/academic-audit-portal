<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $table = 'aa_attachments';
    protected $guarded = ['id'];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }
}
