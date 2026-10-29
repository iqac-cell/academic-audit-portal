@extends(config('academic-audit.layout'))
@section('title', $code.' · '.$audit->department->name)
@section('content')
@include('audit::partials.audit-header')
<div class="row">
    <div class="col-lg-3 d-none d-lg-block">@include('audit::partials.audit-nav', ['current' => $code])</div>
    <div class="col-lg-9">
        <h5 class="mb-1">Section {{ $def['section'] }} ({{ $def['letter'] }}) {{ $param->title }} <span class="badge bg-secondary">{{ \Mmmut\AcademicAudit\Support\Fmt::marks($param->max_marks) }} Marks</span></h5>
        @if($def['annexure'])<p class="small text-muted mb-2">Provide detailed information in Annexure {{ $def['annexure'] }} (upload on the Overview page).</p>@endif
        @if($facultyView)
            <div class="alert alert-info py-2 small">You are adding your own records. They reach the department total after the HOD approves them.</div>
        @endif

        @include('audit::partials.metric-box', ['e' => $eval])

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong>{{ $facultyView ? 'My entries' : 'Entries' }} ({{ $entries->count() }})</strong>
                @if($canReview)
                    <div class="d-flex flex-wrap gap-1">
                        @if($prefillName)
                            <form method="post" action="{{ route('audit.items.prefill', [$audit, $code]) }}">@csrf <button class="btn btn-sm btn-outline-success"><i class="bi bi-magic"></i> Prefill from {{ $prefillName }}</button></form>
                        @endif
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('audit.items.template', [$audit, $code]) }}"><i class="bi bi-download"></i> Excel template</a>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#importBox"><i class="bi bi-upload"></i> Import Excel</button>
                    </div>
                @endif
            </div>
            @if($canReview)
                <div id="importBox" class="collapse border-bottom p-2 bg-light">
                    <form method="post" enctype="multipart/form-data" action="{{ route('audit.items.import', [$audit, $code]) }}" class="d-flex gap-2 align-items-center">
                        @csrf
                        <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm" required>
                        <button class="btn btn-sm btn-primary">Import rows</button>
                        <span class="small text-muted">Use the template; first row = headings. Rows are added to existing ones.</span>
                    </form>
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>SN</th>
                        @foreach($def['columns'] as $c)<th class="small">{{ $c['label'] }}</th>@endforeach
                        <th class="small">Proof</th><th class="small">Status</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $i => $row)
                        <tr class="{{ $row->status === 'pending' ? 'table-warning' : ($row->status === 'rejected' ? 'table-danger' : '') }}">
                            <td>{{ $i + 1 }}</td>
                            @foreach($def['columns'] as $c)
                                @php($v = \Mmmut\AcademicAudit\Support\ItemForm::display($c, $row->data[$c['key']] ?? null))
                                <td class="small">
                                    @if($c['type'] === 'url' && $v)<a href="{{ $v }}" target="_blank" rel="noopener">link</a>@else{{ $v }}@endif
                                </td>
                            @endforeach
                            <td class="small">@if($row->proof_path)<a href="{{ route('audit.entries.proof', [$audit, $row]) }}"><i class="bi bi-paperclip"></i></a>@endif</td>
                            <td class="small">
                                @if($row->status === 'pending')<span class="badge bg-warning text-dark">Awaiting HOD</span>
                                @elseif($row->status === 'rejected')<span class="badge bg-danger">Rejected</span>
                                @else<span class="badge bg-success">Included</span>@endif
                                @if($row->source !== 'manual')<span class="badge bg-light text-dark border">{{ $row->source === 'iqac' ? 'prefilled' : $row->source }}</span>@endif
                                @if($row->remark)<div class="text-muted">{{ $row->remark }}</div>@endif
                            </td>
                            <td class="text-nowrap">
                                @if($canReview && $row->status !== 'approved')
                                    <form class="d-inline" method="post" action="{{ route('audit.entries.review', [$audit, $row]) }}">@csrf <input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success py-0" title="Approve"><i class="bi bi-check-lg"></i></button></form>
                                @endif
                                @if($canReview && $row->status === 'pending')
                                    <form class="d-inline" method="post" action="{{ route('audit.entries.review', [$audit, $row]) }}" onsubmit="const r = prompt('Reason for rejection'); if (r === null) return false; this.remark.value = r;">@csrf <input type="hidden" name="decision" value="reject"><input type="hidden" name="remark"><button class="btn btn-sm btn-outline-danger py-0" title="Reject"><i class="bi bi-x-lg"></i></button></form>
                                @endif
                                @if($canReview || ($canAdd && $row->status !== 'approved'))
                                    <a class="btn btn-sm btn-outline-primary py-0" href="{{ route('audit.items', [$audit, $code, 'edit' => $row->id]) }}#form" title="Edit"><i class="bi bi-pencil"></i></a>
                                    <form class="d-inline" method="post" action="{{ route('audit.entries.destroy', [$audit, $row]) }}" onsubmit="return confirm('Delete this row?')">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger py-0" title="Delete"><i class="bi bi-trash"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($def['columns']) + 4 }}" class="text-center text-muted py-3">No entries yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($canAdd || $editing)
            <div class="card shadow-sm mb-3" id="form">
                <div class="card-header bg-white"><strong>{{ $editing ? 'Edit row' : 'Add row' }}</strong></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('audit.entries.update', [$audit, $editing]) : route('audit.items.store', [$audit, $code]) }}">
                        @csrf
                        @if($editing) @method('put') @endif
                        <div class="row g-2">
                            @foreach($def['columns'] as $i => $c)
                                @php($default = $editing ? ($editing->data[$c['key']] ?? null) : ($facultyView && $c['key'] === 'faculty' ? $access->faculty()?->display_name : null))
                                <div class="{{ $c['type'] === 'textarea' ? 'col-12' : 'col-md-4' }}">
                                    <label class="form-label" for="f_{{ $c['key'] }}">{{ $c['label'] }} @if($i === 0)<span class="text-danger">*</span>@endif</label>
                                    @include('audit::partials.field', ['value' => old("data.{$c['key']}", $default)])
                                </div>
                            @endforeach
                            <div class="col-md-4">
                                <label class="form-label">Proof document (optional)</label>
                                <input type="file" name="proof" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-primary btn-sm">{{ $editing ? 'Save changes' : 'Add row' }}</button>
                            @if($editing)<a href="{{ route('audit.items', [$audit, $code]) }}" class="btn btn-link btn-sm">Cancel</a>@endif
                        </div>
                    </form>
                </div>
            </div>
            <datalist id="dl_programs">@foreach($programs as $p)<option value="{{ $p }}">@endforeach</datalist>
            <datalist id="dl_faculty">@foreach($facultyNames as $f)<option value="{{ $f }}">@endforeach</datalist>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-white"><strong>Supporting documents for this parameter</strong></div>
            <div class="card-body small">
                @forelse($attachments as $f)
                    <div><a href="{{ route('audit.attachments.show', [$audit, $f]) }}"><i class="bi bi-paperclip"></i> {{ $f->original_name }}</a>
                        @if($canReview)<form class="d-inline" method="post" action="{{ route('audit.attachments.destroy', [$audit, $f]) }}">@csrf @method('delete')<button class="btn btn-link btn-sm text-danger p-0 ms-1"><i class="bi bi-x-circle"></i></button></form>@endif
                    </div>
                @empty
                    <div class="text-muted">No documents uploaded.</div>
                @endforelse
                @if($canReview)
                    <form method="post" enctype="multipart/form-data" action="{{ route('audit.attachments.store', $audit) }}" class="d-flex gap-2 mt-2">
                        @csrf <input type="hidden" name="slot" value="{{ $code }}">
                        <input type="file" name="files[]" multiple class="form-control form-control-sm" required>
                        <button class="btn btn-sm btn-outline-primary">Upload</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
