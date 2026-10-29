@extends(config('academic-audit.layout'))
@section('title', 'Inputs')
@section('content')
@include('audit::partials.audit-header')
<div class="row">
    <div class="col-lg-3 d-none d-lg-block">@include('audit::partials.audit-nav', ['current' => 'inputs'])</div>
    <div class="col-lg-9">
        <h5>Denominators &amp; Yes/No items</h5>
        <p class="small text-muted">These single values are used in the formulas of the evaluation scheme. Parameters B.b, B.c, B.g and B.h are filled only here.</p>
        <form method="post" action="{{ route('audit.inputs.update', $audit) }}">
            @csrf
            <fieldset @disabled(! $canEdit)>
            <div class="card shadow-sm mb-3">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th style="width:55%">Input</th><th>Value</th><th class="small">Used in</th></tr></thead>
                    <tbody>
                    @php($anchors = ['int_completed' => 'Bb', 'feedback_hosted' => 'Bc', 'poco_submitted' => 'Bg', 'popso_submitted' => 'Bh'])
                    @foreach(\Mmmut\AcademicAudit\Support\Catalogue::SCALARS as $key => $s)
                        <tr @isset($anchors[$key]) id="{{ $anchors[$key] }}" @endisset>
                            <td><label for="s_{{ $key }}" class="small">{{ $s['label'] }}</label>
                                @if(isset($suggested[$key]))<div class="small text-muted">From faculty directory: <strong>{{ $suggested[$key] }}</strong></div>@endif</td>
                            <td>
                                @if($s['type'] === 'yn')
                                    <select id="s_{{ $key }}" name="{{ $key }}" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        <option value="Y" @selected(($values[$key] ?? '') === 'Y')>Yes</option>
                                        <option value="N" @selected(($values[$key] ?? '') === 'N')>No</option>
                                    </select>
                                @elseif($s['type'] === 'number')
                                    <input id="s_{{ $key }}" type="number" step="any" min="0" name="{{ $key }}" value="{{ $values[$key] ?? '' }}" class="form-control form-control-sm" @if($key === 'regular_faculty') placeholder="{{ $resolved['faculty'] }}" @endif>
                                @else
                                    <input id="s_{{ $key }}" type="text" name="{{ $key }}" value="{{ $values[$key] ?? '' }}" class="form-control form-control-sm">
                                @endif
                            </td>
                            <td class="small text-muted">{{ $s['used_by'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            </fieldset>
            @if($canEdit)<button class="btn btn-primary">Save inputs</button>@endif
        </form>
        <p class="small text-muted mt-3">Regular faculty currently used for Section C ratios: <strong>{{ $resolved['faculty'] }}</strong>.</p>
    </div>
</div>
@endsection
