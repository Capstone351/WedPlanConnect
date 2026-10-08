@props(['title' => null, 'heading' => null, 'subheading' => null])
@php
    $user = auth()->user();
    $operations = [
        ['Bookings', 'bookings.index', 'bookings.*', 'calendar'],
        ['Suppliers', 'suppliers.index', 'suppliers.*', 'store'],
        ['Tasks', 'tasks.index', 'tasks.*', 'clipboard'],
        ['Inventory', 'inventory.index', 'inventory.*', 'cube'],
        ['Payments', 'payments.index', 'payments.*', 'cash'],
        ['Reports', 'reports.index', 'reports.*', 'chart'],
    ];
    // Shown only while emails are not really delivered (MAIL_MAILER=log).
    $testTools = \App\Models\OutboxMessage::enabled()
        ? ['Test mode' => [['Email outbox', 'outbox.index', 'outbox.*', 'bell']]]
        : [];
    // Grouped navigation: section label => [label, route, active pattern, icon]
    $nav = match ($user->role) {
        'admin' => [
            '' => [['Dashboard', 'admin.dashboard', 'admin.dashboard', 'home']],
            'Operations' => $operations,
            'Administration' => [
                ['Users', 'admin.users.index', 'admin.users.*', 'users'],
                ['Chatbot FAQ', 'admin.faqs.index', 'admin.faqs.*', 'chat'],
            ],
        ] + $testTools,
        'planner' => [
            '' => [['Dashboard', 'planner.dashboard', 'planner.dashboard', 'home']],
            'Operations' => $operations,
        ] + $testTools,
        'vendor' => [
            '' => [['Dashboard', 'vendor.dashboard', 'vendor.dashboard', 'home']],
            'My business' => [
                ['Assigned Bookings', 'vendor.bookings', 'vendor.bookings*', 'calendar'],
                ['My Products', 'vendor.products.index', 'vendor.products.*', 'cube'],
                ['My Profile', 'vendor.profile', 'vendor.profile', 'store'],
            ],
        ],
        default => [
            '' => [
                ['My Wedding', 'client.dashboard', 'client.dashboard', 'heart'],
                ['Supplier Catalog', 'client.catalog', 'client.catalog*', 'store'],
            ],
        ],
    };
    $initials = collect(preg_split('/[\s&]+/', $user->name, -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}WedPlanConnect</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div id="sidebar-backdrop" data-sidebar-toggle class="fixed inset-0 z-30 hidden bg-black/30 lg:hidden"></div>

    <aside id="sidebar" class="lattice fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-brand-950 text-white transition-transform lg:translate-x-0 print:hidden">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-800/90 via-brand-900/95 to-brand-950"></div>
        <div class="pointer-events-none absolute inset-y-0 right-0 w-px bg-gradient-to-b from-gold-400/0 via-gold-400/50 to-gold-400/0"></div>
        <a href="{{ route('dashboard') }}" class="relative flex flex-col items-center px-6 pt-7 pb-5 text-center">
            <span class="grid size-12 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900 shadow-[0_6px_18px_-6px_rgba(207,167,90,0.8)] ring-4 ring-gold-400/15">
                <x-icon name="heart" class="size-6" />
            </span>
            <span class="mt-3 block font-display text-xl leading-tight font-semibold tracking-wide text-white">WedPlanConnect</span>
            <span class="mt-0.5 block text-[10.5px] tracking-[0.2em] text-gold-300/90 uppercase">{{ config('wedplan.business_name') }}</span>
            <x-ornament class="mt-3 w-32" :light="true" />
        </a>
        <nav class="relative flex-1 overflow-y-auto px-3 pb-4">
            @foreach ($nav as $section => $items)
                @if ($section !== '')
                    <div class="nav-section">{{ $section }}</div>
                @endif
                <div class="space-y-0.5">
                    @foreach ($items as [$label, $route, $pattern, $icon])
                        <a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($pattern)])>
                            <x-icon :name="$icon" /> {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="relative border-t border-gold-400/20 p-3">
            <a href="{{ route('account.edit') }}" @class(['flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-white/5', 'bg-white/10' => request()->routeIs('account.*')])>
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-800 font-display text-xs font-semibold text-gold-200 uppercase ring-1 ring-gold-400/60">{{ $initials }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-white">{{ $user->name }}</span>
                    <span class="block text-xs text-gold-300/80">{{ $user->roleLabel() }}</span>
                </span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button class="nav-link w-full"><x-icon name="logout" /> Log out</button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-gold-400/30 bg-brand-900 px-4 py-3 text-white lg:hidden print:hidden">
            <button type="button" data-sidebar-toggle class="rounded-lg p-1.5 text-gold-200 hover:bg-white/10" aria-label="Open menu"><x-icon name="menu" /></button>
            <span class="font-display text-lg font-semibold">WedPlanConnect</span>
        </header>

        <main @class(['mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8', 'pb-28' => $user->role === 'client'])>
            @if ($heading)
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 class="font-display text-3xl font-semibold tracking-tight sm:text-[2.35rem]">{{ $heading }}</h1>
                        <x-ornament class="mt-2.5 w-36 [&>span:first-child]:hidden" />
                        @if ($subheading)
                            <p class="mt-2 max-w-3xl text-sm text-stone-500">{{ $subheading }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2 print:hidden">{{ $actions }}</div>
                    @endisset
                </div>
            @endif

            <x-flash />

            {{ $slot }}
        </main>
    </div>

    @if ($user->role === 'client')
        <x-chatbot />
    @endif
</body>
</html>
