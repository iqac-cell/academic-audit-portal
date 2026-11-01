@extends('auth.guest')
@section('title', 'No access yet')
@section('content')
<h5 class="mb-3"><i class="bi bi-person-lock"></i> No access to the Academic Audit yet</h5>
<p>Hello {{ $name }}, you are signed in centrally as <strong>{{ $email }}</strong>, but no role in the Academic Audit Portal has been given to you.</p>
<p>Please ask the IQAC office{{ $contact ? ' ('.$contact.')' : '' }} to add you as Faculty, HOD or Auditor. Your account has been registered, so the IQAC can find you under Administration → Users &amp; roles.</p>
<div class="d-flex gap-2 flex-wrap">
    <a href="{{ config('sso.issuer') }}/home" class="btn btn-primary btn-sm"><i class="bi bi-grid"></i> Portal Home</a>
    <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm">Try again</a>
    <a href="{{ $logoutUrl }}" class="btn btn-outline-secondary btn-sm">Sign out</a>
</div>
@endsection
