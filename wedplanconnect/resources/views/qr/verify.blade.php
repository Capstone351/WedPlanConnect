<x-layouts.guest title="Verify it's you">
    <div class="min-h-screen bg-canvas">
        <div class="lattice relative overflow-hidden bg-brand-950 px-4 pt-10 pb-24 text-center text-white">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-800/80 to-brand-950"></div>
            <div class="relative">
                <div class="mx-auto grid size-14 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900 ring-4 ring-gold-400/15"><x-icon name="lock" class="size-7" /></div>
                <div class="mt-4 eyebrow text-gold-300">{{ config('wedplan.business_name') }}</div>
                <h1 class="mt-2 font-display text-3xl font-semibold text-white">Verify it's you</h1>
                <p class="mx-auto mt-2 max-w-sm text-sm text-brand-100/85">For your privacy, we've emailed a 6-digit PIN to <strong class="text-gold-200">{{ $maskedEmail }}</strong>. Enter it below to see your wedding status.</p>
            </div>
        </div>

        <div class="relative mx-auto -mt-16 w-full max-w-md px-4 pb-10">
            <div class="card p-6">
                <x-flash />
                <form method="POST" action="{{ route('qr.verify', $token) }}" class="space-y-4">
                    @csrf
                    <label for="otp" class="label text-center">One-Time PIN</label>
                    <input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus
                        class="input text-center text-2xl tracking-[0.5em] tabular-nums" placeholder="••••••">
                    <button class="btn-primary w-full py-2.5">Verify & view status</button>
                </form>
                <div class="mt-5 rounded-lg bg-canvas px-4 py-3 text-xs text-stone-600">
                    <p class="font-medium text-ink">Didn't receive it?</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-4">
                        <li>Check your Spam or Promotions folder.</li>
                        <li>The PIN expires after {{ config('wedplan.otp_ttl_minutes') }} minutes and works only once.</li>
                    </ul>
                    <form method="POST" action="{{ route('qr.send', $token) }}" class="mt-2">
                        @csrf
                        <button class="link">Send me a new PIN</button>
                    </form>
                </div>
                @if (\App\Models\OutboxMessage::enabled())
                    <p class="mt-3 rounded-lg bg-gold-50 px-3 py-2 text-center text-xs text-gold-700 ring-1 ring-gold-200">
                        <strong>Test mode:</strong> emails aren't delivered yet. Staff can read this PIN under <strong>Email outbox</strong> in WedPlanConnect.
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-layouts.guest>
