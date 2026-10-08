@props(['title'])
<x-layouts.guest :title="$title">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="lattice relative hidden flex-col justify-between overflow-hidden bg-brand-950 p-12 text-white lg:flex">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-700/80 via-brand-900/90 to-brand-950"></div>
            <div class="pointer-events-none absolute -top-32 -right-24 size-[28rem] rounded-full bg-gold-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute inset-5 rounded-2xl border border-gold-300/25"></div>

            <a href="{{ route('home') }}" class="relative flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900 ring-4 ring-gold-400/15"><x-icon name="heart" class="size-5" /></span>
                <span class="font-display text-xl font-semibold tracking-wide">WedPlanConnect</span>
            </a>
            <div class="relative">
                <div class="eyebrow text-gold-300">Plan with confidence</div>
                <h2 class="mt-2 font-display text-5xl leading-tight font-semibold text-white">Celebrate<br>without worry.</h2>
                <x-ornament class="mt-6 w-48 [&>span:first-child]:hidden" :light="true" />
                <ul class="mt-8 space-y-5 text-brand-100/85">
                    @foreach ([
                        ['calendar', 'Booking management', 'Centralized schedules with double-booking protection.'],
                        ['store', 'Supplier coordination', "Track every supplier's confirmation status per wedding."],
                        ['clipboard', 'Task tracking', 'Deadlines, priorities, and automatic overdue alerts.'],
                    ] as [$icon, $title, $text])
                        <li class="flex gap-4">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white/5 text-gold-300 ring-1 ring-gold-400/40"><x-icon :name="$icon" class="size-5" /></span>
                            <span><strong class="font-display text-base text-white">{{ $title }}</strong><br>{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="relative text-xs tracking-[0.2em] text-gold-300/80 uppercase">{{ config('wedplan.business_name') }} · {{ config('wedplan.business_address') }}</p>
        </div>
        <div class="flex items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2.5 lg:hidden">
                    <span class="grid size-10 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900"><x-icon name="heart" class="size-5" /></span>
                    <span class="font-display text-xl font-semibold">WedPlanConnect</span>
                </a>
                <x-flash />
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.guest>
