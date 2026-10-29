@extends(config('academic-audit.layout'))
@section('title', 'Departments')
@section('content')
<h4>Departments</h4>
<div class="card shadow-sm mb-3">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Department</th><th>Short</th><th>Faculty</th><th>ERP spellings (aliases)</th><th>Add alias</th></tr></thead>
        <tbody>
        @foreach($departments as $d)
            <tr>
                <td>{{ $d->name }}</td><td>{{ $d->short_name }}</td><td>{{ $d->faculty_count }}</td>
                <td class="small">{{ $d->aliases->pluck('alias')->implode(' · ') }}</td>
                <td><form method="post" action="{{ route('audit.admin.departments.alias', $d) }}" class="d-flex gap-1">@csrf<input name="alias" class="form-control form-control-sm" placeholder="name as in ERP" required><button class="btn btn-sm btn-outline-primary">Add</button></form></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="card shadow-sm" style="max-width:640px">
    <div class="card-header bg-white"><strong>Add department</strong></div>
    <div class="card-body">
        <form method="post" action="{{ route('audit.admin.departments.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-7"><label class="form-label">Name</label><input name="name" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><label class="form-label">Short name</label><input name="short_name" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-success w-100">Add</button></div>
        </form>
    </div>
</div>
@endsection
