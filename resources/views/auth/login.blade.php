@extends('auth.guest')
@section('title', 'Login')
@section('content')
<h5 class="mb-3">Sign in</h5>
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
<p class="small text-muted mt-3 mb-0">HODs and faculty sign in with their official @mmmut.ac.in email. First-time users must change the default password. Forgot password? Contact the IQAC office.</p>
@endsection
