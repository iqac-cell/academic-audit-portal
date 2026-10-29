<?php

namespace Mmmut\AcademicAudit\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Mmmut\AcademicAudit\Http\Controllers\Controller;
use Mmmut\AcademicAudit\Models\Department;

class DepartmentController extends Controller
{
    public function index()
    {
        $this->iqac();
        return $this->view('admin.departments', ['departments' => Department::with('aliases')->withCount('faculty')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->iqac();
        $data = $request->validate([
            'name' => 'required|string|max:200|unique:aa_departments,name',
            'short_name' => 'nullable|string|max:30',
            'is_academic' => 'boolean',
        ]);
        Department::create($data + ['is_academic' => true]);
        return back()->with('status', 'Department added. Open the cycle settings to create its audit.');
    }

    public function alias(Request $request, Department $department)
    {
        $this->iqac();
        $data = $request->validate(['alias' => 'required|string|max:200|unique:aa_department_aliases,alias']);
        $department->aliases()->create($data);
        return back()->with('status', 'Alias added.');
    }
}
