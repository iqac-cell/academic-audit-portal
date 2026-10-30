@php($roles = $access->availableRoles())
@php($active = $access->active())
@if($roles->count() > 1)
    <div class="dropdown me-2">
        <button class="btn btn-sm btn-warning dropdown-toggle fw-semibold" data-bs-toggle="dropdown" title="Switch role">
            <i class="bi bi-person-badge"></i> {{ $active['label'] }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Work as</h6></li>
            @foreach($roles as $r)
                <li>
                    <form method="post" action="{{ route('audit.role.switch') }}">
                        @csrf <input type="hidden" name="role" value="{{ $r['key'] }}">
                        <button class="dropdown-item {{ $r['key'] === $active['key'] ? 'active' : '' }}">{{ $r['label'] }}</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@elseif($active)
    <span class="badge bg-light text-dark me-2">{{ $active['label'] }}</span>
@endif
