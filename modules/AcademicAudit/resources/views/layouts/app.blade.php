<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Academic Audit') · {{ $settings->get('general.portal_title') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <style>
        body { background: #f4f6f9; font-size: .92rem; }
        .navbar-brand small { font-size: .7rem; opacity: .8; display: block; line-height: 1; }
        .audit-nav .list-group-item { padding: .35rem .75rem; font-size: .82rem; }
        .audit-nav .list-group-item.active { z-index: 0; }
        .audit-nav .sec { font-weight: 600; background: #eef2f7; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; }
        .table-sm td, .table-sm th { vertical-align: top; }
        .metric-box { border-left: 4px solid #0d6efd; }
        .form-label { font-weight: 500; font-size: .85rem; margin-bottom: .2rem; }
        .text-pre { white-space: pre-line; }
        th.sticky { position: sticky; top: 0; background: #f8f9fa; }
    </style>
    @stack('styles')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#1f3a5f">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('audit.dashboard') }}">
            <i class="bi bi-clipboard-check"></i> {{ $settings->get('general.portal_title') }}
            <small>MMMUT Gorakhpur · IQAC</small>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="{{ route('audit.dashboard') }}">Dashboard</a></li>
                @if($access->isIqac() || $access->isAuditor())
                    <li class="nav-item"><a class="nav-link" href="{{ route('audit.reports.university') }}">University Summary</a></li>
                @endif
                @if($access->isIqac())
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Administration</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('audit.admin.cycles') }}">Audit cycles &amp; marks scheme</a></li>
                            <li><a class="dropdown-item" href="{{ route('audit.admin.users') }}">Users &amp; roles</a></li>
                            <li><a class="dropdown-item" href="{{ route('audit.admin.departments') }}">Departments</a></li>
                            <li><a class="dropdown-item" href="{{ route('audit.admin.faculty') }}">Faculty / ERP import</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('audit.admin.notices') }}"><i class="bi bi-megaphone"></i> Notices</a></li>
                            <li><a class="dropdown-item" href="{{ route('audit.admin.settings') }}"><i class="bi bi-gear"></i> Portal settings</a></li>
                        </ul>
                    </li>
                @endif
            </ul>
            @auth
                <span class="navbar-text text-white-50 me-2 small">{{ auth()->user()->name }}</span>
                @include('audit::partials.role-switcher')
                @if(Route::has('password.change'))
                    <a class="btn btn-sm btn-outline-light me-2" href="{{ route('password.change') }}"><i class="bi bi-key"></i></a>
                @endif
                @if(Route::has('logout'))
                    <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn-sm btn-outline-light">Logout</button></form>
                @endif
            @endauth
        </div>
    </div>
</nav>
<main class="container-fluid py-3">
    @include('audit::partials.flash')
    @yield('content')
</main>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="sessionToast" class="toast" role="alert" aria-live="assertive" data-bs-autohide="false">
        <div class="toast-body">
            Your session will expire in a minute because of inactivity.
            <div class="mt-2"><button type="button" id="stayIn" class="btn btn-sm btn-primary">Stay logged in</button></div>
        </div>
    </div>
</div>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
// Send the user to the login page when the session times out, instead of letting a later click fail with "419 Page Expired".
(function () {
    const lifetime = {{ (int) config('session.lifetime') }} * 60 * 1000;
    const loginUrl = @json(route('login', ['expired' => 1]));
    const pingUrl = @json(route('audit.ping'));
    let warnTimer, expireTimer;
    function arm() {
        clearTimeout(warnTimer); clearTimeout(expireTimer);
        warnTimer = setTimeout(() => bootstrap.Toast.getOrCreateInstance(document.getElementById('sessionToast')).show(), Math.max(0, lifetime - 60000));
        expireTimer = setTimeout(() => { window.location.href = loginUrl; }, lifetime);
    }
    document.getElementById('stayIn').addEventListener('click', async () => {
        const res = await fetch(pingUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
        if (res.status === 401 || res.redirected) { window.location.href = loginUrl; return; }
        bootstrap.Toast.getOrCreateInstance(document.getElementById('sessionToast')).hide();
        arm();
    });
    arm();
})();
</script>
@stack('scripts')
</body>
</html>
