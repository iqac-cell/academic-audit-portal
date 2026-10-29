@php
    use Mmmut\AcademicAudit\Support\Fmt;
    use Mmmut\AcademicAudit\Support\ItemForm;
    use Mmmut\AcademicAudit\Support\Catalogue;
    $dept = $audit->department;
    $levels = \Mmmut\AcademicAudit\Http\Controllers\BasicInfoController::LEVELS;
    $sectionTitles = ['A' => 'SECTION A: STUDENT QUALITY', 'B' => 'SECTION B: CURRICULUM ENRICHMENT', 'C' => 'SECTION C: FACULTY CONTRIBUTION', 'D' => 'SECTION D: EXTENSION AND OUTREACH'];
    $logo = public_path('images/mmmut-logo.png');
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Academic Audit – {{ $dept->name }} – {{ $audit->cycle->academic_year }}</title>
    <style>
        @page { margin: 18mm 14mm 16mm 14mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5pt; color: #000; }
        h1, h2, h3 { margin: 0; }
        .cover { text-align: center; padding-top: 50px; page-break-after: always; }
        .cover h1 { font-size: 19pt; font-family: DejaVu Serif, 'Times New Roman', serif; }
        .cover h2 { font-size: 15pt; margin-top: 8px; }
        .cover .big { font-size: 15pt; margin-top: 70px; font-weight: bold; }
        .hdr { text-align: right; font-style: italic; font-size: 8.5pt; color: #444; }
        .center { text-align: center; }
        .section-title { text-align: center; font-weight: bold; font-size: 11pt; margin: 14px 0 6px; text-decoration: underline; }
        .item-title { font-weight: bold; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
        th { background: #eee; font-weight: bold; text-align: center; font-size: 8.5pt; }
        td { font-size: 8.5pt; }
        .formula { margin: 3px 0 8px 30px; font-style: italic; }
        .r { text-align: right; }
        .muted { color: #555; }
        .pb { page-break-before: always; }
        .sign td { border: none; text-align: center; padding-top: 50px; }
        .noprint { margin: 10px 0; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
@unless($pdf)
    <div class="noprint">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('audit.report.pdf', $audit) }}">Download PDF</a> ·
        <a href="{{ route('audit.report.xlsx', $audit) }}">Download Excel</a> ·
        <a href="{{ route('audit.show', $audit) }}">Back</a>
        @if($audit->isEditable())<strong style="color:#b00"> · DRAFT – not yet submitted</strong>@endif
    </div>
@endunless

<div class="cover">
    <div class="hdr">Academic Audit Format</div>
    <h1>INTERNAL ACADEMIC AUDIT FORMS</h1>
    <h2>Madan Mohan Malaviya University of Technology</h2>
    <h2>Gorakhpur - 273010</h2>
    @if(is_file($logo))<div style="margin-top:40px"><img src="{{ $pdf ? $logo : asset('images/mmmut-logo.png') }}" style="height:150px"></div>@endif
    <div class="big">Name of the Department: {{ $dept->name }}</div>
    <div class="big" style="margin-top:60px">Academic Year: {{ $audit->cycle->academic_year }}</div>
</div>

<div class="center" style="font-weight:bold; font-size:11pt">DEPARTMENT OF {{ strtoupper($dept->name) }}</div>
<div class="section-title">BASIC INFORMATION</div>
<div class="item-title">a) About of Department</div><div>{{ $info['about'] ?? '' }}</div>
<div class="item-title">b) Vision:</div><div>{{ $info['vision'] ?? '' }}</div>
<div class="item-title">c) Mission:</div><div style="white-space: pre-line">{{ $info['mission'] ?? '' }}</div>
<div class="item-title">d) Program offered:</div>
<table><tr><th style="width:6%">SN</th><th>Name of Program</th><th>Approved Intake</th><th>Year of Starting</th></tr>
    @forelse($info['programs'] ?? [] as $i => $p)<tr><td>{{ $i + 1 }}</td><td>{{ $p['name'] }}</td><td>{{ $p['intake'] ?? '' }}</td><td>{{ $p['year'] ?? '' }}</td></tr>@empty<tr><td colspan="4">&nbsp;</td></tr>@endforelse
</table>
<div class="item-title">e) Faculty Information</div>
<table><tr><th style="width:6%">SN</th><th>Name of Faculty</th><th>Highest Qualification</th><th>Year of Experience</th><th>Date of Joining University</th></tr>
    @forelse($info['faculty'] ?? [] as $i => $f)<tr><td>{{ $i + 1 }}</td><td>{{ $f['name'] }}</td><td>{{ $f['qualification'] ?? '' }}</td><td>{{ $f['experience'] ?? '' }}</td><td>{{ $f['doj'] ?? '' }}</td></tr>@empty<tr><td colspan="5">&nbsp;</td></tr>@endforelse
</table>
<div class="item-title">f) Student Information</div>
<div>i) Sanction Intake Approved by Governing Body</div>
<table><tr><th>Program</th><th>Approved Governing body AICTE/PCI/University, etc</th><th>Total Students as per sanctioned Intake (TS)</th></tr>
    @foreach(['UG', 'PG'] as $l)<tr><td>{{ $levels[$l] }}</td><td>{{ $info['sanctioned'][$l]['body'] ?? '' }}</td><td>{{ $info['sanctioned'][$l]['ts'] ?? '' }}</td></tr>@endforeach
</table>
<div>ii) Student Enrolled (Annexure-I)</div>
<table><tr><th rowspan="2">Program</th><th colspan="5">Number of Students Enrolled</th></tr>
    <tr>@foreach(Catalogue::YEARS as $y)<th>{{ $y }}</th>@endforeach<th>Total</th></tr>
    @foreach($levels as $l => $label)
        @php($en = $info['enrolled'][$l] ?? [])
        <tr><td>{{ $label }}</td>@foreach(Catalogue::YEARS as $y)<td class="r">{{ $en[$y] ?? '' }}</td>@endforeach<td class="r">{{ array_sum(array_map('floatval', $en)) ?: '' }}</td></tr>
    @endforeach
</table>

@foreach($sections as $sec => $items)
    <div class="section-title pb">{{ $sectionTitles[$sec] }}</div>
    @foreach($items as $code => $def)
        @php($e = $eval[$code])
        <div class="item-title">{{ $def['letter'] }}) {{ $e['parameter']->title }} ({{ Fmt::marks($e['parameter']->max_marks) }} Marks)
            @if($def['annexure'])<span class="muted" style="font-weight:normal">(details in Annexure - {{ $def['annexure'] }})</span>@endif</div>
        @if($def['columns'])
            <table>
                <tr><th style="width:5%">SN</th>@foreach($def['columns'] as $c)<th>{{ $c['label'] }}</th>@endforeach</tr>
                @forelse($entries[$code] ?? [] as $i => $row)
                    <tr><td>{{ $i + 1 }}</td>@foreach($def['columns'] as $c)<td>{{ ItemForm::display($c, $row->data[$c['key']] ?? null) }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($def['columns']) + 1 }}" class="center muted">Nil</td></tr>
                @endforelse
            </table>
        @endif
        @switch($code)
            @case('B.b')<div>Total number of students completed internship/field/research projects: <strong>{{ $scalars['int_completed'] ?? '—' }}</strong> (eligible: {{ $scalars['int_eligible'] ?? '—' }})</div>@break
            @case('B.c')<div>Feedback analysed, action taken &amp; communicated and hosted on the Department Home Page: <strong>{{ ($scalars['feedback_hosted'] ?? null) === 'Y' ? 'Yes' : (($scalars['feedback_hosted'] ?? null) === 'N' ? 'No' : '—') }}</strong> {{ $scalars['feedback_link'] ?? '' }}</div>@break
            @case('B.g')<div>Submitted: <strong>{{ ($scalars['poco_submitted'] ?? null) === 'Y' ? 'Yes' : 'No' }}</strong> · % improvement compared to previous academic year: {{ $scalars['poco_improvement'] ?? '—' }}</div>@break
            @case('B.h')<div>Submitted: <strong>{{ ($scalars['popso_submitted'] ?? null) === 'Y' ? 'Yes' : 'No' }}</strong></div>@break
        @endswitch
        @if($e['parameter']->scoring !== 'manual')
            <div class="formula">{{ $def['metric']['symbol'] }} = {{ Fmt::indicator($e) }} @if($e['formula'])({{ $e['formula'] }})@endif</div>
        @endif
    @endforeach
@endforeach

<table class="sign" style="margin-top:20px"><tr><td style="text-align:left">Date:<br>Place:</td><td></td><td>Signature of Head of Dept<br>Name of Head of Dept with Seal<br>{{ $audit->hod?->name }}</td></tr></table>

<div class="section-title pb">EVALUATION</div>
<table>
    <tr><th style="width:5%">SN</th><th>Parameter</th><th style="width:9%">Max Mark</th><th style="width:10%">Marks Claimed</th><th style="width:10%">Marks Awarded</th><th style="width:22%">Remark</th></tr>
    @foreach($sections as $sec => $items)
        <tr><th colspan="6">{{ $sectionTitles[$sec] }}</th></tr>
        @foreach($items as $code => $def)
            @php($e = $eval[$code])
            @php($s = $e['score'])
            <tr>
                <td class="center">{{ $def['letter'] }}</td><td>{{ $e['parameter']->title }}</td>
                <td class="r">{{ Fmt::marks($e['parameter']->max_marks) }}</td>
                <td class="r">{{ Fmt::marks($audit->isEditable() ? $e['claimed'] : ($s?->claimed_marks ?? $e['claimed'])) }}</td>
                <td class="r">{{ Fmt::marks($s?->awarded_marks) }}</td>
                <td>{{ trim(($s?->auditor1_remark ?? '').' '.($s?->auditor2_remark ?? '')) }}</td>
            </tr>
        @endforeach
        <tr><th colspan="2" style="text-align:right">Sub-Total Marks ({{ $sec }})</th><th class="r">{{ Fmt::marks($summary[$sec]['max']) }}</th><th class="r">{{ Fmt::marks($summary[$sec]['claimed']) }}</th><th class="r">{{ Fmt::marks($summary[$sec]['awarded']) }}</th><th></th></tr>
    @endforeach
</table>

<div class="section-title">SUMMARY OF EVALUATION</div>
<table style="width:85%; margin: 0 auto">
    <tr><th>Section</th><th>Details</th><th>Max Marks</th><th>Marks claimed</th><th>Marks Awarded</th></tr>
    @foreach(Catalogue::SECTIONS as $sec => $l)
        <tr><td class="center">{{ $sec }}</td><td>{{ $l }}</td><td class="r">{{ Fmt::marks($summary[$sec]['max']) }}</td><td class="r">{{ Fmt::marks($summary[$sec]['claimed']) }}</td><td class="r">{{ Fmt::marks($summary[$sec]['awarded']) }}</td></tr>
    @endforeach
    <tr><th colspan="2" style="text-align:right">Total Marks</th><th class="r">{{ Fmt::marks($summary['total']['max']) }}</th><th class="r">{{ Fmt::marks($summary['total']['claimed']) }}</th><th class="r">{{ Fmt::marks($summary['total']['awarded']) }}</th></tr>
</table>

<table class="sign"><tr>
    <td>Signature of<br>Head of Dept</td>
    <td>Name and Signature of<br>Academic auditor - 1<br>{{ $audit->auditor1?->name }}</td>
    <td>Name and Signature of<br>Academic auditor - 2<br>{{ $audit->auditor2?->name }}</td>
</tr></table>

@if($annexures->isNotEmpty())
    <div class="item-title" style="margin-top:20px">Annexures / documents attached in portal</div>
    <ul>
        @foreach($annexures as $slot => $files)
            <li>{{ str_starts_with($slot, 'ANX-') ? 'Annexure '.substr($slot, 4) : 'Parameter '.$slot }}: {{ $files->pluck('original_name')->implode(', ') }}</li>
        @endforeach
    </ul>
@endif
<p class="muted" style="margin-top:12px">Note: Marks will be awarded based on proof provided by department against each parameter. Generated from the IQAC Academic Audit Portal on {{ now()->format('d-m-Y H:i') }}.</p>
</body>
</html>
