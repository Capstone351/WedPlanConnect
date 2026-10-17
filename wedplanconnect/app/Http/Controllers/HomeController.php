<?php

namespace App\Http\Controllers;

use App\Models\PortfolioEntry;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Portfolio weddings with real photos (entries without photos stay in the Our Weddings gallery only).
        $weddings = PortfolioEntry::published()->whereHas('photos')->with('photos')
            ->orderByDesc('is_featured')->orderByDesc('event_date')->limit(6)->get();

        return view('home', [
            'weddings' => $weddings,
            'weddingCount' => PortfolioEntry::published()->count(),
        ]);
    }
}
