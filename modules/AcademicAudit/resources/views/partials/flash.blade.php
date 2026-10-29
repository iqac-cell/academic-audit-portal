@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show py-2">{{ session('status') }}<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif
