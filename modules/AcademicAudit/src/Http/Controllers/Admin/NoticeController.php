<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Notice;

class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $this->iqac();
        $notices = Notice::orderByDesc('is_active')->orderBy('sort_order')->orderByDesc('id')->get();
        $editing = $request->filled('edit') ? $notices->firstWhere('id', (int) $request->edit) : null;
        return $this->view('admin.notices', compact('notices', 'editing'));
    }

    public function store(Request $request)
    {
        $this->iqac();
        $notice = Notice::create($this->validated($request) + ['created_by' => auth()->id()]);
        $this->saveFile($request, $notice);
        return redirect()->route('audit.admin.notices')->with('status', 'Notice added.');
    }

    public function update(Request $request, Notice $notice)
    {
        $this->iqac();
        $notice->update($this->validated($request));
        if ($request->boolean('remove_attachment') && $notice->attachment_path) {
            Storage::disk(config('academic-audit.disk'))->delete($notice->attachment_path);
            $notice->update(['attachment_path' => null, 'attachment_name' => null]);
        }
        $this->saveFile($request, $notice);
        return redirect()->route('audit.admin.notices')->with('status', 'Notice updated.');
    }

    public function toggle(Notice $notice)
    {
        $this->iqac();
        $notice->update(['is_active' => ! $notice->is_active]);
        return back()->with('status', $notice->is_active ? 'Notice shown.' : 'Notice hidden.');
    }

    public function destroy(Notice $notice)
    {
        $this->iqac();
        if ($notice->attachment_path) {
            Storage::disk(config('academic-audit.disk'))->delete($notice->attachment_path);
        }
        $notice->delete();
        return back()->with('status', 'Notice deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'link_url' => ['nullable', 'string', 'max:1000', 'regex:#^https?://#i'],
            'show_on' => 'required|in:'.implode(',', array_keys(Notice::PLACES)),
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ], ['link_url.regex' => 'The link must start with http:// or https://']);
        unset($data['attachment']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_new'] = $request->boolean('is_new');
        return $data;
    }

    private function saveFile(Request $request, Notice $notice): void
    {
        if (! $request->hasFile('attachment')) {
            return;
        }
        $disk = Storage::disk(config('academic-audit.disk'));
        if ($notice->attachment_path) {
            $disk->delete($notice->attachment_path);
        }
        $file = $request->file('attachment');
        $notice->update([
            'attachment_path' => $file->store(config('academic-audit.upload_dir').'/notices', config('academic-audit.disk')),
            'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 250),
        ]);
    }
}
