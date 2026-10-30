@extends('auth.guest')
@section('title', 'Login')
@section('content')
<h5 class="mb-3">Sign in</h5>
@if(session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif
@if(!empty($notice))<div class="alert alert-info py-2 small" style="white-space: pre-line">{{ $notice }}</div>@endif
<form method="post" action="{{ url('login') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="email">Official email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input id="password" type="password" name="password" class="form-control" required>
    </div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Remember me</label></div>
    <button class="btn btn-primary w-100">Login</button>
</form>
@if($otpEnabled)
    <div class="text-center mt-2"><a href="{{ route('password.request') }}" class="small">Forgot password?</a></div>
@endif
<p class="small text-muted mt-3 mb-0">HODs and faculty sign in with their official @mmmut.ac.in email. First-time users must change the default password.
    @unless($otpEnabled) Forgot password? Contact the IQAC office{{ $contact ? ' ('.$contact.')' : '' }}.@endunless</p>
@endsection
