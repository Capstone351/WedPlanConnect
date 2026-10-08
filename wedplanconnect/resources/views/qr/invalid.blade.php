<x-layouts.guest title="Link unavailable">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="card max-w-md p-8 text-center">
            <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-stone-100 text-stone-500"><x-icon name="warning" class="size-7" /></div>
            <h1 class="mt-4 font-display text-2xl font-semibold">This status link is not available</h1>
            <p class="mt-2 text-sm text-stone-600">The QR code may be invalid, or the booking is no longer active. Please contact {{ config('wedplan.business_name') }} for assistance.</p>
            <a href="{{ route('home') }}" class="btn-secondary mt-6">Go to homepage</a>
        </div>
    </div>
</x-layouts.guest>
