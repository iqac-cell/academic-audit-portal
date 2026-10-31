<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

/** Scrolling notice shown on the login page and/or dashboard, managed by IQAC. */
class Notice extends Model
{
    public const PLACES = ['both' => 'Login page and dashboard', 'login' => 'Login page only', 'dashboard' => 'Dashboard only'];

    protected $table = 'aa_notices';
    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'is_new' => 'boolean'];

    /** Active notices for a place ('login' or 'dashboard') whose date window includes today. */
    public function scopeVisible($q, string $place)
    {
        $today = now()->toDateString();
        return $q->where('is_active', true)
            ->whereIn('show_on', [$place, 'both'])
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->orderBy('sort_order')->orderByDesc('id');
    }

    public function isVisible(): bool
    {
        return static::visible('login')->whereKey($this->id)->exists() || static::visible('dashboard')->whereKey($this->id)->exists();
    }

    public function url(): ?string
    {
        if ($this->attachment_path) {
            return route('audit.notices.file', $this);
        }
        return $this->link_url ?: null;
    }
}
