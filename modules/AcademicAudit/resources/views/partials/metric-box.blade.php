@php($d = $e['definition'])
<div class="card shadow-sm metric-box mb-3">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="small text-muted">Indicator</div>
                <div class="fs-5 fw-semibold">{{ $d['metric']['symbol'] }} = {{ \Mmmut\AcademicAudit\Support\Fmt::indicator($e) }}</div>
                <div class="small text-muted">{{ $e['formula'] }}</div>
                @if($e['missing'])<div class="small text-danger">{{ $e['missing'] }}</div>@endif
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Marks claimed (auto)</div>
                <div class="fs-5 fw-semibold">@marks($e['claimed']) <span class="text-muted fs-6">/ @marks($e['parameter']->max_marks)</span></div>
            </div>
            <div class="col-md-5 small">
                @if($e['parameter']->scoring === 'slab')
                    <div class="text-muted">Evaluation criteria</div>
                    @foreach($e['parameter']->slabs as $s)
                        <span class="badge bg-light text-dark border">{{ $s->label($d['metric']['symbol']) }} → {{ \Mmmut\AcademicAudit\Support\Fmt::marks($s->marks) }}</span>
                    @endforeach
                    <span class="badge bg-light text-muted border">otherwise → 0</span>
                @elseif($e['parameter']->scoring === 'yes_no')
                    <span class="text-muted">Yes: @marks($e['parameter']->max_marks) marks · No: zero</span>
                @else
                    <span class="text-muted">Marks awarded by auditor after verifying records.</span>
                @endif
            </div>
        </div>
        @if($e['parameter']->notes)<div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> {{ $e['parameter']->notes }}</div>@endif
    </div>
</div>
