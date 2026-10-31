@extends(config('academic-audit.layout'))
@section('title', 'Notices')
@section('content')
<h4>Notices <span class="small text-muted fw-normal">— scrolling notices on the login page and dashboard</span></h4>

@include('audit::partials.notice-ticker', ['notices' => \Mmmut\AcademicAudit\Models\Notice::visible('dashboard')->get()])

<div class="card shadow-sm mb-3">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>#</th><th>Notice</th><th>Shown on</th><th>From</th><th>Till</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($notices as $n)
            <tr class="{{ $n->is_active ? '' : 'text-muted' }}">
                <td>{{ $n->sort_order }}</td>
                <td>
                    @if($n->is_new)<span class="badge bg-danger">NEW</span>@endif
                    {{ $n->title }}
                    @if($n->attachment_path)<br><a class="small" href="{{ route('audit.notices.file', $n) }}" target="_blank"><i class="bi bi-paperclip"></i> {{ $n->attachment_name }}</a>@endif
                    @if($n->link_url)<br><a class="small" href="{{ $n->link_url }}" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> {{ \Illuminate\Support\Str::limit($n->link_url, 60) }}</a>@endif
                </td>
                <td class="small">{{ \Mmmut\AcademicAudit\Models\Notice::PLACES[$n->show_on] }}</td>
                <td class="small">{{ $n->starts_on?->format('d-m-Y') ?? '—' }}</td>
                <td class="small">{{ $n->ends_on?->format('d-m-Y') ?? '—' }}</td>
                <td>
                    @if(! $n->is_active)<span class="badge bg-secondary">Hidden</span>
                    @elseif($n->isVisible())<span class="badge bg-success">Showing</span>
                    @else<span class="badge bg-warning text-dark">Outside dates</span>@endif
                </td>
                <td class="text-nowrap">
                    <a class="btn btn-sm btn-outline-primary py-0" href="{{ route('audit.admin.notices', ['edit' => $n->id]) }}#form">Edit</a>
                    <form class="d-inline" method="post" action="{{ route('audit.admin.notices.toggle', $n) }}">@csrf <button class="btn btn-sm btn-outline-secondary py-0">{{ $n->is_active ? 'Hide' : 'Show' }}</button></form>
                    <form class="d-inline" method="post" action="{{ route('audit.admin.notices.destroy', $n) }}" onsubmit="return confirm('Delete this notice?')">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger py-0">Delete</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-3">No notices yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card shadow-sm" id="form" style="max-width: 900px">
    <div class="card-header bg-white"><strong>{{ $editing ? 'Edit notice' : 'Add notice' }}</strong></div>
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('audit.admin.notices.update', $editing) : route('audit.admin.notices.store') }}">
            @csrf @if($editing) @method('put') @endif
            <div class="mb-2"><label class="form-label">Notice text</label>
                <textarea name="title" rows="2" class="form-control form-control-sm" required>{{ old('title', $editing?->title) }}</textarea></div>
            <div class="row g-2 mb-2">
                <div class="col-md-6"><label class="form-label">Link (optional)</label>
                    <input name="link_url" value="{{ old('link_url', $editing?->link_url) }}" class="form-control form-control-sm" placeholder="https://"></div>
                <div class="col-md-6"><label class="form-label">Attachment (optional, PDF / image / Office file)</label>
                    <input type="file" name="attachment" class="form-control form-control-sm">
                    @if($editing?->attachment_path)
                        <div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="rmAtt"><label class="form-check-label" for="rmAtt">Remove current file ({{ $editing->attachment_name }})</label></div>
                    @endif
                    <div class="form-text">If both are given, the attachment opens when the notice is clicked.</div></div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-md-4"><label class="form-label">Show on</label>
                    <select name="show_on" class="form-select form-select-sm">@foreach(\Mmmut\AcademicAudit\Models\Notice::PLACES as $k => $l)<option value="{{ $k }}" @selected(old('show_on', $editing?->show_on ?? 'both') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Show from</label><input type="date" name="starts_on" value="{{ old('starts_on', $editing?->starts_on?->format('Y-m-d')) }}" class="form-control form-control-sm"></div>
                <div class="col-md-3"><label class="form-label">Show till</label><input type="date" name="ends_on" value="{{ old('ends_on', $editing?->ends_on?->format('Y-m-d')) }}" class="form-control form-control-sm"></div>
                <div class="col-md-2"><label class="form-label">Order</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order', $editing?->sort_order ?? 0) }}" class="form-control form-control-sm"></div>
            </div>
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $editing?->is_active ?? true))><label class="form-check-label" for="act">Active</label></div>
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_new" value="1" id="new" @checked(old('is_new', $editing?->is_new))><label class="form-check-label" for="new">Show blinking "NEW"</label></div>
            <div class="mt-3"><button class="btn btn-primary btn-sm">{{ $editing ? 'Save notice' : 'Add notice' }}</button>
                @if($editing)<a href="{{ route('audit.admin.notices') }}" class="btn btn-link btn-sm">Cancel</a>@endif</div>
        </form>
    </div>
</div>
@endsection
