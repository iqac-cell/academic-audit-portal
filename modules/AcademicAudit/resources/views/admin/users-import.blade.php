@extends(config('academic-audit.layout'))
@section('title', 'Import users')
@section('content')
<h4>Import users from Excel</h4>
<p class="small text-muted">Create logins and give roles to many people at once. Existing users (same email) only get the new roles — nothing is removed. New users get the default password and must change it at first login.</p>

@if(! $rows)
    <div class="card shadow-sm" style="max-width: 760px">
        <div class="card-body">
            <ol class="small">
                <li><a href="{{ route('audit.admin.users.import.template') }}"><i class="bi bi-download"></i> Download the template</a> (sheet "Help" lists the departments).</li>
                <li>Fill one row per person: <strong>Name, Email, Roles</strong> (Faculty, HOD, IQAC, Auditor — comma separated), <strong>Department</strong> (needed for Faculty / HOD), Designation, Mobile.</li>
                <li>Upload it here. You will see a preview before anything is created.</li>
            </ol>
            <form method="post" enctype="multipart/form-data" action="{{ route('audit.admin.users.import.preview') }}" class="d-flex gap-2">
                @csrf
                <input type="file" name="file" accept=".xlsx" class="form-control form-control-sm" required>
                <button class="btn btn-sm btn-primary text-nowrap">Upload &amp; preview</button>
            </form>
        </div>
    </div>
@else
    @php($rowsC = collect($rows))
    <div class="alert alert-light border small">
        <strong>{{ $rowsC->where('status', 'new')->count() }}</strong> new user(s),
        <strong>{{ $rowsC->where('status', 'existing')->count() }}</strong> existing user(s) (roles will be added),
        <strong class="text-danger">{{ $rowsC->where('status', 'error')->count() }}</strong> row(s) with errors (will be skipped).
    </div>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>Row</th><th>Name</th><th>Email</th><th>Roles</th><th>Department</th><th>Designation</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($rows as $r)
                    <tr class="{{ $r['status'] === 'error' ? 'table-danger' : '' }}">
                        <td>{{ $r['row'] }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td class="small">{{ $r['email'] }}</td>
                        <td class="small">{{ collect($r['roles'])->map(fn ($x) => \Mmmut\AcademicAudit\Models\UserRole::LABELS[$x])->implode(', ') }}</td>
                        <td class="small">{{ $r['department'] }}</td>
                        <td class="small">{{ $r['designation'] }}</td>
                        <td class="small">
                            @if($r['status'] === 'new')<span class="badge bg-success">New user</span>
                            @elseif($r['status'] === 'existing')<span class="badge bg-info text-dark">Existing – add roles</span>
                            @else<span class="badge bg-danger">Error</span> {{ implode(' ', $r['messages']) }}@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="post" action="{{ route('audit.admin.users.import.confirm') }}" class="d-flex gap-3 align-items-center">
            @csrf
            @if($mailEnabled)
                <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="send_mail" value="1" id="sm"><label class="form-check-label small" for="sm">Email login details to new users</label></div>
            @endif
            <button class="btn btn-success btn-sm" @disabled($rowsC->where('status', '!=', 'error')->isEmpty())>Import {{ $rowsC->where('status', '!=', 'error')->count() }} user(s)</button>
        </form>
        <form method="post" action="{{ route('audit.admin.users.import.cancel') }}">@csrf <button class="btn btn-outline-secondary btn-sm">Cancel / upload another file</button></form>
    </div>
@endif
@endsection
