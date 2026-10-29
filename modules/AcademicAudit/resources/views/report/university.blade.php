@extends(config('academic-audit.layout'))
@section('title', 'University Summary')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h4 class="mb-0">University Summary – {{ $cycle->academic_year }}</h4>
    <div class="d-flex gap-2">
        <form method="get"><select name="cycle" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($cycles as $c)<option value="{{ $c->id }}" @selected($c->id === $cycle->id)>{{ $c->academic_year }}</option>@endforeach
        </select></form>
        <a class="btn btn-sm btn-success" href="{{ route('audit.reports.university.xlsx', ['cycle' => $cycle->id]) }}"><i class="bi bi-file-earmark-excel"></i> Excel (parameter-wise)</a>
    </div>
</div>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr><th>#</th><th>Department</th><th>Status</th>
                @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $s => $l)<th class="text-end">{{ $s }}. {{ $l }}</th>@endforeach
                <th class="text-end">Claimed</th><th class="text-end">Awarded</th><th class="text-end">%</th></tr>
            </thead>
            <tbody>
            @foreach($rows->sortByDesc(fn ($r) => $r['summary']['total']['awarded'] ?? -1)->values() as $i => $r)
                @php($t = $r['summary']['total'])
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td><a href="{{ route('audit.show', $r['audit']) }}">{{ $r['audit']->department->name }}</a></td>
                    <td>@include('audit::partials.status', ['audit' => $r['audit']])</td>
                    @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $s => $l)
                        <td class="text-end">@marks($r['summary'][$s]['awarded'] ?? $r['summary'][$s]['claimed']) <span class="text-muted small">/@marks($r['summary'][$s]['max'])</span></td>
                    @endforeach
                    <td class="text-end">@marks($t['claimed'])</td>
                    <td class="text-end fw-bold">@marks($t['awarded'])</td>
                    <td class="text-end">{{ $t['awarded'] !== null && $t['max'] ? round($t['awarded'] / $t['max'] * 100, 1) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Ranked by awarded marks. Section columns show awarded marks when available, otherwise claimed.</p>
@endsection
