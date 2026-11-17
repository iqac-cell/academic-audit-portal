@extends(config('academic-audit.layout'))
@section('title', 'Edit '.$param->code)
@section('content')
<h5>{{ $param->code }} – {{ $param->cycle->academic_year }}</h5>
<div class="card shadow-sm" style="max-width:900px">
    <div class="card-body">
        <form method="post" action="{{ route('audit.admin.parameters.update', $param) }}">
            @csrf @method('put')
            <label class="form-label">Title</label>
            <input name="title" value="{{ old('title', $param->title) }}" class="form-control form-control-sm mb-2">
            <div class="row g-2 mb-2">
                <div class="col-md-3"><label class="form-label">Max marks</label><input type="number" step="0.5" name="max_marks" value="{{ old('max_marks', $param->max_marks) }}" class="form-control form-control-sm"></div>
                <div class="col-md-9"><label class="form-label">Indicator</label>
                    <div class="form-control form-control-sm bg-light">{{ $def['metric']['symbol'] }} · scoring: {{ $param->scoring }}</div></div>
            </div>
            @if($param->scoring === 'slab')
                @include('audit::partials.repeat-rows')
                @php($slabs = $param->slabs->values())
                @php($slabRow = function ($i, $s = null) use ($def) {
                    $op = $s->op ?? 'gt';
                    return '<tr data-row><td><select name="slabs['.$i.'][op]" class="form-select form-select-sm">'
                        .'<option value="gt"'.($op === 'gt' ? ' selected' : '').'>'.e($def['metric']['symbol']).' &gt;</option>'
                        .'<option value="gte"'.($op === 'gte' ? ' selected' : '').'>'.e($def['metric']['symbol']).' ≥</option></select></td>'
                        .'<td><input type="number" step="any" name="slabs['.$i.'][threshold]" value="'.e($s ? \Mmmut\AcademicAudit\Support\Fmt::value($s->threshold, 4) : '').'" class="form-control form-control-sm"></td>'
                        .'<td><input type="number" step="0.5" name="slabs['.$i.'][marks]" value="'.e($s ? \Mmmut\AcademicAudit\Support\Fmt::marks($s->marks) : '').'" class="form-control form-control-sm"></td>'
                        .'<td class="align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-row title="Remove slab"><i class="bi bi-x-lg"></i></button></td></tr>';
                })
                <div data-repeat data-next="{{ max($slabs->count(), 1) }}">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label mb-1">Slabs (checked top to bottom; the first condition met gives the marks; none met → 0)</label>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-add-row><i class="bi bi-plus-lg"></i> Add slab</button>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>Condition</th><th>Threshold</th><th>Marks</th><th></th></tr></thead>
                        <tbody>
                        @forelse($slabs as $i => $s){!! $slabRow($i, $s) !!}@empty{!! $slabRow(0) !!}@endforelse
                        </tbody>
                    </table>
                    <template>{!! $slabRow('__i__') !!}</template>
                </div>
            @endif
            <label class="form-label">Note shown to departments</label>
            <textarea name="notes" rows="2" class="form-control form-control-sm mb-3">{{ old('notes', $param->notes) }}</textarea>
            <button class="btn btn-primary btn-sm">Save</button>
            <a href="{{ route('audit.admin.parameters', $param->cycle_id) }}" class="btn btn-link btn-sm">Back</a>
        </form>
    </div>
</div>
@endsection
