@extends(config('academic-audit.layout'))
@section('title', 'Users & roles')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Users &amp; roles</h4>
    <a href="{{ route('audit.admin.users.import') }}" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Import users from Excel</a>
</div>
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
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Roles</th><th></th></tr></thead>
        <tbody>
        @forelse($people as $p)
            <tr>
                <td>{{ $p['user']->name }}</td><td class="small">{{ $p['user']->email }}</td>
                <td>
                    @foreach($p['roles'] as $r)
                        <span class="badge bg-{{ ['iqac' => 'danger', 'hod' => 'primary', 'auditor' => 'info', 'faculty' => 'secondary'][$r->role] }} me-1 mb-1">
                            {{ $labels[$r->role] }}{{ $r->department ? ' – '.($r->department->short_name ?: $r->department->name) : '' }}
                            <form class="d-inline" method="post" action="{{ route('audit.admin.roles.destroy', $r) }}" onsubmit="return confirm('Remove this role?')">@csrf @method('delete')<button class="btn btn-link p-0 text-white ms-1" style="font-size:.7rem" title="Remove role"><i class="bi bi-x-lg"></i></button></form>
                        </span>
                    @endforeach
                </td>
                <td class="text-nowrap">
                    @if($otpEnabled)
                        <form class="d-inline" method="post" action="{{ route('audit.admin.users.otp', $p['user']->id) }}">@csrf <button class="btn btn-sm btn-outline-primary py-0">Email reset code</button></form>
                    @endif
                    <form class="d-inline" method="post" action="{{ route('audit.admin.users.reset', $p['user']->id) }}" onsubmit="return confirm('Reset password to the default password?')">@csrf <button class="btn btn-sm btn-outline-secondary py-0">Reset to default</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted text-center py-3">No users.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="small text-muted mt-2">One person can hold several roles (e.g. Faculty + HOD + Auditor). After login they choose the role to work in from the switcher at the top right. To add a role to an existing person, use their email in the form above.</p>
@endsection
