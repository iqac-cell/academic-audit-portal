@extends(config('academic-audit.layout'))
@section('title', 'Dashboard')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h4 class="mb-0">Academic Audit {{ $cycle?->academic_year }}</h4>
    @if($cycles->count() > 1)
        <form class="d-flex gap-2" method="get">
            <select name="cycle" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($cycles as $c)<option value="{{ $c->id }}" @selected($cycle?->id === $c->id)>{{ $c->academic_year }}</option>@endforeach
            </select>
        </form>
    @endif
</div>

@if(! $cycle)
    <div class="alert alert-info">No audit cycle has been opened yet. @if($access->isIqac())<a href="{{ route('audit.admin.cycles') }}">Create a cycle</a>.@endif</div>
@elseif($rows->isEmpty())
    <div class="alert alert-info">
        No department audit is assigned to you. If you are a HOD or faculty member, ask IQAC to map your login to your department.
    </div>
@else
    @if($cycle->closes_on)
        <p class="text-muted small">Submission window: {{ $cycle->opens_on?->format('d M Y') }} – {{ $cycle->closes_on->format('d M Y') }} · Cycle is <strong>{{ $cycle->status }}</strong></p>
    @endif

    @if($access->faculty() && $myPending->isNotEmpty())
        <div class="alert alert-light border small">
            Your contributions: {{ $myPending['approved'] ?? 0 }} approved, {{ $myPending['pending'] ?? 0 }} awaiting HOD, {{ $myPending['rejected'] ?? 0 }} returned for correction.
        </div>
    @endif

    @if($access->isIqac())
        @php($byStatus = $rows->groupBy(fn ($r) => $r['audit']->status))
        <div class="row g-2 mb-3">
            @foreach(\Mmmut\AcademicAudit\Models\Audit::STATUS_LABELS as $st => [$label, $color])
                <div class="col-6 col-md-2">
                    <div class="card border-{{ $color }} shadow-sm"><div class="card-body py-2">
                        <div class="small text-muted">{{ $label }}</div>
                        <div class="fs-4 fw-semibold">{{ $byStatus->get($st)?->count() ?? 0 }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Department</th><th>Status</th><th>Progress</th>
                    @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $s => $l)<th class="text-end" title="{{ $l }}">{{ $s }}</th>@endforeach
                    <th class="text-end">Claimed</th><th class="text-end">Awarded</th><th>Auditors</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    @php($a = $r['audit'])
                    <tr>
                        <td><a href="{{ route('audit.show', $a) }}" class="fw-semibold text-decoration-none">{{ $a->department->name }}</a>
                            @if($r['pending'])<br><span class="badge bg-warning text-dark">{{ $r['pending'] }} faculty rows awaiting HOD</span>@endif</td>
                        <td>@include('audit::partials.status', ['audit' => $a])</td>
                        <td style="min-width:120px">
                            @php($pct = $r['params'] ? round($r['filled'] / $r['params'] * 100) : 0)
                            <div class="progress" style="height:8px"><div class="progress-bar" style="width: {{ $pct }}%"></div></div>
                            <small class="text-muted">{{ $r['filled'] }}/{{ $r['params'] }} parameters</small>
                        </td>
                        @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $s => $l)
                            <td class="text-end small">@marks($r['summary'][$s]['awarded'] ?? $r['summary'][$s]['claimed'])<span class="text-muted">/@marks($r['summary'][$s]['max'])</span></td>
                        @endforeach
                        <td class="text-end fw-semibold">@marks($r['summary']['total']['claimed'])</td>
                        <td class="text-end fw-semibold">@marks($r['summary']['total']['awarded'])</td>
                        <td class="small">{{ $a->auditor1?->name ?? '—' }}<br>{{ $a->auditor2?->name ?? '—' }}</td>
                        <td><a class="btn btn-sm btn-primary" href="{{ route('audit.show', $a) }}">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2">Section columns show awarded marks once auditors finalise, otherwise marks claimed (auto-calculated from the evaluation scheme).</p>
@endif
@endsection
