@php
    $editing = $booking->exists;
    $mode = old('new_client_email') ? 'new' : 'existing';
@endphp
<x-layouts.app :title="$editing ? 'Edit Booking' : 'New Booking'" :heading="$editing ? 'Edit Booking #'.$booking->id : 'New Wedding Booking'"
    subheading="The system checks for date-and-venue conflicts before saving.">
    <form method="POST" action="{{ $editing ? route('bookings.update', $booking) : route('bookings.store') }}" class="grid gap-6 lg:grid-cols-3 lg:items-start">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Event details</h2></div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="event_date" class="label">Event date</label>
                    <input id="event_date" name="event_date" type="date" value="{{ old('event_date', $booking->event_date?->toDateString()) }}"
                        min="{{ $editing ? '' : today()->toDateString() }}" required @class(['input', 'input-error' => $errors->has('event_date')])>
                    @error('event_date')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="venue" class="label">Venue</label>
                    <input id="venue" name="venue" value="{{ old('venue', $booking->venue) }}" required maxlength="200" placeholder="e.g. Casa Gorordo Museum, Cebu City" class="input">
                </div>
                <div>
                    <label for="package" class="label">Package</label>
                    <input id="package" name="package" list="packages" value="{{ old('package', $booking->package) }}" required maxlength="100" class="input">
                    <datalist id="packages">@foreach ($packages as $p)<option value="{{ $p }}">@endforeach</datalist>
                </div>
                <div>
                    <label for="total_amount" class="label">Contract amount (₱)</label>
                    <input id="total_amount" name="total_amount" type="number" min="0" step="0.01" value="{{ old('total_amount', $booking->total_amount) }}" required class="input">
                </div>
                @if (auth()->user()->role === 'admin')
                    <div class="sm:col-span-2">
                        <label for="planner_id" class="label">Assigned wedding planner</label>
                        <select id="planner_id" name="planner_id" required class="input">
                            <option value="">Select a planner…</option>
                            @foreach ($planners as $p)
                                <option value="{{ $p->id }}" @selected(old('planner_id', $booking->planner_id) == $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Couple-client</h2></div>
            <div class="card-body space-y-4">
                <div class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="client_mode" value="existing" data-client-mode @checked($mode === 'existing')> Existing account</label>
                    <label class="flex items-center gap-2"><input type="radio" name="client_mode" value="new" data-client-mode @checked($mode === 'new')> New client</label>
                </div>
                <div id="client-existing">
                    <label for="client_id" class="label">Client account</label>
                    <select id="client_id" name="client_id" class="input" data-client-select>
                        <option value="">Select a client…</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" data-name="{{ $c->name }}" data-phone="{{ $c->phone }}" @selected(old('client_id', $booking->client_id) == $c->id)>{{ $c->name }} — {{ $c->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="client-new" class="hidden space-y-4">
                    <div>
                        <label for="new_client_name" class="label">Account name</label>
                        <input id="new_client_name" name="new_client_name" value="{{ old('new_client_name') }}" maxlength="150" class="input">
                    </div>
                    <div>
                        <label for="new_client_email" class="label">Email</label>
                        <input id="new_client_email" name="new_client_email" type="email" value="{{ old('new_client_email') }}" maxlength="150" class="input">
                        <p class="hint">The client receives a link to set their password. OTP codes for the QR status page go to this address.</p>
                    </div>
                </div>
                <hr class="border-stone-100">
                <div>
                    <label for="client_name" class="label">Display name on contract</label>
                    <input id="client_name" name="client_name" value="{{ old('client_name', $booking->client_name) }}" required maxlength="150" placeholder="Ana & Miguel Santos" class="input">
                </div>
                <div>
                    <label for="contact_number" class="label">Contact number</label>
                    <input id="contact_number" name="contact_number" value="{{ old('contact_number', $booking->contact_number) }}" required maxlength="20" placeholder="+639171234567" class="input">
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 lg:col-span-3">
            <a href="{{ $editing ? route('bookings.show', $booking) : route('bookings.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Check availability & save' }}</button>
        </div>
    </form>
</x-layouts.app>
