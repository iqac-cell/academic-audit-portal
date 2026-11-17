@extends(config('academic-audit.layout'))
@section('title', 'Basic Information')
@section('content')
@include('audit::partials.audit-header')
@include('audit::partials.repeat-rows')
<div class="row">
    <div class="col-lg-3 d-none d-lg-block">@include('audit::partials.audit-nav', ['current' => 'basic'])</div>
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Basic Information</h5>
            @if($canEdit)
                <form method="post" action="{{ route('audit.basic.prefill', $audit) }}">@csrf <button class="btn btn-sm btn-outline-success"><i class="bi bi-magic"></i> Fill empty fields from directory</button></form>
            @endif
        </div>
        <form method="post" action="{{ route('audit.basic.update', $audit) }}">
            @csrf
            <fieldset @disabled(! $canEdit)>
            <div class="card shadow-sm mb-3"><div class="card-body">
                <label class="form-label">a) About the Department (max 150 words)</label>
                <textarea name="about" rows="4" class="form-control form-control-sm mb-2">{{ old('about', $info['about'] ?? '') }}</textarea>
                <label class="form-label">b) Vision</label>
                <textarea name="vision" rows="2" class="form-control form-control-sm mb-2">{{ old('vision', $info['vision'] ?? '') }}</textarea>
                <label class="form-label">c) Mission</label>
                <textarea name="mission" rows="3" class="form-control form-control-sm">{{ old('mission', $info['mission'] ?? '') }}</textarea>
            </div></div>

            @php($row = fn ($kind, $i, $r = []) => view('audit::partials.basic-row', ['kind' => $kind, 'i' => $i, 'row' => $r, 'levels' => $levels, 'years' => $years]))
            @php($addBtn = '<button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i class="bi bi-plus-lg"></i> Add row</button>')
            <datalist id="aa-program-names">
                @foreach(collect($info['programs'])->pluck('name')->filter()->merge(array_values($levels))->unique() as $n)<option value="{{ $n }}">@endforeach
            </datalist>

            <div class="card shadow-sm mb-3" data-repeat data-next="{{ max(count($info['programs']), 1) }}">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span><strong>d) Programs offered</strong> <span class="small text-muted">— these names appear as choices in program-wise tables</span></span>
                    @if($canEdit){!! $addBtn !!}@endif
                </div>
                <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>SN</th><th>Name of Program</th><th>Level</th><th>Approved Intake</th><th>Year of Starting</th><th></th></tr></thead>
                    <tbody>
                    @forelse($info['programs'] as $i => $p){!! $row('programs', $i, $p) !!}@empty{!! $row('programs', 0) !!}@endforelse
                    </tbody>
                </table>
                </div>
                <template>{!! $row('programs', '__i__') !!}</template>
            </div>

            <div class="card shadow-sm mb-3" data-repeat data-next="{{ max(count($info['faculty']), 1) }}">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>e) Faculty Information</strong>
                    @if($canEdit){!! $addBtn !!}@endif
                </div>
                <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>SN</th><th>Name of Faculty</th><th>Highest Qualification</th><th>Years of Experience</th><th>Date of Joining University</th><th></th></tr></thead>
                    <tbody>
                    @forelse($info['faculty'] as $i => $f){!! $row('faculty', $i, $f) !!}@empty{!! $row('faculty', 0) !!}@endforelse
                    </tbody>
                </table>
                </div>
                <template>{!! $row('faculty', '__i__') !!}</template>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>f) Student Information</strong></div>
                <div class="card-body">
                    @php($sanctioned = \Mmmut\AcademicAudit\Support\BasicInfo::formSanctioned($info))
                    <div data-repeat data-next="{{ count($sanctioned) }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="small fw-semibold mb-0">i) Sanctioned Intake Approved by Governing Body</h6>
                            @if($canEdit){!! $addBtn !!}@endif
                        </div>
                        <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light"><tr><th>SN</th><th>Program</th><th>Approved Governing body (AICTE/PCI/University, etc)</th><th>Total Students as per sanctioned Intake (TS)</th><th></th></tr></thead>
                            <tbody>
                            @foreach($sanctioned as $i => $r){!! $row('sanctioned', $i, $r) !!}@endforeach
                            </tbody>
                        </table>
                        </div>
                        <template>{!! $row('sanctioned', '__i__') !!}</template>
                    </div>
                    <p class="small text-muted">B.Tech. total students = sanctioned intake including lateral entry for second, third and final year. For other programs, total students for all years including lateral entry. Add one row per program or programme group as needed.</p>

                    @php($enrolled = \Mmmut\AcademicAudit\Support\BasicInfo::formEnrolled($info))
                    <div data-repeat data-next="{{ count($enrolled) }}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="small fw-semibold mb-0">ii) Students Enrolled <span class="text-muted">(detailed list as Annexure I)</span></h6>
                            @if($canEdit){!! $addBtn !!}@endif
                        </div>
                        <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light"><tr><th>SN</th><th>Program</th>@foreach($years as $y)<th>{{ $y }}</th>@endforeach<th>Total</th><th></th></tr></thead>
                            <tbody>
                            @foreach($enrolled as $i => $r){!! $row('enrolled', $i, $r) !!}@endforeach
                            </tbody>
                        </table>
                        </div>
                        <template>{!! $row('enrolled', '__i__') !!}</template>
                    </div>
                </div>
            </div>
            </fieldset>
            @if($canEdit)<button class="btn btn-primary">Save basic information</button>@endif
        </form>
    </div>
</div>
@endsection
