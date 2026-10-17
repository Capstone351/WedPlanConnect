@php $editing = $entry->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit Portfolio Wedding' : 'Add Portfolio Wedding'" :heading="$editing ? $entry->title : 'Add a Past Wedding'"
    subheading="Shown on the public website. Use the couple's first names only, and publish only with their written consent.">
    <x-slot:actions>
        <a href="{{ route('portfolio.index') }}" class="btn-secondary">Back to portfolio</a>
        @if ($editing && $entry->is_published)
            <a href="{{ route('weddings.show', $entry) }}" target="_blank" class="btn-outline">View on site ↗</a>
        @endif
    </x-slot:actions>

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('portfolio.update', $entry) : route('portfolio.store') }}" class="grid gap-6 lg:grid-cols-3 lg:items-start">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="booking_id" value="{{ old('booking_id', $entry->booking_id) }}">

        <div class="space-y-6 lg:col-span-2">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Wedding details</h2></div>
                <div class="card-body grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="title" class="label">Couple (shown as the title)</label>
                        <input id="title" name="title" value="{{ old('title', $entry->title) }}" required maxlength="150" class="input" placeholder="e.g. Ana & Miguel">
                        <p class="hint">First names only is recommended for privacy.</p>
                    </div>
                    <div>
                        <label for="event_date" class="label">Wedding date</label>
                        <input id="event_date" name="event_date" type="date" value="{{ old('event_date', $entry->event_date?->toDateString()) }}" class="input">
                    </div>
                    <div>
                        <label for="theme" class="label">Theme / package</label>
                        <input id="theme" name="theme" value="{{ old('theme', $entry->theme) }}" maxlength="100" class="input" placeholder="e.g. Garden Romance">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="venue" class="label">Venue</label>
                        <input id="venue" name="venue" value="{{ old('venue', $entry->venue) }}" maxlength="200" class="input" placeholder="e.g. Casa Gorordo Museum, Cebu City">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="summary" class="label">Short description</label>
                        <input id="summary" name="summary" value="{{ old('summary', $entry->summary) }}" maxlength="300" class="input" placeholder="One sentence shown on the gallery card.">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="story" class="label">The story <span class="font-normal text-stone-400">(optional)</span></label>
                        <textarea id="story" name="story" rows="6" maxlength="5000" class="input" placeholder="Colors, flowers, special touches, how the day felt… Leave a blank line between paragraphs.">{{ old('story', $entry->story) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Photos</h2>
                    <span class="text-xs text-stone-500">The first photo is the cover</span>
                </div>
                <div class="card-body space-y-5">
                    @if ($editing && $entry->photos->isNotEmpty())
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($entry->photos as $photo)
                                <div @class(['overflow-hidden rounded-xl border', 'border-gold-400 ring-2 ring-gold-300' => $loop->first, 'border-line' => ! $loop->first])>
                                    <div class="relative">
                                        <img src="{{ $photo->url() }}" alt="" class="aspect-[4/3] w-full object-cover">
                                        @if ($loop->first)<span class="badge-gold absolute top-2 left-2">Cover</span>@endif
                                    </div>
                                    <div class="space-y-2 p-2">
                                        <input name="captions[{{ $photo->id }}]" value="{{ $photo->caption }}" maxlength="200" class="input py-1.5 text-xs" placeholder="Caption (optional)" aria-label="Caption">
                                        <div class="flex items-center gap-1">
                                            @unless ($loop->first)
                                                <button form="cover-{{ $photo->id }}" class="btn-secondary btn-sm">Make cover</button>
                                            @endunless
                                            <button form="delete-{{ $photo->id }}" class="btn-icon ml-auto" title="Remove photo" aria-label="Remove photo"><x-icon name="trash" class="size-4" /></button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div>
                        <label for="photos" class="label">{{ $editing && $entry->photos->isNotEmpty() ? 'Add more photos' : 'Upload photos' }}</label>
                        <input id="photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-stone-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gold-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gold-700 hover:file:bg-gold-100">
                        <p class="hint">JPG, PNG, or WebP, up to 4 MB each, up to 20 at a time. Hold Ctrl to pick several.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Testimonial <span class="text-sm font-normal text-stone-400">(optional)</span></h2></div>
                <div class="card-body grid gap-5">
                    <div>
                        <label for="testimonial" class="label">What the couple said</label>
                        <textarea id="testimonial" name="testimonial" rows="3" maxlength="1500" class="input">{{ old('testimonial', $entry->testimonial) }}</textarea>
                    </div>
                    <div>
                        <label for="testimonial_by" class="label">Signed as</label>
                        <input id="testimonial_by" name="testimonial_by" value="{{ old('testimonial_by', $entry->testimonial_by) }}" maxlength="150" class="input" placeholder="e.g. Ana & Miguel">
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6 lg:sticky lg:top-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Publishing</h2></div>
                <div class="card-body space-y-4 text-sm">
                    <label class="flex items-start gap-2.5">
                        <input type="checkbox" name="consent_confirmed" value="1" @checked(old('consent_confirmed', $entry->consent_confirmed)) class="mt-0.5 rounded border-stone-300 text-brand-600">
                        <span><strong>The couple gave written consent</strong> to show these photos and details publicly (Data Privacy Act of 2012, RA 10173).</span>
                    </label>
                    <label class="flex items-start gap-2.5">
                        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $entry->is_published)) class="mt-0.5 rounded border-stone-300 text-brand-600">
                        <span><strong>Publish on the website</strong><br><span class="text-xs text-stone-500">Unticked = saved as a private draft.</span></span>
                    </label>
                    <label class="flex items-start gap-2.5">
                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $entry->is_featured)) class="mt-0.5 rounded border-stone-300 text-brand-600">
                        <span><strong>Feature on the front page</strong><br><span class="text-xs text-stone-500">Its cover photo becomes the front page background.</span></span>
                    </label>
                    <button class="btn-primary w-full">{{ $editing ? 'Save changes' : 'Save wedding' }}</button>
                </div>
            </div>
            @if ($entry->booking_id)
                <p class="px-1 text-xs text-stone-500">Linked to <a href="{{ route('bookings.show', $entry->booking_id) }}" class="link">booking #{{ $entry->booking_id }}</a>. Only the details above are shown publicly; contact details and payments never are.</p>
            @endif
        </div>
    </form>

    {{-- Separate forms for photo actions (HTML forms can't be nested) --}}
    @if ($editing)
        @foreach ($entry->photos as $photo)
            <form id="cover-{{ $photo->id }}" method="POST" action="{{ route('portfolio.photos.cover', [$entry, $photo]) }}" class="hidden">@csrf @method('PATCH')</form>
            <form id="delete-{{ $photo->id }}" method="POST" action="{{ route('portfolio.photos.destroy', [$entry, $photo]) }}" class="hidden" data-confirm="Remove this photo?">@csrf @method('DELETE')</form>
        @endforeach
    @endif
</x-layouts.app>
