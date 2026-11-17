{{-- One editable row of a Basic Information table. $kind: programs | faculty | sanctioned | enrolled; $i: index or "__i__"; $row: values. --}}
<tr data-row>
    <td class="sn text-muted small align-middle">{{ is_int($i) ? $i + 1 : '' }}</td>
    @switch($kind)
        @case('programs')
            <td><input name="programs[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. B.Tech. Civil Engineering"></td>
            <td><select name="programs[{{ $i }}][level]" class="form-select form-select-sm">
                    @foreach($levels as $k => $l)<option value="{{ $k }}" @selected(($row['level'] ?? '') === $k)>{{ $k }}</option>@endforeach
                </select></td>
            <td><input type="number" min="0" name="programs[{{ $i }}][intake]" value="{{ $row['intake'] ?? '' }}" class="form-control form-control-sm"></td>
            <td><input type="number" name="programs[{{ $i }}][year]" value="{{ $row['year'] ?? '' }}" class="form-control form-control-sm"></td>
            @break
        @case('faculty')
            <td><input name="faculty[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" class="form-control form-control-sm"></td>
            <td><input name="faculty[{{ $i }}][qualification]" value="{{ $row['qualification'] ?? '' }}" class="form-control form-control-sm"></td>
            <td><input type="number" min="0" step="any" name="faculty[{{ $i }}][experience]" value="{{ $row['experience'] ?? '' }}" class="form-control form-control-sm"></td>
            <td><input name="faculty[{{ $i }}][doj]" value="{{ $row['doj'] ?? '' }}" class="form-control form-control-sm" placeholder="dd-mm-yyyy"></td>
            @break
        @case('sanctioned')
            <td><input name="sanctioned[{{ $i }}][program]" value="{{ $row['program'] ?? '' }}" list="aa-program-names" class="form-control form-control-sm"></td>
            <td><input name="sanctioned[{{ $i }}][body]" value="{{ $row['body'] ?? '' }}" class="form-control form-control-sm"></td>
            <td><input type="number" min="0" name="sanctioned[{{ $i }}][ts]" value="{{ $row['ts'] ?? '' }}" class="form-control form-control-sm"></td>
            @break
        @case('enrolled')
            <td><input name="enrolled[{{ $i }}][program]" value="{{ $row['program'] ?? '' }}" list="aa-program-names" class="form-control form-control-sm"></td>
            @foreach($years as $y)
                <td><input type="number" min="0" name="enrolled[{{ $i }}][counts][{{ $y }}]" value="{{ $row['counts'][$y] ?? '' }}" class="form-control form-control-sm"></td>
            @endforeach
            <td class="fw-semibold align-middle">{{ \Mmmut\AcademicAudit\Support\BasicInfo::total($row['counts'] ?? []) ?: '' }}</td>
            @break
    @endswitch
    <td class="align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-row title="Remove row"><i class="bi bi-x-lg"></i></button></td>
</tr>
