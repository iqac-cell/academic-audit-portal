@extends('auth.guest')
@section('title', 'Reset password')
@section('content')
<h5 class="mb-2">Reset password</h5>
@if(session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif
<form method="post" action="{{ route('password.otp.reset') }}">
    @csrf
    <div class="mb-2"><label class="form-label">Official email</label><input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Code from email</label><input name="code" inputmode="numeric" autocomplete="one-time-code" class="form-control @error('code') is-invalid @enderror" required>@error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-2"><label class="form-label">New password</label><input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">At least {{ $min }} characters with letters and numbers.</div></div>
    <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <button class="btn btn-primary w-100">Set new password</button>
</form>
<div class="text-center mt-3"><a href="{{ route('password.request') }}" class="small">Send a new code</a> · <a href="{{ route('login') }}" class="small">Back to login</a></div>
@endsection
