<x-layouts.app title="Meetings" heading="Meet Your Planner"
    subheading="Pick a date and time to meet your Wedding Planner. They'll confirm it or suggest another time.">
    @if ($pendingBooking)
        <div class="mb-6 rounded-xl border border-gold-200 bg-gold-50 px-5 py-4 text-sm text-stone-700">
            <strong>Your down payment is made at this meeting.</strong>
            Meet your planner in person to {{ $pendingBooking->missingBeforeConfirm() ? 'agree on the '.\Illuminate\Support\Arr::join($pendingBooking->missingBeforeConfirm(), ', ', ' and ').' and ' : 'go over '.$pendingBooking->package.' and ' }}pay the down payment. Your booking for {{ $pendingBooking->event_date->format('F j, Y') }} is confirmed after that.
            <div class="mt-1 text-xs text-stone-500">Cancellation policy: {{ \App\Models\Booking::cancellationPolicy() }}</div>
        </div>
    @endif
    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="card lg:col-span-1">
            <div class="card-header"><h2 class="card-title">Ask for a meeting</h2></div>
            @if (! $planner)
                <div class="card-body space-y-4">
                    <p class="text-sm text-stone-600">Choose your Wedding Planner first, then come back here to set a meeting.</p>
                    <a href="{{ route('client.planner') }}" class="btn-primary">Choose your planner</a>
                </div>
            @else
                <form method="POST" action="{{ route('client.meetings.store') }}" class="card-body space-y-4">
                    @csrf
                    <p class="text-sm text-stone-600">With <strong>{{ $planner->name }}</strong></p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <div>
                            <label for="date" class="label">Date</label>
                            <input id="date" name="date" type="date" required min="{{ today()->toDateString() }}" value="{{ old('date') }}"
                                @class(['input', 'input-error' => $errors->has('date')])>
                            @error('date')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="time" class="label">Time</label>
                            <input id="time" name="time" type="time" required step="900" value="{{ old('time', '10:00') }}"
                                @class(['input', 'input-error' => $errors->has('time')])>
                            @error('time')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="mode" class="label">How you'd like to meet</label>
                        <select id="mode" name="mode" class="input">
                            @foreach ($modes as $value => $label)
                                <option value="{{ $value }}" @selected(old('mode', request('mode', 'in_person')) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="location" class="label">Place or link <span class="font-normal text-stone-400">(optional)</span></label>
                        <input id="location" name="location" maxlength="200" list="cebu-meetup" autocomplete="off" value="{{ old('location') }}" class="input"
                            placeholder="Start typing for Cebu places, or paste a Google Meet link">
                        <x-place-options id="cebu-meetup" type="meetup" />
                    </div>
                    <div>
                        <label for="agenda" class="label">What you'd like to talk about <span class="font-normal text-stone-400">(optional)</span></label>
                        <textarea id="agenda" name="agenda" rows="3" maxlength="500" class="input"
                            placeholder="e.g. Theme ideas, package inclusions, budget">{{ old('agenda', request('agenda')) }}</textarea>
                    </div>
                    <button class="btn-primary w-full">Send meeting request</button>
                </form>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Upcoming meetings</h2></div>
                <ul class="divide-y divide-line">
                    @forelse ($upcoming as $m)
                        <li class="flex flex-wrap items-start gap-4 px-5 py-4 text-sm">
                            <div class="min-w-0 flex-1">
                                <div class="font-display text-lg font-semibold">{{ $m->when() }}</div>
                                <div class="text-stone-500">{{ $m->modeLabel() }}@if ($m->location) · {{ $m->location }}@endif · with {{ $m->planner->name }}</div>
                                @if ($m->agenda)<div class="mt-1 text-stone-600">"{{ $m->agenda }}"</div>@endif
                                @if ($m->response_note)<div class="mt-1 text-xs text-stone-500">Planner's note: "{{ $m->response_note }}"</div>@endif
                            </div>
                            <span class="badge-{{ $m->statusBadge() }}">{{ $m->status === 'requested' ? 'Waiting for reply' : ucfirst($m->status) }}</span>
                            <form method="POST" action="{{ route('client.meetings.cancel', $m) }}" data-confirm="Cancel this meeting?">
                                @csrf @method('PATCH')
                                <button class="btn-secondary btn-sm">Cancel</button>
                            </form>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-stone-500">No upcoming meetings yet.</li>
                    @endforelse
                </ul>
            </div>

            @if ($past->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Past and declined</h2></div>
                    <ul class="divide-y divide-line">
                        @foreach ($past as $m)
                            <li class="flex flex-wrap items-start gap-4 px-5 py-3 text-sm">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium">{{ $m->when() }}</div>
                                    <div class="text-xs text-stone-500">{{ $m->modeLabel() }} · with {{ $m->planner->name }}@if ($m->response_note) · "{{ $m->response_note }}"@endif</div>
                                </div>
                                <span class="badge-{{ $m->statusBadge() }}">{{ $m->status === 'requested' ? 'No reply' : ucfirst($m->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
