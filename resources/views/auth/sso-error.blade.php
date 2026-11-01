@extends('auth.guest')
@section('title', 'Sign-in failed')
@section('content')
<h5 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Sign-in failed</h5>
<div class="alert alert-danger small">{{ $message }}</div>
<div class="d-flex gap-2 flex-wrap">
    <a href="{{ route('sso.redirect') }}" class="btn btn-primary btn-sm">Try again</a>
    <a href="{{ route('login.local') }}" class="btn btn-outline-secondary btn-sm">Emergency login (IQAC)</a>
</div>
@endsection
