{{-- $c column, $value, $programs, $facultyNames --}}
@php($name = "data[{$c['key']}]")
@php($id = 'f_'.$c['key'])
@switch($c['type'])
    @case('textarea')
        <textarea name="{{ $name }}" id="{{ $id }}" class="form-control form-control-sm" rows="2">{{ $value }}</textarea>
        @break
    @case('number')
        <input type="number" step="any" min="0" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="form-control form-control-sm">
        @break
    @case('date')
        <input type="date" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="form-control form-control-sm">
        @break
    @case('url')
        <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="form-control form-control-sm" placeholder="https://">
        @break
    @case('yn')
        <select name="{{ $name }}" id="{{ $id }}" class="form-select form-select-sm">
            <option value="">—</option>
            <option value="Y" @selected($value === 'Y')>Yes</option>
            <option value="N" @selected($value === 'N')>No</option>
        </select>
        @break
    @case('select')
        <select name="{{ $name }}" id="{{ $id }}" class="form-select form-select-sm">
            <option value="">—</option>
            @foreach($c['options'] as $o)<option value="{{ $o }}" @selected($value === $o)>{{ $o }}</option>@endforeach
        </select>
        @break
    @case('program')
        <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="form-control form-control-sm" list="dl_programs" autocomplete="off">
        @break
    @case('faculty')
        <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="form-control form-control-sm" list="dl_faculty" autocomplete="off">
        @break
    @default
        <input type="text" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" class="form-control form-control-sm">
@endswitch
