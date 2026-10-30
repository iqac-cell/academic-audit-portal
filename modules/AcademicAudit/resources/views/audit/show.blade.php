@extends(config('academic-audit.layout'))
@section('title', $audit->department->name)
@section('content')
@include('audit::partials.audit-header')
<div class="row">
    <div class="col-lg-3">@include('audit::partials.audit-nav', ['current' => 'overview', 'navEval' => $eval])</div>
    <div class="col-lg-9">
        {{-- Workflow actions --}}
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h6 class="card-title">Workflow</h6>
                <ol class="small mb-3">
                    <li>Faculty add their own records (papers, FDPs, projects …) — HOD approves them.</li>
                    <li>HOD fills department data, denominators and annexures, then <strong>submits to IQAC</strong>.</li>
                    <li>IQAC verifies each parameter (or returns with remarks) and assigns two academic auditors.</li>
                    <li>Auditor-1 and Auditor-2 award marks; final marks = {{ $audit->cycle->awarding_rule }} of the two. IQAC locks the audit.</li>
                </ol>
                <div class="d-flex flex-wrap gap-2">
                    @if($access->canEdit($audit))
                        @php($errCount = $issues?->where('severity', 'error')->count() ?? 0)
                        <form method="post" action="{{ route('audit.workflow', [$audit, 'submit']) }}" onsubmit="return confirm(@json(($errCount ? $errCount.' data-quality error(s) are still open. ' : '').'Submit the audit to IQAC? You will not be able to edit unless IQAC returns it.'))">
                            @csrf <button class="btn btn-success btn-sm"><i class="bi bi-send"></i> Submit to IQAC</button>
                        </form>
                    @endif
                    @if($access->isIqac() && in_array($audit->status, ['submitted', 'verified']))
                        <a class="btn btn-primary btn-sm" href="{{ route('audit.evaluation', $audit) }}"><i class="bi bi-check2-square"></i> Verify parameters</a>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="collapse" data-bs-target="#returnBox"><i class="bi bi-arrow-return-left"></i> Return to HOD</button>
                    @endif
                    @if($access->isIqac() && $audit->status === 'submitted')
                        <form method="post" action="{{ route('audit.workflow', [$audit, 'verify']) }}">@csrf <button class="btn btn-info btn-sm"><i class="bi bi-patch-check"></i> Mark verified &amp; send to auditors</button></form>
                    @endif
                    @if($access->canMark($audit))
                        <a class="btn btn-primary btn-sm" href="{{ route('audit.evaluation', $audit) }}"><i class="bi bi-pencil-square"></i> Enter my marks</a>
                    @endif
                    @if($access->isIqac() && $audit->status === 'audited')
                        <form method="post" action="{{ route('audit.workflow', [$audit, 'lock']) }}">@csrf <button class="btn btn-dark btn-sm"><i class="bi bi-lock"></i> Lock audit</button></form>
                        <form method="post" action="{{ route('audit.workflow', [$audit, 'reopen']) }}">@csrf <button class="btn btn-outline-secondary btn-sm">Reopen marking</button></form>
                    @endif
                    @if($access->isIqac() && $audit->status === 'locked')
                        <form method="post" action="{{ route('audit.workflow', [$audit, 'unlock']) }}">@csrf <button class="btn btn-outline-dark btn-sm"><i class="bi bi-unlock"></i> Unlock</button></form>
                    @endif
                </div>
                @if($access->isIqac())
                    <form id="returnBox" class="collapse mt-2" method="post" action="{{ route('audit.workflow', [$audit, 'return']) }}">
                        @csrf
                        <textarea name="remark" class="form-control form-control-sm mb-2" rows="3" placeholder="What should the department correct?" required></textarea>
                        <button class="btn btn-warning btn-sm">Return with remarks</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Summary of evaluation --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Summary of Evaluation</strong></div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Section</th><th>Details</th><th class="text-end">Max Marks</th><th class="text-end">Marks claimed</th><th class="text-end">Marks Awarded</th></tr></thead>
                <tbody>
                @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $s => $l)
                    <tr><td>{{ $s }}</td><td>{{ $l }}</td><td class="text-end">@marks($summary[$s]['max'])</td><td class="text-end">@marks($summary[$s]['claimed'])</td><td class="text-end">@marks($summary[$s]['awarded'])</td></tr>
                @endforeach
                <tr class="fw-bold"><td colspan="2" class="text-end">Total Marks</td><td class="text-end">@marks($summary['total']['max'])</td><td class="text-end">@marks($summary['total']['claimed'])</td><td class="text-end">@marks($summary['total']['awarded'])</td></tr>
                </tbody>
            </table>
        </div>

        @if($suggestions && $suggestions->isNotEmpty())
            <div class="card shadow-sm mb-3 border-success">
                <div class="card-header bg-white"><strong><i class="bi bi-lightbulb text-success"></i> Top actions to gain marks</strong>
                    <span class="small text-muted">— based on the evaluation scheme; enter only genuine activities with proof</span></div>
                <ul class="list-group list-group-flush small">
                    @foreach($suggestions->take(8) as $code => $sg)
                        @php($d = \Mmmut\AcademicAudit\Support\Catalogue::get($code))
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <span><a href="{{ $d['columns'] ? route('audit.items', [$audit, $code]) : route('audit.inputs', $audit) }}"><strong>{{ $code }}</strong> {{ \Illuminate\Support\Str::limit($d['title'], 60) }}</a><br>{{ $sg['message'] }}</span>
                            @if($sg['gain'] > 0 && ! $sg['missing'])<span class="badge bg-success align-self-start">+@marks($sg['gain'])</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($issues && $issues->isNotEmpty())
            <div class="card shadow-sm mb-3 border-warning">
                <div class="card-header bg-white"><strong><i class="bi bi-clipboard-pulse text-warning"></i> Data quality:</strong>
                    <span class="text-danger">{{ $issues->where('severity', 'error')->count() }} error(s)</span>, {{ $issues->where('severity', 'warning')->count() }} warning(s)</div>
                <ul class="list-group list-group-flush small">
                    @foreach($issues->take(12) as $i)
                        <li class="list-group-item {{ $i['severity'] === 'error' ? 'text-danger' : '' }}"><a href="{{ route('audit.items', [$audit, $i['code']]) }}"><strong>{{ $i['code'] }}</strong></a> {{ $i['message'] }}</li>
                    @endforeach
                    @if($issues->count() > 12)<li class="list-group-item text-muted">… and {{ $issues->count() - 12 }} more — open each parameter to see all.</li>@endif
                </ul>
            </div>
        @endif

        {{-- Parameters needing attention --}}
        @php($attention = $eval->filter(fn ($e) => $e['missing'] || ($pending[$e['parameter']->code] ?? 0) || $e['score']?->iqac_status === 'returned'))
        @if($attention->isNotEmpty())
            <div class="card shadow-sm mb-3 border-warning">
                <div class="card-header bg-white"><strong>Needs attention</strong></div>
                <ul class="list-group list-group-flush small">
                    @foreach($attention as $code => $e)
                        <li class="list-group-item">
                            <a href="{{ $e['definition']['columns'] ? route('audit.items', [$audit, $code]) : route('audit.inputs', $audit) }}"><strong>{{ $code }}</strong> {{ $e['parameter']->title }}</a>
                            @if($e['missing'])<span class="text-danger">— {{ $e['missing'] }}</span>@endif
                            @if($pending[$code] ?? 0)<span class="badge bg-warning text-dark">{{ $pending[$code] }} awaiting HOD approval</span>@endif
                            @if($e['score']?->iqac_status === 'returned')<span class="badge bg-danger">IQAC: {{ $e['score']->iqac_remark }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Annexures --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Annexures</strong> <span class="small text-muted">(upload PDF / Excel)</span></div>
            <ul class="list-group list-group-flush">
                @foreach(config('academic-audit.annexures') as $k => $label)
                    @php($slot = "ANX-{$k}")
                    <li class="list-group-item">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <div>{{ $label }}</div>
                                @foreach($attachments->get($slot, collect()) as $f)
                                    <div class="small">
                                        <a href="{{ route('audit.attachments.show', [$audit, $f]) }}"><i class="bi bi-paperclip"></i> {{ $f->original_name }}</a>
                                        @if($access->canEdit($audit))
                                            <form class="d-inline" method="post" action="{{ route('audit.attachments.destroy', [$audit, $f]) }}" onsubmit="return confirm('Remove file?')">@csrf @method('delete')<button class="btn btn-link btn-sm text-danger p-0 ms-1"><i class="bi bi-x-circle"></i></button></form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if($access->canEdit($audit))
                                <form method="post" enctype="multipart/form-data" action="{{ route('audit.attachments.store', $audit) }}" class="d-flex gap-1">
                                    @csrf <input type="hidden" name="slot" value="{{ $slot }}">
                                    <input type="file" name="files[]" multiple class="form-control form-control-sm" required>
                                    <button class="btn btn-sm btn-outline-primary">Upload</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Activity</strong></div>
            <ul class="list-group list-group-flush small">
                @forelse($logs as $l)
                    <li class="list-group-item"><span class="text-muted">{{ $l->created_at?->format('d-m-Y H:i') }}</span> · {{ $l->user?->name ?? 'System' }} · <strong>{{ $l->action }}</strong> {{ $l->detail }}</li>
                @empty
                    <li class="list-group-item text-muted">No activity yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
