<x-layouts.app title="My QR Code" heading="Your Wedding QR Code"
    subheading="Scan it with any phone to follow your wedding preparations. Save it to your phone or print it.">
    <x-slot:actions>
        <a href="{{ route('client.dashboard') }}" class="btn-secondary">Back to my wedding</a>
        <button type="button" class="btn-secondary" data-qr-png="wedding-qr-{{ $booking->id }}.png"><x-icon name="download" class="size-4" /> Download image</button>
        <button type="button" onclick="window.print()" class="btn-primary"><x-icon name="printer" class="size-4" /> Print</button>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <x-qr-card :booking="$booking" :qr-svg="$qrSvg" />

        <div class="space-y-6 print:hidden">
            <div class="card">
                <div class="card-header"><h2 class="card-title">How to use it</h2></div>
                <ol class="card-body space-y-3 text-sm text-stone-700">
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">1</span>Scan the code with a phone camera. Family members can scan it too.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">2</span>A 6-digit PIN is sent to <strong>{{ $booking->client->email }}</strong>, so only you can open it.</li>
                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-800 text-xs font-semibold text-gold-200">3</span>Enter the PIN to see your milestones, suppliers, and payments.</li>
                </ol>
            </div>
            <div class="rounded-xl border border-line bg-white px-4 py-3 text-sm text-stone-600">
                Already logged in? You can also open your <a href="{{ route('client.status', $booking) }}" class="link">full status page</a> directly.
            </div>
        </div>
    </div>
</x-layouts.app>
