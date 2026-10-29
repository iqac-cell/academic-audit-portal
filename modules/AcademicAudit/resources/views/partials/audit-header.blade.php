<div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <div>
        <h5 class="mb-0">Department of {{ $audit->department->name }}</h5>
        <div class="text-muted small">Academic Year {{ $audit->cycle->academic_year }} · @include('audit::partials.status')</div>
    </div>
    <div class="btn-group btn-group-sm">
        <a class="btn btn-outline-secondary" href="{{ route('audit.report', $audit) }}" target="_blank"><i class="bi bi-printer"></i> Report</a>
        <a class="btn btn-outline-secondary" href="{{ route('audit.report.pdf', $audit) }}"><i class="bi bi-filetype-pdf"></i> PDF</a>
        <a class="btn btn-outline-secondary" href="{{ route('audit.report.xlsx', $audit) }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    </div>
</div>
@if($audit->status === 'returned' && $audit->iqac_remark)
    <div class="alert alert-warning py-2"><strong>Returned by IQAC:</strong> <span class="text-pre">{{ $audit->iqac_remark }}</span></div>
@endif
