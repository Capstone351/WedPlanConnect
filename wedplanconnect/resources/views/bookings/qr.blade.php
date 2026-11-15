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
        <x-qr-card :booking="$booking" :qr-svg="$qrSvg" />

        {{-- Staff guide (not printed) --}}
        <div class="space-y-6 print:hidden">
            <div class="card">
                <div class="card-header"><h2 class="card-title">How the couple uses it</h2></div>
                <ol class="card-body space-y-3 text-sm text-stone-700">
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">1</span><span>They scan the code with their phone camera (no app needed).</span></li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">2</span><span class="min-w-0">A 6-digit PIN is emailed to <strong class="break-words">{{ $booking->client->email }}</strong>.</span></li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">3</span><span>They enter the PIN and see live progress: milestones, suppliers, and set-up items.</span></li>
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

            @if (\App\Models\OutboxMessage::enabled() && auth()->user()->role === 'admin')
                <div class="rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
                    <strong>Test mode:</strong> PIN emails aren't delivered yet. After scanning, read the PIN in the
                    <a href="{{ route('outbox.index') }}" class="font-semibold underline" target="_blank">Email outbox</a>.
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
