<footer class="bg-brand-950 py-12 text-center">
    <div class="font-display text-2xl text-gold-200">{{ config('wedplan.business_full_name') }}</div>
    <div class="mt-1 text-xs tracking-[0.2em] text-gold-300/70 uppercase">Weddings &amp; Events · {{ config('wedplan.business_address') }}</div>
    <x-ornament class="mx-auto mt-4 w-40" :light="true" />
    <div class="mt-5 flex flex-wrap justify-center gap-x-6 gap-y-1 text-sm text-brand-100/80">
        @foreach (config('wedplan.contacts') as $contact)
            <a href="tel:{{ preg_replace('/\D/', '', $contact['phone']) }}" class="hover:text-gold-200">{{ $contact['name'] }} · {{ $contact['phone'] }}</a>
        @endforeach
    </div>
    <nav class="mt-4 flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-brand-100/70">
        <a href="{{ route('home') }}#services" class="hover:text-gold-200">Services</a>
        <a href="{{ route('home') }}#gallery" class="hover:text-gold-200">Gallery</a>
        <a href="{{ route('weddings.index') }}" class="hover:text-gold-200">Our Weddings</a>
        <a href="{{ route('home') }}#contact" class="hover:text-gold-200">Contact</a>
        <a href="{{ route('login') }}" class="hover:text-gold-200">Log in</a>
    </nav>
    <p class="mx-auto mt-6 max-w-2xl px-4 text-xs text-brand-100/50">© {{ date('Y') }} {{ config('wedplan.business_full_name') }} ({{ config('wedplan.business_name') }}). Powered by WedPlanConnect. Photos and weddings are shown with our couples' consent. Personal data is processed in accordance with the Data Privacy Act of 2012 (RA 10173).</p>
</footer>
