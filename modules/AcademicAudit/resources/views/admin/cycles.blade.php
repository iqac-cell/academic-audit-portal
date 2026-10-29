@extends(config('academic-audit.layout'))
@section('title', 'Audit cycles')
@section('content')
<h4>Audit cycles</h4>
<div class="card shadow-sm mb-3">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Academic year</th><th>Opens</th><th>Closes</th><th>Status</th><th>Awarded marks =</th><th>Audits</th><th></th></tr></thead>
        <tbody>
        @foreach($cycles as $c)
            <tr>
                                    <td class="fw-semibold">{{ $c->academic_year }}</td>
                    <td><input form="cyc-{{ $c->id }}" type="date" name="opens_on" value="{{ $c->opens_on?->format('Y-m-d') }}" class="form-control form-control-sm"></td>
                    <td><input form="cyc-{{ $c->id }}" type="date" name="closes_on" value="{{ $c->closes_on?->format('Y-m-d') }}" class="form-control form-control-sm"></td>
                    <td><select form="cyc-{{ $c->id }}" name="status" class="form-select form-select-sm"><option value="open" @selected($c->status === 'open')>Open</option><option value="closed" @selected($c->status === 'closed')>Closed</option></select></td>
                    <td><select form="cyc-{{ $c->id }}" name="awarding_rule" class="form-select form-select-sm">
                            @foreach(['average' => 'Average of 2 auditors', 'min' => 'Lower of 2 auditors', 'max' => 'Higher of 2 auditors'] as $k => $l)<option value="{{ $k }}" @selected($c->awarding_rule === $k)>{{ $l }}</option>@endforeach
                        </select></td>
                    <td>{{ $c->audits_count }}</td>
                    <td class="text-nowrap"><button form="cyc-{{ $c->id }}" class="btn btn-sm btn-primary">Save</button>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('audit.admin.parameters', $c) }}">Marks scheme</a></td>
                    <td class="d-none"><form id="cyc-{{ $c->id }}" method="post" action="{{ route('audit.admin.cycles.update', $c) }}">@csrf @method('put')</form></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="card shadow-sm" style="max-width:640px">
    <div class="card-header bg-white"><strong>Open a new cycle</strong></div>
    <div class="card-body">
        <form method="post" action="{{ route('audit.admin.cycles.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4"><label class="form-label">Academic year</label><input name="academic_year" class="form-control form-control-sm" placeholder="2026-27" required></div>
            <div class="col-md-3"><label class="form-label">Opens</label><input type="date" name="opens_on" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label">Closes</label><input type="date" name="closes_on" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-success w-100">Create</button></div>
        </form>
        <p class="small text-muted mt-2 mb-0">Max marks and slabs are copied from the latest cycle; one audit is created per academic department. Saving a cycle also creates audits for newly added departments.</p>
    </div>
</div>
@endsection
