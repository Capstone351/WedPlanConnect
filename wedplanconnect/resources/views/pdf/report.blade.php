<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 10px; color: #2b2226; margin: 0; }
        .brand { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #9d445c; }
        h1 { font-size: 18px; margin: 4px 0 2px; }
        .muted { color: #78716c; }
        .summary { width: 100%; margin: 14px 0; border-collapse: separate; border-spacing: 6px 0; }
        .summary td { background: #fbf5f6; border: 1px solid #edd0d7; border-radius: 6px; padding: 6px 8px; vertical-align: top; }
        .summary .label { font-size: 8px; text-transform: uppercase; color: #78716c; }
        .summary .value { font-size: 12px; font-weight: bold; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f5f5f4; text-align: left; font-size: 8px; text-transform: uppercase; color: #57534e; padding: 5px 6px; border-bottom: 1px solid #d6d3d1; }
        table.data td { padding: 5px 6px; border-bottom: 1px solid #e7e5e4; }
        .right { text-align: right; }
        .overdue { color: #b91c1c; font-weight: bold; }
        .footer { margin-top: 14px; font-size: 8px; color: #a8a29e; }
    </style>
</head>
<body>
    <div class="brand">{{ config('wedplan.business_name') }} · WedPlanConnect</div>
    <h1>{{ $report['title'] }}</h1>
    <div class="muted">
        {{ $report['description'] }}<br>
        @if (! empty($filters['from']) || ! empty($filters['to']))
            Period: {{ $filters['from'] ?? 'beginning' }} to {{ $filters['to'] ?? 'present' }}
        @elseif (isset($report['period']))
            Window: {{ $report['period'][0]->format('M j, Y') }} – {{ $report['period'][1]->format('M j, Y') }}
        @else
            Period: all records
        @endif
    </div>

    <table class="summary">
        <tr>
            @foreach ($report['summary'] as $label => $value)
                <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
            @endforeach
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>@foreach ($report['columns'] as $col => $align)<th class="{{ $align === 'right' ? 'right' : '' }}">{{ $col }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach (array_values($report['columns']) as $i => $align)
                        <td class="{{ $align === 'right' ? 'right' : '' }} {{ $row[$i] === 'OVERDUE' ? 'overdue' : '' }}">{{ $row[$i] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" class="muted" style="text-align:center;padding:20px">No data available for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated {{ now()->format('F j, Y g:i A') }} by {{ $generatedBy->name }} ({{ $generatedBy->roleLabel() }}) · Confidential: contains personal data protected under RA 10173.</div>
</body>
</html>
