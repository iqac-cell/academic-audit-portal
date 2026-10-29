<?php

namespace Mmmut\AcademicAudit\Models;

use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    protected $table = 'aa_faculty';
    protected $guarded = ['id'];
    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_retirement' => 'date',
        'date_of_birth' => 'date',
        'is_regular' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(($this->salutation ? $this->salutation.' ' : '').$this->name);
    }

    /** Years since joining the university, as of the given date. */
    public function experienceYears(?\DateTimeInterface $asOf = null): ?int
    {
        return $this->date_of_joining ? (int) $this->date_of_joining->diffInYears($asOf ?? now()) : null;
    }
}
