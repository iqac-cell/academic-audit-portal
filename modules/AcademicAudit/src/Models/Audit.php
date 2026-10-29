<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const RETURNED = 'returned';
    public const VERIFIED = 'verified';
    public const AUDITED = 'audited';
    public const LOCKED = 'locked';

    public const STATUS_LABELS = [
        self::DRAFT => ['Draft', 'secondary'],
        self::SUBMITTED => ['Submitted to IQAC', 'primary'],
        self::RETURNED => ['Returned to HOD', 'warning'],
        self::VERIFIED => ['Verified by IQAC', 'info'],
        self::AUDITED => ['Audited', 'success'],
        self::LOCKED => ['Locked', 'dark'],
    ];

    protected $table = 'aa_audits';
    protected $guarded = ['id'];
    protected $casts = [
        'basic_info' => 'array',
        'scalars' => 'array',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'auditor1_done_at' => 'datetime',
        'auditor2_done_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(Cycle::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function scores()
    {
        return $this->hasMany(Score::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function logs()
    {
        return $this->hasMany(AuditLog::class)->latest('id');
    }

    public function hod()
    {
        return $this->belongsTo(config('academic-audit.user_model'), 'hod_user_id');
    }

    public function auditor1()
    {
        return $this->belongsTo(config('academic-audit.user_model'), 'auditor1_user_id');
    }

    public function auditor2()
    {
        return $this->belongsTo(config('academic-audit.user_model'), 'auditor2_user_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::RETURNED], true);
    }

    public function scalar(string $key): mixed
    {
        $v = $this->scalars[$key] ?? null;
        return $v === '' ? null : $v;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_LABELS[$this->status][1] ?? 'secondary';
    }

    public function log(string $action, ?string $detail = null): void
    {
        $this->logs()->create(['user_id' => auth()->id(), 'action' => $action, 'detail' => $detail ? mb_substr($detail, 0, 1000) : null]);
    }
}
