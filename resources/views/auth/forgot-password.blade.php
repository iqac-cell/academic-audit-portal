@extends('auth.guest')
@section('title', 'Forgot password')
@section('content')
<h5 class="mb-3">Forgot password</h5>
@if(! $enabled)
    <p>Password reset by email is not available. Please contact the IQAC office{{ $contact ? ' at '.$contact : '' }} to reset your password.</p>
@else
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="email">Official email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary w-100">Send code</button>
    </form>
@endif
<div class="text-center mt-3"><a href="{{ route('login') }}" class="small">Back to login</a></div>
@endsection
