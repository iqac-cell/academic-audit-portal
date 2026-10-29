<?php

namespace Mmmut\AcademicAudit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Models\Attachment;
use Mmmut\AcademicAudit\Models\Audit;
use Mmmut\AcademicAudit\Support\Catalogue;

/** Annexures I–V and parameter-level proof documents. */
class AttachmentController extends Controller
{
    public function store(Request $request, Audit $audit)
    {
        $this->editable($audit);
        $slots = array_merge(array_keys(Catalogue::all()), array_map(fn ($k) => "ANX-{$k}", array_keys(config('academic-audit.annexures'))));
        $request->validate([
            'slot' => ['required', 'in:'.implode(',', $slots)],
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:'.config('academic-audit.max_upload_kb').'|mimes:'.config('academic-audit.upload_mimes'),
        ]);
        foreach ($request->file('files') as $file) {
            $audit->attachments()->create([
                'slot' => $request->slot,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'path' => $file->store(config('academic-audit.upload_dir')."/{$audit->id}", config('academic-audit.disk')),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }
        $audit->log('upload', "Uploaded ".count($request->file('files'))." file(s) to {$request->slot}");
        return back()->with('status', 'File(s) uploaded.');
    }

    public function show(Audit $audit, Attachment $attachment)
    {
        $this->viewable($audit);
        return Storage::disk(config('academic-audit.disk'))->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Audit $audit, Attachment $attachment)
    {
        $this->editable($audit);
        Storage::disk(config('academic-audit.disk'))->delete($attachment->path);
        $attachment->delete();
        return back()->with('status', 'File removed.');
    }
}
