@extends(config('academic-audit.layout'))
@section('title', 'Basic Information')
@section('content')
@include('audit::partials.audit-header')
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

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>d) Programs offered</strong> <span class="small text-muted">— these names appear as choices in program-wise tables</span></div>
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>SN</th><th>Name of Program</th><th>Level</th><th>Approved Intake</th><th>Year of Starting</th></tr></thead>
                    <tbody>
                    @php($programs = array_values($info['programs'] ?? []))
                    @for($i = 0; $i < max(count($programs) + 2, 4); $i++)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><input name="programs[{{ $i }}][name]" value="{{ $programs[$i]['name'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. B.Tech. Civil Engineering"></td>
                            <td><select name="programs[{{ $i }}][level]" class="form-select form-select-sm">
                                    @foreach($levels as $k => $l)<option value="{{ $k }}" @selected(($programs[$i]['level'] ?? '') === $k)>{{ $k }}</option>@endforeach
                                </select></td>
                            <td><input type="number" min="0" name="programs[{{ $i }}][intake]" value="{{ $programs[$i]['intake'] ?? '' }}" class="form-control form-control-sm"></td>
                            <td><input type="number" name="programs[{{ $i }}][year]" value="{{ $programs[$i]['year'] ?? '' }}" class="form-control form-control-sm"></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>e) Faculty Information</strong></div>
                <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>SN</th><th>Name of Faculty</th><th>Highest Qualification</th><th>Years of Experience</th><th>Date of Joining University</th></tr></thead>
                    <tbody>
                    @php($fac = array_values($info['faculty'] ?? []))
                    @for($i = 0; $i < count($fac) + 2; $i++)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><input name="faculty[{{ $i }}][name]" value="{{ $fac[$i]['name'] ?? '' }}" class="form-control form-control-sm"></td>
                            <td><input name="faculty[{{ $i }}][qualification]" value="{{ $fac[$i]['qualification'] ?? '' }}" class="form-control form-control-sm"></td>
                            <td><input type="number" min="0" step="any" name="faculty[{{ $i }}][experience]" value="{{ $fac[$i]['experience'] ?? '' }}" class="form-control form-control-sm"></td>
                            <td><input name="faculty[{{ $i }}][doj]" value="{{ $fac[$i]['doj'] ?? '' }}" class="form-control form-control-sm" placeholder="dd-mm-yyyy"></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><strong>f) Student Information</strong></div>
                <div class="card-body">
                    <h6 class="small fw-semibold">i) Sanctioned Intake Approved by Governing Body</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>Program</th><th>Approved Governing body (AICTE/PCI/University, etc)</th><th>Total Students as per sanctioned Intake (TS)</th></tr></thead>
                        <tbody>
                        @foreach(['UG', 'PG'] as $lvl)
                            <tr>
                                <td>{{ $levels[$lvl] }}</td>
                                <td><input name="sanctioned[{{ $lvl }}][body]" value="{{ $info['sanctioned'][$lvl]['body'] ?? '' }}" class="form-control form-control-sm"></td>
                                <td><input type="number" min="0" name="sanctioned[{{ $lvl }}][ts]" value="{{ $info['sanctioned'][$lvl]['ts'] ?? '' }}" class="form-control form-control-sm"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p class="small text-muted">B.Tech. total students = sanctioned intake including lateral entry for second, third and final year. For other programs, total students for all years including lateral entry.</p>

                    <h6 class="small fw-semibold">ii) Students Enrolled <span class="text-muted">(detailed list as Annexure I)</span></h6>
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>Program</th>@foreach($years as $y)<th>{{ $y }}</th>@endforeach<th>Total</th></tr></thead>
                        <tbody>
                        @foreach($levels as $lvl => $label)
                            @php($en = $info['enrolled'][$lvl] ?? [])
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach($years as $y)
                                    <td><input type="number" min="0" name="enrolled[{{ $lvl }}][{{ $y }}]" value="{{ $en[$y] ?? '' }}" class="form-control form-control-sm"></td>
                                @endforeach
                                <td class="fw-semibold">{{ array_sum(array_map('floatval', $en)) ?: '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            </fieldset>
            @if($canEdit)<button class="btn btn-primary">Save basic information</button>@endif
        </form>
    </div>
</div>
@endsection
