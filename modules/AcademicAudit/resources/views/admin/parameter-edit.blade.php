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
                <label class="form-label">Slabs (checked top to bottom; the first condition met gives the marks; none met → 0)</label>
                <table class="table table-sm">
                    <thead><tr><th>Condition</th><th>Threshold</th><th>Marks</th></tr></thead>
                    <tbody>
                    @php($slabs = $param->slabs->values())
                    @for($i = 0; $i < $slabs->count() + 3; $i++)
                        <tr>
                            <td><select name="slabs[{{ $i }}][op]" class="form-select form-select-sm">
                                    <option value="gt" @selected(($slabs[$i]->op ?? 'gt') === 'gt')>{{ $def['metric']['symbol'] }} &gt;</option>
                                    <option value="gte" @selected(($slabs[$i]->op ?? '') === 'gte')>{{ $def['metric']['symbol'] }} ≥</option>
                                </select></td>
                            <td><input type="number" step="any" name="slabs[{{ $i }}][threshold]" value="{{ isset($slabs[$i]) ? \Mmmut\AcademicAudit\Support\Fmt::value($slabs[$i]->threshold, 4) : '' }}" class="form-control form-control-sm"></td>
                            <td><input type="number" step="0.5" name="slabs[{{ $i }}][marks]" value="{{ isset($slabs[$i]) ? \Mmmut\AcademicAudit\Support\Fmt::marks($slabs[$i]->marks) : '' }}" class="form-control form-control-sm"></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            @endif
            <label class="form-label">Note shown to departments</label>
            <textarea name="notes" rows="2" class="form-control form-control-sm mb-3">{{ old('notes', $param->notes) }}</textarea>
            <button class="btn btn-primary btn-sm">Save</button>
            <a href="{{ route('audit.admin.parameters', $param->cycle_id) }}" class="btn btn-link btn-sm">Back</a>
        </form>
    </div>
</div>
@endsection
