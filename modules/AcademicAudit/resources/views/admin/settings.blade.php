@extends(config('academic-audit.layout'))
@section('title', 'Portal settings')
@section('content')
<h4>Portal settings</h4>
<ul class="nav nav-tabs mb-3">
    @foreach(\Mmmut\AcademicAudit\Support\SettingsRegistry::GROUPS as $g => [$label, $icon])
        <li class="nav-item"><a class="nav-link {{ $g === $group ? 'active' : '' }}" href="{{ route('audit.admin.settings', ['tab' => $g]) }}"><i class="bi {{ $icon }}"></i> {{ $label }}</a></li>
    @endforeach
</ul>

@if($group === 'mail' && ! $settings->get('mail.enabled'))
    <div class="alert alert-secondary small py-2">Email is <strong>off</strong>. Notifications and password reset by OTP need email to be on.</div>
@endif
@if($group === 'ai')
    <div class="alert alert-info small py-2">
        <strong>How AI assistance works.</strong> Data quality checks, marks suggestions and previous-year prefill run inside the portal (no internet, no data leaves the server).
        "Fill from document" sends the uploaded PDF/image to Anthropic's Claude API to read it; the user always reviews the values before saving. Suggestions only explain the evaluation scheme — data entered must be genuine and backed by proof.
        All features are off until switched on here.
    </div>
@endif

<div class="card shadow-sm" style="max-width: 900px">
    <div class="card-body">
        <form method="post" action="{{ route('audit.admin.settings.update', $group) }}">
            @csrf
            @foreach($fields as $f)
                @php($name = str_replace('.', '__', $f['key']))
                @php($value = $settings->get($f['key']))
                <div class="mb-3 row">
                    @if($f['type'] === 'bool')
                        <div class="col-md-8 offset-md-4">
                            <div class="form-check form-switch">
                                <input type="hidden" name="{{ $name }}" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="{{ $name }}" name="{{ $name }}" value="1" @checked($value)>
                                <label class="form-check-label" for="{{ $name }}">{{ $f['label'] }}</label>
                            </div>
                            @if($f['help'])<div class="form-text">{{ $f['help'] }}</div>@endif
                        </div>
                    @else
                        <label class="col-md-4 col-form-label col-form-label-sm" for="{{ $name }}">{{ $f['label'] }}</label>
                        <div class="col-md-8">
                            @switch($f['type'])
                                @case('select')
                                    <select id="{{ $name }}" name="{{ $name }}" class="form-select form-select-sm">
                                        @foreach($f['options'] as $k => $l)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $l }}</option>@endforeach
                                    </select>
                                    @break
                                @case('textarea')
                                    <textarea id="{{ $name }}" name="{{ $name }}" rows="3" class="form-control form-control-sm">{{ $value }}</textarea>
                                    @break
                                @case('password')
                                    <input id="{{ $name }}" type="password" name="{{ $name }}" autocomplete="new-password" class="form-control form-control-sm"
                                           placeholder="{{ $settings->isSet($f['key']) ? '•••••••• saved — leave blank to keep' : 'not set' }}">
                                    @if($settings->isSet($f['key']))
                                        <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="{{ $name }}__clear" value="1" id="{{ $name }}__clear"><label class="form-check-label small" for="{{ $name }}__clear">Remove saved value</label></div>
                                    @endif
                                    @break
                                @case('number')
                                    <input id="{{ $name }}" type="number" min="0" name="{{ $name }}" value="{{ $value }}" class="form-control form-control-sm" style="max-width: 180px">
                                    @break
                                @default
                                    <input id="{{ $name }}" type="{{ $f['type'] === 'email' ? 'email' : 'text' }}" name="{{ $name }}" value="{{ $value }}" class="form-control form-control-sm">
                            @endswitch
                            @if($f['help'])<div class="form-text">{{ $f['help'] }}</div>@endif
                        </div>
                    @endif
                </div>
            @endforeach
            <button class="btn btn-primary btn-sm">Save settings</button>
        </form>

        @if($group === 'mail')
            <hr>
            <form method="post" action="{{ route('audit.admin.settings.test-mail') }}">@csrf
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-send"></i> Send test mail to {{ auth()->user()->email }}</button>
            </form>
        @endif
        @if($group === 'ai')
            <hr>
            <form method="post" action="{{ route('audit.admin.settings.test-ai') }}">@csrf
                <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-plug"></i> Test Claude API connection</button>
                <span class="small text-muted ms-2">Needs outbound HTTPS from the server to api.anthropic.com.</span>
            </form>
        @endif
    </div>
</div>

@if($group === 'ai' && $usage->isNotEmpty())
    <div class="card shadow-sm mt-3" style="max-width: 900px">
        <div class="card-header bg-white"><strong>Document auto-fill usage (last 30 days)</strong></div>
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>User</th><th class="text-end">Requests</th><th class="text-end">Errors</th><th class="text-end">Input tokens</th><th class="text-end">Output tokens</th></tr></thead>
            <tbody>
            @foreach($usage as $u)
                <tr><td>{{ $usageUsers[$u->user_id] ?? $u->user_id }}</td><td class="text-end">{{ $u->calls }}</td><td class="text-end">{{ $u->errors }}</td><td class="text-end">{{ number_format($u->input_tokens) }}</td><td class="text-end">{{ number_format($u->output_tokens) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
