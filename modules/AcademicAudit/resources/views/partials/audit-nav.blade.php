@php($current = $current ?? '')
@php($navEval = $navEval ?? null)
<div class="list-group audit-nav mb-3 shadow-sm">
    <a href="{{ route('audit.show', $audit) }}" class="list-group-item list-group-item-action {{ $current === 'overview' ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Overview</a>
    <a href="{{ route('audit.basic', $audit) }}" class="list-group-item list-group-item-action {{ $current === 'basic' ? 'active' : '' }}"><i class="bi bi-building"></i> Basic Information</a>
    <a href="{{ route('audit.inputs', $audit) }}" class="list-group-item list-group-item-action {{ $current === 'inputs' ? 'active' : '' }}"><i class="bi bi-123"></i> Denominators &amp; Yes/No items</a>
    @foreach(\Mmmut\AcademicAudit\Support\Catalogue::bySection() as $sec => $items)
        <div class="list-group-item sec">Section {{ $sec }}: {{ \Mmmut\AcademicAudit\Support\Catalogue::SECTIONS[$sec] }}</div>
        @foreach($items as $code => $def)
            @php($href = $def['columns'] ? route('audit.items', [$audit, $code]) : route('audit.inputs', $audit).'#'.str_replace('.', '', $code))
            <a href="{{ $href }}" class="list-group-item list-group-item-action d-flex justify-content-between {{ $current === $code ? 'active' : '' }}" title="{{ $def['title'] }}">
                <span class="text-truncate"><strong>{{ $def['letter'] }})</strong> {{ \Illuminate\Support\Str::limit($def['title'], 42) }}</span>
                @if($navEval && isset($navEval[$code]) && $def['columns'])
                    <span class="badge rounded-pill bg-{{ $navEval[$code]['rows'] ? 'success' : 'light text-muted' }} ms-1">{{ $navEval[$code]['rows'] }}</span>
                @endif
            </a>
        @endforeach
    @endforeach
    <a href="{{ route('audit.evaluation', $audit) }}" class="list-group-item list-group-item-action {{ $current === 'evaluation' ? 'active' : '' }}"><i class="bi bi-table"></i> Evaluation &amp; Marks</a>
</div>
