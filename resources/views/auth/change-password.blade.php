@extends('auth.guest')
@section('title', 'Change password')
@section('content')
<h5 class="mb-1">Change password</h5>
@if(auth()->user()->must_change_password)<p class="small text-danger">Please set your own password before continuing.</p>@endif
<form method="post" action="{{ route('password.update') }}">
    @csrf
    <div class="mb-2"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>@error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="mb-2"><label class="form-label">New password</label><input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">At least 8 characters with letters and numbers.</div></div>
    <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" required></div>
    <button class="btn btn-primary w-100">Save password</button>
</form>
<form method="post" action="{{ route('logout') }}" class="mt-2 text-center">@csrf <button class="btn btn-link btn-sm">Logout</button></form>
@endsection
