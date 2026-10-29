<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · MMMUT Academic Audit</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
</head>
<body style="background:#1f3a5f">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="text-center text-white mb-3">
                @if(is_file(public_path('images/mmmut-logo.png')))<img src="{{ asset('images/mmmut-logo.png') }}" alt="MMMUT" style="height:90px" class="mb-2 bg-white rounded-circle p-1">@endif
                <h4 class="mb-0">Internal Academic Audit Portal</h4>
                <div class="small opacity-75">Madan Mohan Malaviya University of Technology, Gorakhpur · IQAC</div>
            </div>
            <div class="card shadow">
                <div class="card-body p-4">@yield('content')</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
