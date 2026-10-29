@extends(config('academic-audit.layout'))
@section('title', 'Marks scheme')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">Marks scheme – {{ $cycle->academic_year }}</h4>
    <form method="post" action="{{ route('audit.admin.parameters.reset', $cycle) }}" onsubmit="return confirm('Reset all max marks and slabs of this cycle to the printed evaluation scheme?')">@csrf <button class="btn btn-sm btn-outline-danger">Reset to printed scheme</button></form>
</div>
<div class="alert alert-warning small">
    <strong>Discrepancies in the printed format (Oct 2026) — please confirm:</strong>
    <ul class="mb-0">
        <li>Section B totals 200 in the Evaluation table but 350 in the Summary; the Summary grand total says 1000 though A+B+C+D = 950 (or 800 with B = 200). The portal totals whatever max marks are set below: currently <strong>{{ \Mmmut\AcademicAudit\Support\Fmt::marks(array_sum($sectionMax)) }}</strong>.</li>
        <li>A(i) is 10 marks on the form page but 15 in the Evaluation Scheme (15 used).</li>
        <li>A(f) Higher studies (max 15) and A(g) Placement (max 30): the printed slabs give at most 10 marks.</li>
        <li>Where the printed slab text has typos (e.g. "20 &gt; PSC", "4 &lt; NFD &gt; 5", "Patent"/"FRA" in C.h/C.k), the evident intent was used — see the note on each parameter.</li>
    </ul>
</div>
<div class="row g-2 mb-3">
    @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $s => $l)
        <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2"><div class="small text-muted">Section {{ $s }} – {{ $l }}</div><div class="fs-5 fw-semibold">@marks($sectionMax[$s] ?? 0)</div></div></div></div>
    @endforeach
</div>
<div class="card shadow-sm">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Code</th><th>Parameter</th><th class="text-end">Max</th><th>Scoring</th><th>Slabs</th><th></th></tr></thead>
        <tbody>
        @foreach($cycle->parameters as $p)
            @php($sym = $p->definition()['metric']['symbol'])
            <tr>
                <td>{{ $p->code }}</td>
                <td class="small">{{ $p->title }} @if($p->notes)<i class="bi bi-info-circle text-muted" title="{{ $p->notes }}"></i>@endif</td>
                <td class="text-end fw-semibold">@marks($p->max_marks)</td>
                <td class="small">{{ ['slab' => 'Slab', 'yes_no' => 'Yes / No', 'manual' => 'Auditor'][$p->scoring] }}</td>
                <td class="small">@foreach($p->slabs as $s)<span class="badge bg-light text-dark border">{{ $s->label($sym) }} → @marks($s->marks)</span> @endforeach</td>
                <td><a class="btn btn-sm btn-outline-primary py-0" href="{{ route('audit.admin.parameters.edit', $p) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
