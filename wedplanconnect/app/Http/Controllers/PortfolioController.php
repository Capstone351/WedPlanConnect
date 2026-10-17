<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PortfolioEntry;
use App\Models\PortfolioPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff management (admin and planners) of the public "Our Weddings" portfolio.
 * Photos are stored outside the web root and served only for published entries.
 */
class PortfolioController extends Controller
{
    public const DISK = 'local';

    public function index(): View
    {
        return view('portfolio.index', [
            'entries' => PortfolioEntry::with('photos')->withCount('photos')->latest('event_date')->latest('id')->get(),
            'completedWithoutEntry' => Booking::where('status', 'completed')
                ->whereDoesntHave('portfolioEntry')
                ->orderByDesc('event_date')->limit(6)->get(['id', 'client_name', 'event_date', 'venue']),
        ]);
    }

    public function create(Request $request): View
    {
        $entry = new PortfolioEntry;

        // Pre-fill from a completed booking ("Add to portfolio").
        if ($request->filled('booking_id')) {
            $booking = Booking::findOrFail($request->integer('booking_id'));
            $entry->fill([
                'booking_id' => $booking->id,
                'title' => $this->firstNames($booking->client_name),
                'event_date' => $booking->event_date,
                'venue' => $booking->venue,
                'theme' => $booking->package,
            ]);
        }

        return view('portfolio.form', ['entry' => $entry]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entry = PortfolioEntry::create($this->validated($request));
        $this->storePhotos($request, $entry);

        return redirect()->route('portfolio.edit', $entry)->with('status', $this->savedMessage($entry));
    }

    public function edit(PortfolioEntry $portfolio): View
    {
        return view('portfolio.form', ['entry' => $portfolio->load('photos')]);
    }

    public function update(Request $request, PortfolioEntry $portfolio): RedirectResponse
    {
        $portfolio->update($this->validated($request, $portfolio));
        $this->storePhotos($request, $portfolio);

        foreach ((array) $request->input('captions', []) as $photoId => $caption) {
            $portfolio->photos()->whereKey($photoId)->update(['caption' => mb_substr((string) $caption, 0, 200) ?: null]);
        }

        return redirect()->route('portfolio.edit', $portfolio)->with('status', $this->savedMessage($portfolio));
    }

    public function destroy(PortfolioEntry $portfolio): RedirectResponse
    {
        $portfolio->delete();

        return redirect()->route('portfolio.index')->with('status', "“{$portfolio->title}” removed from the portfolio.");
    }

    /** Make a photo the cover (first in display order). */
    public function cover(PortfolioEntry $portfolio, PortfolioPhoto $photo): RedirectResponse
    {
        abort_unless($photo->portfolio_entry_id === $portfolio->id, 404);

        $portfolio->photos()->where('id', '!=', $photo->id)->increment('sort_order');
        $photo->update(['sort_order' => 0]);

        return back()->with('status', 'Cover photo updated.');
    }

    public function destroyPhoto(PortfolioEntry $portfolio, PortfolioPhoto $photo): RedirectResponse
    {
        abort_unless($photo->portfolio_entry_id === $portfolio->id, 404);

        Storage::disk(self::DISK)->delete($photo->path);
        $photo->delete();

        return back()->with('status', 'Photo removed.');
    }

    private function validated(Request $request, ?PortfolioEntry $entry = null): array
    {
        $data = $request->validate([
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')],
            'title' => ['required', 'string', 'max:150'],
            'event_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:200'],
            'theme' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:300'],
            'story' => ['nullable', 'string', 'max:5000'],
            'testimonial' => ['nullable', 'string', 'max:1500'],
            'testimonial_by' => ['nullable', 'string', 'max:150'],
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_published' => ['nullable', 'boolean'],
            'consent_confirmed' => [$request->boolean('is_published') ? 'accepted' : 'nullable'],
        ], [
            'consent_confirmed.accepted' => 'To publish, confirm that the couple gave written consent to share their photos and details.',
            'photos.*.max' => 'Each photo must be 4 MB or smaller.',
            'photos.*.image' => 'Photos must be JPG, PNG, or WebP images.',
        ]);

        unset($data['photos']);
        $data['consent_confirmed'] = $request->boolean('consent_confirmed');
        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');

        return $data;
    }

    private function storePhotos(Request $request, PortfolioEntry $entry): void
    {
        $next = (int) $entry->photos()->max('sort_order') + 1;

        foreach ($request->file('photos', []) as $file) {
            $entry->photos()->create([
                'path' => $file->store('portfolio', self::DISK),
                'sort_order' => $entry->photos()->exists() ? $next++ : 0,
            ]);
        }
    }

    private function savedMessage(PortfolioEntry $entry): string
    {
        return $entry->is_published
            ? "“{$entry->title}” is saved and visible on the website."
            : "“{$entry->title}” is saved as a draft (not visible on the website yet).";
    }

    /** "Ana & Miguel Santos" -> "Ana & Miguel" (first names only, for privacy). */
    private function firstNames(string $name): string
    {
        $parts = preg_split('/\s*&\s*/', $name);

        return count($parts) === 2 ? trim($parts[0]).' & '.strtok(trim($parts[1]), ' ') : $name;
    }
}
