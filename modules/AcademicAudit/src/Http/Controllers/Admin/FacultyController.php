<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Department;
use Mmmut\AcademicAudit\Models\Faculty;
use Mmmut\AcademicAudit\Services\ErpImporter;

class FacultyController extends Controller
{
    public function index(Request $request)
    {
        $this->iqac();
        $faculty = Faculty::with('department')
            ->when($request->department, fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')->paginate(50)->withQueryString();
        return $this->view('admin.faculty', ['faculty' => $faculty, 'departments' => Department::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->iqac();
        Faculty::create($this->validated($request) + ['source' => 'manual']);
        return back()->with('status', 'Faculty added.');
    }

    public function update(Request $request, Faculty $faculty)
    {
        $this->iqac();
        $faculty->update($this->validated($request));
        return back()->with('status', 'Faculty updated.');
    }

    public function import(Request $request, ErpImporter $importer)
    {
        $this->iqac();
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);
        $r = $importer->import($request->file('file')->getRealPath());
        $msg = "Imported: {$r['created']} new, {$r['updated']} updated, {$r['users_created']} logins created.";
        if ($r['unmatched_departments']) {
            $msg .= ' Unmatched departments: '.collect($r['unmatched_departments'])->map(fn ($n, $d) => "{$d} ({$n})")->implode(', ').'.';
        }
        return back()->with('status', $msg);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'salutation' => 'nullable|string|max:20',
            'department_id' => 'required|exists:aa_departments,id',
            'designation' => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:200',
            'date_of_joining' => 'nullable|date',
            'date_of_retirement' => 'nullable|date',
            'is_regular' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_regular'] = $request->boolean('is_regular');
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }
}
