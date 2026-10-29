@extends(config('academic-audit.layout'))
@section('title', 'Evaluation')
@section('content')
@include('audit::partials.audit-header')
<div class="row">
    <div class="col-xl-2 d-none d-xl-block">@include('audit::partials.audit-nav', ['current' => 'evaluation'])</div>
    <div class="col-xl-10">
        <h5>Evaluation</h5>

        @if($access->isIqac())
            <div class="card shadow-sm mb-3">
                <div class="card-body py-2">
                    <form method="post" action="{{ route('audit.auditors', $audit) }}" class="row g-2 align-items-end">
                        @csrf
                        @foreach([1, 2] as $n)
                            <div class="col-md-4">
                                <label class="form-label">Academic auditor - {{ $n }}</label>
                                <select name="auditor{{ $n }}_user_id" class="form-select form-select-sm" @disabled(in_array($audit->status, ['audited', 'locked']))>
                                    <option value="">— not assigned —</option>
                                    @foreach($auditors as $u)<option value="{{ $u->id }}" @selected($audit->{"auditor{$n}_user_id"} == $u->id)>{{ $u->name }} ({{ $u->email }})</option>@endforeach
                                </select>
                            </div>
                        @endforeach
                        <div class="col-md-4"><button class="btn btn-sm btn-outline-primary" @disabled(in_array($audit->status, ['audited', 'locked']))>Assign auditors</button>
                            @if($auditors->isEmpty())<div class="small text-danger">No user has the Auditor role yet (Administration → Users).</div>@endif
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($canMark)
            <div class="alert alert-primary py-2 small">
                You are <strong>Auditor-{{ $slot }}</strong>. Enter marks for every parameter (you may use the claimed marks as reference), save, and then finalise.
                @if($audit->{"auditor{$slot}_done_at"})<strong>You finalised your marks on {{ $audit->{"auditor{$slot}_done_at"}->format('d-m-Y H:i') }}.</strong>@endif
            </div>
        @endif

        <form id="f_claims" method="post" action="{{ route('audit.evaluation.claims', $audit) }}">@csrf</form>
        <form id="f_verify" method="post" action="{{ route('audit.evaluation.verify', $audit) }}">@csrf</form>
        <form id="f_marks" method="post" action="{{ route('audit.evaluation.marks', $audit) }}">@csrf</form>

        <div class="card shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>SN</th><th>Parameter</th><th class="text-end">Max Mark</th><th>Indicator</th><th class="text-end">Marks Claimed</th>
                        <th>IQAC verification</th>
                        <th class="text-end">Auditor-1</th><th class="text-end">Auditor-2</th><th class="text-end">Marks Awarded</th><th>Remark</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SECTIONS as $sec => $label)
                        <tr class="table-secondary"><th colspan="10" class="text-center">SECTION {{ $sec }}: {{ strtoupper($label) }}</th></tr>
                        @foreach($eval->filter(fn ($e) => $e['parameter']->section === $sec) as $code => $e)
                            @php($s = $e['score'])
                            @php($claimed = $audit->isEditable() ? $e['claimed'] : ($s?->claimed_marks ?? $e['claimed']))
                            <tr>
                                <td>{{ $e['definition']['letter'] }}</td>
                                <td class="small">
                                    <a href="{{ $e['definition']['columns'] ? route('audit.items', [$audit, $code]) : route('audit.inputs', $audit) }}" class="text-decoration-none">{{ $e['parameter']->title }}</a>
                                    @if($e['definition']['columns'])<span class="text-muted">({{ $e['rows'] }} rows)</span>@endif
                                </td>
                                <td class="text-end">@marks($e['parameter']->max_marks)</td>
                                <td class="small text-nowrap">{{ $e['definition']['metric']['symbol'] }}: {{ \Mmmut\AcademicAudit\Support\Fmt::indicator($e) }}</td>
                                <td class="text-end" style="width:90px">
                                    @if($e['parameter']->scoring === 'manual' && $canClaim)
                                        <input form="f_claims" type="number" step="0.5" min="0" max="{{ $e['parameter']->max_marks }}" name="claim[{{ $code }}]" value="{{ $s?->claimed_marks }}" class="form-control form-control-sm text-end">
                                    @else
                                        @marks($claimed)
                                    @endif
                                </td>
                                <td style="min-width:180px">
                                    @if($canVerify)
                                        <select form="f_verify" name="status[{{ $code }}]" class="form-select form-select-sm mb-1">
                                            <option value="">—</option>
                                            <option value="verified" @selected($s?->iqac_status === 'verified')>Verified</option>
                                            <option value="returned" @selected($s?->iqac_status === 'returned')>Needs correction</option>
                                        </select>
                                        <input form="f_verify" name="remark[{{ $code }}]" value="{{ $s?->iqac_remark }}" class="form-control form-control-sm" placeholder="Remark">
                                    @else
                                        @if($s?->iqac_status === 'verified')<span class="badge bg-success">Verified</span>@elseif($s?->iqac_status === 'returned')<span class="badge bg-danger">Needs correction</span>@endif
                                        <div class="small text-muted">{{ $s?->iqac_remark }}</div>
                                    @endif
                                </td>
                                @foreach([1, 2] as $n)
                                    <td class="text-end" style="width:90px">
                                        @if($canMark && $slot === $n && ! $audit->{"auditor{$n}_done_at"})
                                            <input form="f_marks" type="number" step="0.5" min="0" max="{{ $e['parameter']->max_marks }}" name="marks[{{ $code }}]" value="{{ $s?->{"auditor{$n}_marks"} }}" class="form-control form-control-sm text-end" placeholder="{{ \Mmmut\AcademicAudit\Support\Fmt::marks($claimed) }}">
                                        @elseif($access->isIqac() || $slot === $n || in_array($audit->status, ['audited', 'locked']))
                                            @marks($s?->{"auditor{$n}_marks"})
                                        @else
                                            <span class="text-muted small">{{ $s?->{"auditor{$n}_marks"} !== null ? 'entered' : '—' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-end fw-semibold">@marks($s?->awarded_marks)</td>
                                <td style="min-width:160px">
                                    @if($canMark && ! $audit->{"auditor{$slot}_done_at"})
                                        <input form="f_marks" name="remark[{{ $code }}]" value="{{ $s?->{"auditor{$slot}_remark"} }}" class="form-control form-control-sm">
                                    @else
                                        <div class="small">{{ $s?->auditor1_remark }}@if($s?->auditor1_remark && $s?->auditor2_remark) · @endif{{ $s?->auditor2_remark }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr class="fw-semibold"><td colspan="2" class="text-end">Sub-Total Marks ({{ $sec }})</td><td class="text-end">@marks($summary[$sec]['max'])</td><td></td><td class="text-end">@marks($summary[$sec]['claimed'])</td><td colspan="3"></td><td class="text-end">@marks($summary[$sec]['awarded'])</td><td></td></tr>
                    @endforeach
                    <tr class="fw-bold table-light"><td colspan="2" class="text-end">Total Marks</td><td class="text-end">@marks($summary['total']['max'])</td><td></td><td class="text-end">@marks($summary['total']['claimed'])</td><td colspan="3"></td><td class="text-end">@marks($summary['total']['awarded'])</td><td></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if($canClaim)<button form="f_claims" class="btn btn-sm btn-primary">Save claimed marks (manual parameters)</button>@endif
            @if($canVerify)<button form="f_verify" class="btn btn-sm btn-primary">Save verification</button>@endif
            @if($canMark && ! $audit->{"auditor{$slot}_done_at"})
                <button form="f_marks" class="btn btn-sm btn-primary">Save my marks</button>
                <form method="post" action="{{ route('audit.workflow', [$audit, 'auditor-done']) }}" onsubmit="return confirm('Finalise your marks? You will not be able to change them.')">@csrf <button class="btn btn-sm btn-success">Finalise my marks</button></form>
            @endif
        </div>
        @if($audit->status === 'verified' || $audit->status === 'audited')
            <p class="small text-muted mt-2">Auditor-1: {{ $audit->auditor1?->name ?? '—' }} {{ $audit->auditor1_done_at ? '✔ finalised' : '' }} · Auditor-2: {{ $audit->auditor2?->name ?? '—' }} {{ $audit->auditor2_done_at ? '✔ finalised' : '' }} · Awarded = {{ $audit->cycle->awarding_rule }} of both auditors.</p>
        @endif
    </div>
</div>
@endsection
