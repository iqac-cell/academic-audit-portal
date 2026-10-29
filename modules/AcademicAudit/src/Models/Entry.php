<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $table = 'aa_entries';
    protected $guarded = ['id'];
    protected $casts = ['data' => 'array'];

    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', self::APPROVED);
    }

    public function value(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
