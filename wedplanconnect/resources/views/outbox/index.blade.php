@php $latestPin = $messages->getCollection()->first(fn ($m) => $m->pin()); @endphp
<x-layouts.app title="Email outbox" heading="Email Outbox"
    subheading="Test mode: emails are not delivered yet, so copies appear here instead. Use this to read a couple's one-time PIN during a demo.">
    <x-slot:actions>
        <a href="{{ route('outbox.index') }}" class="btn-secondary">Refresh</a>
    </x-slot:actions>

    <div class="mb-6 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
        <strong>For testing only.</strong> Once real email is set up (<code>MAIL_MAILER=smtp</code> in <code>.env</code>), PINs go straight to the couple's inbox and this page turns itself off.
    </div>

    @if ($latestPin)
        <div class="hero-panel mb-6">
            <div class="relative flex flex-col items-center gap-2 p-8 text-center">
                <div class="eyebrow text-gold-300">Latest one-time PIN</div>
                <div class="font-sans text-6xl font-semibold tracking-[0.25em] text-gold-gradient tabular-nums">{{ $latestPin->pin() }}</div>
                <div class="text-sm text-brand-100/80">Sent to {{ $latestPin->to }} · {{ $latestPin->created_at->diffForHumans() }} · valid for {{ config('wedplan.otp_ttl_minutes') }} minutes, one use</div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h2 class="card-title">Recent emails</h2></div>
        <ul class="divide-y divide-line">
            @forelse ($messages as $m)
                <li class="px-5 py-4">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                            <span class="min-w-0">
                                <span class="block font-medium">{{ $m->subject }}</span>
                                <span class="block text-xs text-stone-500">To {{ $m->to }} · {{ $m->created_at->format('M j, Y g:i:s A') }}</span>
                            </span>
                            <span class="flex items-center gap-3">
                                @if ($m->pin())
                                    <span class="rounded-lg bg-gold-50 px-3 py-1 font-sans text-xl font-semibold tracking-[0.2em] text-gold-700 ring-1 ring-gold-300 tabular-nums">{{ $m->pin() }}</span>
                                @endif
                                <span class="text-xs font-medium text-brand-600 group-open:hidden">View email</span>
                                <span class="hidden text-xs font-medium text-brand-600 group-open:inline">Hide</span>
                            </span>
                        </summary>
                        <iframe src="{{ route('outbox.show', $m) }}" sandbox="" title="Email: {{ $m->subject }}" class="mt-4 h-96 w-full rounded-lg border border-line bg-white"></iframe>
                    </details>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm text-stone-500">No emails yet. Scan a booking's QR code to send a PIN.</li>
            @endforelse
        </ul>
        <div class="p-4">{{ $messages->links() }}</div>
    </div>
</x-layouts.app>
