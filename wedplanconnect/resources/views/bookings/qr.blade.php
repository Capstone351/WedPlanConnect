<x-layouts.app :title="'QR Code · Booking #'.$booking->id" heading="Client QR Status Code" subheading="Print this card and attach it to the couple's contract. They scan it with a phone camera to follow their preparations.">
    <x-slot:actions>
        <a href="{{ route('bookings.show', $booking) }}" class="btn-secondary">Back to booking</a>
        <a href="{{ route('bookings.qr.download', $booking) }}" class="btn-secondary"><x-icon name="download" class="size-4" /> Download</a>
        <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Print card</button>
    </x-slot:actions>

    @if ($networkWarning)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 print:hidden" role="alert">
            <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
            <div><strong>Phones may not be able to open this QR code.</strong> {{ $networkWarning }}</div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        {{-- Printable card --}}
        <div class="mx-auto w-full max-w-md rounded-3xl border border-gold-300 bg-white p-8 text-center shadow-[0_18px_40px_-24px_rgba(74,31,46,0.5)] print:max-w-none print:shadow-none">
            <div class="eyebrow text-gold-700">{{ config('wedplan.business_name') }}</div>
            <h2 class="mt-3 font-display text-3xl font-semibold">{{ $booking->client_name }}</h2>
            <p class="mt-1 text-sm text-stone-500">{{ $booking->event_date->format('F j, Y') }} · {{ $booking->venue }}</p>
            <x-ornament class="mx-auto mt-4 w-40" />
            <div class="mx-auto mt-5 inline-block rounded-2xl border border-line bg-white p-4">{!! $qrSvg !!}</div>
            <p class="mt-5 font-display text-lg font-semibold">Scan to view your wedding preparations</p>
            <ol class="mx-auto mt-3 max-w-xs space-y-1 text-left text-xs text-stone-600">
                <li><strong>1.</strong> Open your phone camera and point it at the code.</li>
                <li><strong>2.</strong> Tap the link that appears.</li>
                <li><strong>3.</strong> Enter the 6-digit PIN we email to you.</li>
            </ol>
            <p class="mt-4 text-[11px] text-stone-400">For your privacy, the PIN is required every time.</p>
        </div>

        {{-- Staff guide (not printed) --}}
        <div class="space-y-6 print:hidden">
            <div class="card">
                <div class="card-header"><h2 class="card-title">How the couple uses it</h2></div>
                <ol class="card-body space-y-3 text-sm text-stone-700">
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">1</span>They scan the code with their phone camera (no app needed).</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">2</span>A 6-digit PIN is emailed to <strong>{{ $booking->client->email }}</strong>.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">3</span>They enter the PIN and see live progress: milestones, suppliers, and set-up items.</li>
                </ol>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Status link</h2></div>
                <div class="card-body space-y-3">
                    <p class="text-xs text-stone-500">This is the address inside the QR code. You can also send it to the couple directly.</p>
                    <div class="flex gap-2">
                        <input id="status-url" value="{{ $statusUrl }}" readonly class="input font-mono text-xs" aria-label="Status link">
                        <button type="button" class="btn-secondary shrink-0" data-copy="#status-url">Copy</button>
                    </div>
                    <a href="{{ $statusUrl }}" target="_blank" rel="noopener" class="btn-outline btn-sm">Open link to test →</a>
                </div>
            </div>

            @if (\App\Models\OutboxMessage::enabled())
                <div class="rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
                    <strong>Test mode:</strong> PIN emails aren't delivered yet. After scanning, read the PIN in the
                    <a href="{{ route('outbox.index') }}" class="font-semibold underline" target="_blank">Email outbox</a>.
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
