<x-layouts.app title="Chatbot Logs" heading="Chatbot Session Logs" subheading="Audit trail of WedBot conversations. Use unanswered questions to grow the knowledge base.">
    <x-slot:actions>
        <a href="{{ route('admin.faqs.index') }}" class="btn-secondary">Back to FAQ</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total questions" :value="$stats['total']" icon="chat" />
        <x-stat label="Answered by FAQ" :value="$stats['faq']" icon="check" tone="green" />
        <x-stat label="Gemini fallback" :value="$stats['ai']" icon="bell" tone="blue" />
        <x-stat label="Unanswered" :value="$stats['unanswered']" icon="warning" tone="amber" />
    </div>

    <div class="card mt-6">
        <div class="flex gap-2 border-b border-stone-100 p-4 text-sm">
            @foreach (['' => 'All', 'unanswered' => 'Not matched to FAQ', 'ai' => 'Gemini answers'] as $key => $label)
                <a href="{{ route('admin.faqs.logs', array_filter(['filter' => $key])) }}" @class(['btn-sm', 'btn-primary' => request('filter', '') === $key, 'btn-secondary' => request('filter', '') !== $key])>{{ $label }}</a>
            @endforeach
        </div>
        <ul class="divide-y divide-stone-100">
            @forelse ($sessions as $s)
                <li class="px-5 py-4 text-sm">
                    <div class="flex flex-wrap items-center gap-2 text-xs text-stone-500">
                        <span>{{ $s->created_at->format('M j, Y g:i A') }}</span> ·
                        <span>{{ $s->user?->name ?? 'Guest' }}</span>
                        @if ($s->api_used)
                            <span class="badge-blue">Gemini</span>
                        @elseif ($s->faq_id)
                            <span class="badge-green">FAQ #{{ $s->faq_id }}</span>
                        @else
                            <span class="badge-amber">Fallback</span>
                        @endif
                    </div>
                    <div class="mt-1.5 font-medium">Q: {{ $s->question }}</div>
                    <div class="mt-1 whitespace-pre-line text-stone-600">A: {{ \Illuminate\Support\Str::limit($s->answer, 300) }}</div>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-stone-500">No chatbot sessions yet.</li>
            @endforelse
        </ul>
        <div class="p-4">{{ $sessions->links() }}</div>
    </div>
</x-layouts.app>
