<?php

namespace App\Http\Controllers;

use App\Models\PortfolioEntry;
use App\Models\PortfolioPhoto;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public "Our Weddings" pages: published portfolio entries only.
 */
class WeddingGalleryController extends Controller
{
    public function index(): View
    {
        return view('weddings.index', [
            'entries' => PortfolioEntry::published()->with('photos')
                ->orderByDesc('is_featured')->orderByDesc('event_date')->paginate(12),
        ]);
    }

    public function show(PortfolioEntry $entry): View
    {
        abort_unless($entry->is_published && $entry->consent_confirmed, 404);

        return view('weddings.show', [
            'entry' => $entry->load('photos'),
            'more' => PortfolioEntry::published()->whereKeyNot($entry->id)->with('photos')->inRandomOrder()->limit(3)->get(),
        ]);
    }

    /** Serves a photo publicly only when its wedding is published; staff can always preview. */
    public function photo(PortfolioPhoto $photo): StreamedResponse
    {
        $entry = $photo->entry;
        $staff = auth()->check() && auth()->user()->hasRole('admin', 'planner');

        abort_unless($staff || ($entry && ! $entry->trashed() && $entry->is_published && $entry->consent_confirmed), 404);
        abort_unless(Storage::disk(PortfolioController::DISK)->exists($photo->path), 404);

        return Storage::disk(PortfolioController::DISK)->response($photo->path, null, [
            // Short cache so unpublishing a wedding takes effect within minutes.
            'Cache-Control' => $staff ? 'private, max-age=60' : 'public, max-age=300',
        ]);
    }
}
