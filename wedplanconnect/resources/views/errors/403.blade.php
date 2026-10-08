<x-layouts.guest title="Access restricted">
    <div class="lattice relative flex min-h-screen items-center justify-center overflow-hidden bg-brand-950 px-4 py-12">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-800/80 via-brand-900/90 to-brand-950"></div>
        <div class="card relative w-full max-w-lg p-8 text-center sm:p-10">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-gradient-to-b from-gold-100 to-gold-200 text-gold-700 ring-1 ring-gold-300">
                <x-icon name="lock" class="size-7" />
            </div>
            <div class="mt-4 eyebrow text-gold-600">Access restricted</div>
            <h1 class="mt-1 font-display text-3xl font-semibold">This page isn't for your account</h1>
            <x-ornament class="mx-auto mt-4 w-40" />
            <p class="mt-4 text-sm text-stone-600">
                {{ $exception->getMessage() && $exception->getMessage() !== 'You are not authorized to access this page.' ? $exception->getMessage() : 'Your account role does not have access to this page.' }}
            </p>

            @auth
                <p class="mt-4 rounded-lg bg-gold-50 px-4 py-3 text-sm text-stone-700">
                    You're signed in as <strong>{{ auth()->user()->name }}</strong>
                    <span class="badge-gold ml-1">{{ auth()->user()->roleLabel() }}</span><br>
                    <span class="text-xs text-stone-500">{{ auth()->user()->email }}</span>
                </p>
                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('dashboard') }}" class="btn-primary">Go to my dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary">Switch account</button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn-primary mt-6">Log in</a>
            @endauth
        </div>
    </div>
</x-layouts.guest>
