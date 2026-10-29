@extends(config('academic-audit.layout'))
@section('title', 'Faculty')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <h4 class="mb-0">Faculty directory</h4>
    <form method="post" enctype="multipart/form-data" action="{{ route('audit.admin.faculty.import') }}" class="d-flex gap-2">
        @csrf
        <input type="file" name="file" accept=".xlsx,.xls" class="form-control form-control-sm" required>
        <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-upload"></i> Import Samarth ERP export</button>
    </form>
</div>
<p class="small text-muted">Only "Teaching" cadre rows are imported. Each faculty member with an official email gets a login (default password, change on first login) and the Faculty role for their department.</p>
<form method="get" class="d-flex gap-2 mb-2" style="max-width:640px">
    <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name / email">
    <select name="department" class="form-select form-select-sm"><option value="">All departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach</select>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
</form>
<div class="card shadow-sm mb-3">
    <div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Name</th><th>Department</th><th>Designation</th><th>Qualification</th><th>Email</th><th>Joining</th><th>Retirement</th><th>Regular</th><th>Active</th><th></th></tr></thead>
        <tbody>
        @foreach($faculty as $f)
            <tr>
                                    <input form="fac-{{ $f->id }}" type="hidden" name="salutation" value="{{ $f->salutation }}">
                    <td><input form="fac-{{ $f->id }}" name="name" value="{{ $f->name }}" class="form-control form-control-sm" style="min-width:160px"></td>
                    <td><select form="fac-{{ $f->id }}" name="department_id" class="form-select form-select-sm">@foreach($departments as $d)<option value="{{ $d->id }}" @selected($f->department_id === $d->id)>{{ $d->short_name ?: $d->name }}</option>@endforeach</select></td>
                    <td><input form="fac-{{ $f->id }}" name="designation" value="{{ $f->designation }}" class="form-control form-control-sm"></td>
                    <td><input form="fac-{{ $f->id }}" name="qualification" value="{{ $f->qualification }}" class="form-control form-control-sm"></td>
                    <td><input form="fac-{{ $f->id }}" name="email" value="{{ $f->email }}" class="form-control form-control-sm"></td>
                    <td><input form="fac-{{ $f->id }}" type="date" name="date_of_joining" value="{{ $f->date_of_joining?->format('Y-m-d') }}" class="form-control form-control-sm"></td>
                    <td><input form="fac-{{ $f->id }}" type="date" name="date_of_retirement" value="{{ $f->date_of_retirement?->format('Y-m-d') }}" class="form-control form-control-sm"></td>
                    <td class="text-center"><input form="fac-{{ $f->id }}" type="checkbox" name="is_regular" value="1" @checked($f->is_regular)></td>
                    <td class="text-center"><input form="fac-{{ $f->id }}" type="checkbox" name="is_active" value="1" @checked($f->is_active)></td>
                    <td><button form="fac-{{ $f->id }}" class="btn btn-sm btn-outline-primary py-0">Save</button></td>
                    <td class="d-none"><form id="fac-{{ $f->id }}" method="post" action="{{ route('audit.admin.faculty.update', $f) }}">@csrf @method('put')</form></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
{{ $faculty->links('pagination::bootstrap-5') }}
<div class="card shadow-sm mt-3" style="max-width:900px">
    <div class="card-header bg-white"><strong>Add faculty manually</strong></div>
    <div class="card-body">
        <form method="post" action="{{ route('audit.admin.faculty.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-1"><label class="form-label">Title</label><input name="salutation" class="form-control form-control-sm" placeholder="Dr."></div>
            <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><label class="form-label">Department</label><select name="department_id" class="form-select form-select-sm">@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Designation</label><input name="designation" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">Qualification</label><input name="qualification" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label">Joining</label><input type="date" name="date_of_joining" class="form-control form-control-sm"></div>
            <div class="col-md-2 form-check ms-2"><input type="hidden" name="is_active" value="1"><input type="checkbox" class="form-check-input" name="is_regular" value="1" checked id="reg"><label for="reg" class="form-check-label small">Regular</label></div>
            <div class="col-md-2"><button class="btn btn-sm btn-success">Add</button></div>
        </form>
    </div>
</div>
@endsection
