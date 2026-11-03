@extends(config('academic-audit.layout'))
@section('title', 'Users & roles')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Users &amp; roles</h4>
    <a href="{{ route('audit.admin.users.import') }}" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Import users from Excel</a>
</div>
@if($gen = session('generated_password'))
    <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
        <div>New password for <strong>{{ $gen['name'] }}</strong> ({{ $gen['email'] }}):</div>
        <code class="fs-5 px-2 bg-white border rounded" id="genPw">{{ $gen['password'] }}</code>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('genPw').textContent); this.textContent='Copied'">Copy</button>
        <div class="small text-muted w-100">This password is shown only once. Share it with the person privately.</div>
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white"><strong>Assign a role</strong> <span class="small text-muted">— a new login (without a known password) is created if the email is not registered</span></div>
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
                <td class="text-nowrap text-end">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary py-0 dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#pwModal"
                                        data-action="{{ route('audit.admin.users.password', $p['user']->id) }}" data-name="{{ $p['user']->name }}" data-email="{{ $p['user']->email }}">
                                    <i class="bi bi-key"></i> Set new password</button></li>
                            <li><form method="post" action="{{ route('audit.admin.users.reset', $p['user']->id) }}" onsubmit="return confirm(@json('Give '.$p['user']->name.' a new random temporary password (shown once)?'))">@csrf
                                    <button class="dropdown-item"><i class="bi bi-arrow-counterclockwise"></i> Reset to temporary password</button></form></li>
                            @if($otpEnabled)
                                <li><form method="post" action="{{ route('audit.admin.users.otp', $p['user']->id) }}">@csrf
                                        <button class="dropdown-item"><i class="bi bi-envelope"></i> Email reset code</button></form></li>
                            @endif
                            @if((int) $p['user']->id !== (int) auth()->id())
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="post" action="{{ route('audit.admin.users.destroy', $p['user']->id) }}"
                                          onsubmit="return confirm(@json('Delete the login of '.$p['user']->name.' ('.$p['user']->email.')? All their roles are removed. Their entries in audits are kept. This cannot be undone.'))">
                                        @csrf @method('delete')
                                        <button class="dropdown-item text-danger"><i class="bi bi-trash"></i> Delete user</button></form></li>
                            @endif
                        </ul>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted text-center py-3">No users.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="small text-muted mt-2">One person can hold several roles (e.g. Faculty + HOD + Auditor). After login they choose the role to work in from the switcher at the top right. To add a role to an existing person, use their email in the form above.</p>
<div class="modal fade" id="pwModal" tabindex="-1" aria-labelledby="pwTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="pwForm" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="pwTitle">Set new password</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small mb-2">For <strong id="pwName"></strong> <span class="text-muted" id="pwEmail"></span></p>
                <div class="form-check"><input class="form-check-input" type="radio" name="mode" value="generate" id="pwGen" checked><label class="form-check-label" for="pwGen">Generate a strong password (shown once after saving)</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="mode" value="type" id="pwType"><label class="form-check-label" for="pwType">Type a password</label></div>
                <div id="pwFields" class="d-none">
                    <input type="password" name="password" class="form-control form-control-sm mb-2" placeholder="New password" autocomplete="new-password">
                    <input type="password" name="password_confirmation" class="form-control form-control-sm mb-1" placeholder="Confirm new password" autocomplete="new-password">
                    <div class="form-text mb-2">At least {{ max(6, (int) $settings->get('security.password_min')) }} characters with letters and numbers.</div>
                </div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="must_change" value="1" id="pwMust" checked><label class="form-check-label" for="pwMust">Must change this password at next login</label></div>
                <p class="small text-muted mt-2 mb-0">The person is logged out of any open sessions.
                    @if(config('sso.enabled'))<br><strong>Central login (Q-Sphere) is on:</strong> users sign in with their Q-Sphere account; a local password is used only for the IQAC emergency login.@endif</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save password</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('pwModal').addEventListener('show.bs.modal', (ev) => {
    const b = ev.relatedTarget;
    document.getElementById('pwForm').action = b.dataset.action;
    document.getElementById('pwName').textContent = b.dataset.name;
    document.getElementById('pwEmail').textContent = '(' + b.dataset.email + ')';
    document.getElementById('pwForm').reset();
    document.getElementById('pwFields').classList.add('d-none');
});
document.querySelectorAll('#pwForm input[name=mode]').forEach(r => r.addEventListener('change', () => {
    const typed = document.getElementById('pwType').checked;
    document.getElementById('pwFields').classList.toggle('d-none', !typed);
    document.querySelectorAll('#pwFields input').forEach(i => i.required = typed);
}));
</script>
@endpush
@endsection
