<x-layouts.app :title="$report['title']" :heading="$report['title']" :subheading="$report['description']">
    <x-slot:actions>
        <a href="{{ route('reports.index') }}" class="btn-secondary">All reports</a>
        <a href="{{ route('reports.pdf', [$report['type'], ...$filters]) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Export PDF</a>
        <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Print</button>
    </x-slot:actions>

    <form class="card mb-6 flex flex-wrap items-end gap-3 p-4 print:hidden">
        <div>
            <label class="label" for="type">Report</label>
            <select id="type" class="input" onchange="window.location = this.value">
                @foreach (\App\Services\ReportService::allowedFor(auth()->user()) as $key => [$title])
                    <option value="{{ route('reports.show', [$key, ...$filters]) }}" @selected($key === $report['type'])>{{ $title }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label" for="from">From</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
        <div><label class="label" for="to">To</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
        <button class="btn-secondary">Apply filters</button>
        @if ($filters)<a href="{{ route('reports.show', $report['type']) }}" class="text-sm link">Clear</a>@endif
    </form>

    @isset($report['period'])
        <p class="mb-4 text-sm text-stone-500">Window: {{ $report['period'][0]->format('M j, Y') }} – {{ $report['period'][1]->format('M j, Y') }}</p>
    @endisset

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($report['summary'] as $label => $value)
            <div class="card p-4">
                <div class="text-xs tracking-wide text-stone-500 uppercase">{{ $label }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="card mt-6">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        @foreach ($report['columns'] as $col => $align)<th @class(['text-right' => $align === 'right'])>{{ $col }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            @foreach (array_values($report['columns']) as $i => $align)
                                <td @class(['text-right tabular-nums' => $align === 'right', 'font-semibold text-red-600' => $row[$i] === 'OVERDUE'])>{{ $row[$i] }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($report['columns']) }}" class="py-10 text-center text-stone-500">No data available for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4 text-xs text-stone-500">Generated {{ now()->format('M j, Y g:i A') }} by {{ auth()->user()->name }} · {{ count($report['rows']) }} row(s)</p>
</x-layouts.app>
