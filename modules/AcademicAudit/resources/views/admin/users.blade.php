@extends(config('academic-audit.layout'))
@section('title', 'Users & roles')
@section('content')
<h4>Users &amp; roles</h4>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Assign a role</strong> <span class="small text-muted">— a new login is created with the default password if the email is not registered</span></div>
    <div class="card-body">
        <form method="post" action="{{ route('audit.admin.users.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control form-control-sm" required value="{{ old('email') }}"></div>
            <div class="col-md-3"><label class="form-label">Name (new users)</label><input name="name" class="form-control form-control-sm" value="{{ old('name') }}"></div>
            <div class="col-md-2"><label class="form-label">Role</label><select name="role" class="form-select form-select-sm">@foreach($labels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Department (HOD / Faculty)</label><select name="department_id" class="form-select form-select-sm"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
            <div class="col-md-1"><button class="btn btn-sm btn-success w-100">Assign</button></div>
        </form>
    </div>
</div>
<form method="get" class="d-flex gap-2 mb-2" style="max-width:600px">
    <select name="role" class="form-select form-select-sm"><option value="">All roles</option>@foreach($labels as $k => $l)<option value="{{ $k }}" @selected(request('role') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="department" class="form-select form-select-sm"><option value="">All departments</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(request('department') == $d->id)>{{ $d->name }}</option>@endforeach</select>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
</form>
<div class="card shadow-sm">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th></th></tr></thead>
        <tbody>
        @foreach($roles as $r)
            <tr>
                <td>{{ $r->user->name }}</td><td class="small">{{ $r->user->email }}</td>
                <td><span class="badge bg-{{ ['iqac' => 'danger', 'hod' => 'primary', 'auditor' => 'info', 'faculty' => 'secondary'][$r->role] }}">{{ $labels[$r->role] }}</span></td>
                <td class="small">{{ $r->department?->name }}</td>
                <td class="text-nowrap">
                    <form class="d-inline" method="post" action="{{ route('audit.admin.users.reset', $r->user_id) }}" onsubmit="return confirm('Reset password to default?')">@csrf <button class="btn btn-sm btn-outline-secondary py-0">Reset password</button></form>
                    <form class="d-inline" method="post" action="{{ route('audit.admin.roles.destroy', $r) }}" onsubmit="return confirm('Remove this role?')">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger py-0">Remove role</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
