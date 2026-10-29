<?php

namespace Mmmut\AcademicAudit\Providers;

use Mmmut\AcademicAudit\Contracts\DirectoryProvider;
use Mmmut\AcademicAudit\Contracts\PrefillProvider;
use Mmmut\AcademicAudit\Models\Audit;

/**
 * Standalone default: only what the local directory knows (faculty list, faculty count,
 * department profile). Replace with an IQAC-backed provider after merging.
 */
class NullPrefillProvider implements PrefillProvider
{
    public function __construct(private DirectoryProvider $directory) {}

    public function name(): string
    {
        return 'Faculty directory';
    }

    public function supports(): array
    {
        return [];
    }

    public function rows(string $parameterCode, Audit $audit): iterable
    {
        return [];
    }

    public function scalars(Audit $audit): array
    {
        return ['regular_faculty' => $this->directory->regularFacultyCount($audit->department, $audit->cycle)];
    }

    public function basicInfo(Audit $audit): array
    {
        $dept = $audit->department;

        return [
            'about' => $dept->about,
            'vision' => $dept->vision,
            'mission' => $dept->mission,
            'faculty' => $this->directory->faculty($dept)->map(fn ($f) => [
                'name' => $f->display_name,
                'qualification' => $f->qualification,
                'experience' => $f->experienceYears(),
                'doj' => $f->date_of_joining?->format('d-m-Y'),
            ])->values()->all(),
        ];
    }
}
