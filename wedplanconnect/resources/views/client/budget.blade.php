@php
    $total = old('total_amount', $budget?->total_amount);
    $hints = [
        'Coordination' => 'Wedding planning and on-the-day coordination',
        'Event Styling' => 'Décor: arches, florals, table setup',
        'Photo & Video' => 'Photo coverage, video, and same-day edit',
        'Entourage Dress(es)' => 'Gowns and attire for the entourage',
        'Catering' => 'Food and drinks for your guests',
        'Venue' => 'Ceremony and reception venue',
    ];
@endphp
<x-layouts.app title="My Budget" heading="My Wedding Budget"
    subheading="Tell your Wedding Planner how much you'd like to spend. They'll plan suppliers and décor within it.">
    <form method="POST" action="{{ route('client.budget.update') }}" class="grid gap-6 lg:grid-cols-3 lg:items-start" data-budget-form>
        @csrf @method('PUT')

        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Your budget</h2></div>
                <div class="card-body grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="total_amount" class="label">Total wedding budget (₱)</label>
                        <input id="total_amount" name="total_amount" type="text" inputmode="decimal" required value="{{ $total !== null ? number_format((float) $total, 0, '.', ',') : '' }}"
                            placeholder="e.g. 250,000" data-budget-total data-money
                            @class(['input text-lg font-semibold', 'input-error' => $errors->has('total_amount')])>
                        @error('total_amount')<p class="error-text">{{ $message }}</p>@else<p class="hint">Your overall amount for the whole wedding.</p>@enderror
                    </div>
                    <div>
                        <label for="guest_count" class="label">Expected number of guests <span class="font-normal text-stone-400">(optional)</span></label>
                        <input id="guest_count" name="guest_count" type="number" min="1" max="5000" value="{{ old('guest_count', $budget?->guest_count) }}"
                            placeholder="e.g. 150" data-budget-guests @class(['input', 'input-error' => $errors->has('guest_count')])>
                        @error('guest_count')<p class="error-text">{{ $message }}</p>@else<p class="hint">Helps with catering and table settings.</p>@enderror
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Breakdown <span class="text-sm font-normal text-stone-400">(optional)</span></h2>
                    <span class="text-xs text-stone-500">Leave blank anything you're unsure about</span>
                </div>
                @error('allocations')
                    <div class="mx-5 mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700 ring-1 ring-red-200">{{ $message }}</div>
                @enderror
                <div class="card-body grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ($categories as $category)
                        <div>
                            <label for="alloc-{{ $loop->index }}" class="label">{{ $category }}</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-stone-400">₱</span>
                                <input id="alloc-{{ $loop->index }}" name="allocations[{{ $category }}]" type="text" inputmode="decimal"
                                    value="{{ old('allocations.'.$category, isset($amounts[$category]) ? number_format((float) $amounts[$category], 0, '.', ',') : '') }}"
                                    placeholder="0" class="input pl-7" data-budget-alloc data-money>
                            </div>
                            @isset($hints[$category])<p class="hint">{{ $hints[$category] }}</p>@endisset
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Notes for your planner <span class="text-sm font-normal text-stone-400">(optional)</span></h2></div>
                <div class="card-body">
                    <textarea name="notes" rows="4" maxlength="2000" class="input"
                        placeholder="e.g. We'd rather spend more on flowers than on the cake. Our budget can stretch a little for the venue.">{{ old('notes', $budget?->notes) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Live summary --}}
        <div class="space-y-4 lg:sticky lg:top-6">
            <div class="hero-panel">
                <div class="relative p-6">
                    <div class="eyebrow text-gold-300">Total budget</div>
                    <div class="mt-1 font-display text-4xl font-semibold text-gold-gradient tabular-nums" data-budget-show-total>₱{{ number_format((float) $total, 0) }}</div>
                    <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/15">
                        <div class="h-2 rounded-full bg-gradient-to-r from-gold-300 to-gold-500 transition-all" style="width: 0%" data-budget-bar></div>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-brand-100/80">Allocated</dt><dd class="font-semibold tabular-nums" data-budget-allocated>₱0</dd></div>
                        <div class="flex justify-between"><dt class="text-brand-100/80">Not yet allocated</dt><dd class="font-semibold tabular-nums" data-budget-remaining>₱0</dd></div>
                        <div class="flex justify-between"><dt class="text-brand-100/80">Per guest</dt><dd class="font-semibold tabular-nums" data-budget-per-guest>–</dd></div>
                    </dl>
                    <p class="mt-3 hidden rounded-lg bg-red-500/20 px-3 py-2 text-xs text-red-100 ring-1 ring-red-300/40" data-budget-over>
                        Your breakdown is more than your total budget.
                    </p>
                </div>
            </div>
            <button class="btn-primary w-full py-3">Save my budget</button>
            <p class="px-1 text-xs text-stone-500">
                Only you and {{ config('wedplan.business_name') }}'s planners can see this.
                @if ($budget)<br>Last updated {{ $budget->updated_at->diffForHumans() }}.@endif
            </p>
        </div>
    </form>
</x-layouts.app>
