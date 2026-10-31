<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Models\Notice;

/** Public download of a notice attachment (also reachable from the login page). */
class NoticeFileController extends Controller
{
    public function show(Notice $notice)
    {
        abort_unless($notice->attachment_path && $notice->isVisible(), 404);
        $disk = Storage::disk(config('academic-audit.disk'));
        abort_unless($disk->exists($notice->attachment_path), 404);
        return $disk->response($notice->attachment_path, $notice->attachment_name, [], 'inline');
    }
}
