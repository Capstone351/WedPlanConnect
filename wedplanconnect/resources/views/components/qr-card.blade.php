{{-- The printable QR status card, shared by the planner's and the couple's QR pages. --}}
@props(['booking', 'qrSvg'])
<div {{ $attributes->merge(['class' => 'mx-auto w-full max-w-md rounded-3xl border border-gold-300 bg-white p-5 text-center sm:p-8 shadow-[0_18px_40px_-24px_rgba(74,31,46,0.5)] print:max-w-none print:shadow-none']) }}>
    <div class="eyebrow text-gold-700">{{ config('wedplan.business_name') }}</div>
    <h2 class="mt-3 font-display text-3xl font-semibold">{{ $booking->client_name }}</h2>
    <p class="mt-1 text-sm text-stone-500">{{ $booking->event_date->format('F j, Y') }} · {{ $booking->venueLabel() }}</p>
    <x-ornament class="mx-auto mt-4 w-40" />
    {{-- The code shrinks to fit narrow phones instead of pushing the page wider. --}}
    <div class="mx-auto mt-5 inline-block max-w-full rounded-2xl border border-line bg-white p-3 sm:p-4 [&>svg]:h-auto [&>svg]:max-w-full" data-qr-image>{!! $qrSvg !!}</div>
    <p class="mt-5 font-display text-lg font-semibold">Scan to view your wedding preparations</p>
    <ol class="mx-auto mt-3 max-w-xs space-y-1 text-left text-xs text-stone-600">
        <li><strong>1.</strong> Open your phone camera and point it at the code.</li>
        <li><strong>2.</strong> Tap the link that appears.</li>
        <li><strong>3.</strong> Enter the 6-digit PIN we email to you.</li>
    </ol>
    <p class="mt-4 text-[11px] text-stone-400">For your privacy, the PIN is required every time.</p>
</div>
