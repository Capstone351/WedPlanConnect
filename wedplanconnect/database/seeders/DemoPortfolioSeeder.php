<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\PortfolioEntry;
use Illuminate\Database\Seeder;

/**
 * SAMPLE portfolio entries for demonstrations only (fictional couples, no photos).
 * Replace them with FMT's real weddings and photos under Portfolio.
 * Runs as part of DemoSeeder, or alone: php artisan db:seed --class=DemoPortfolioSeeder
 */
class DemoPortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $completed = Booking::where('status', 'completed')->where('client_name', 'like', 'Faith%')->first();

        $entries = [
            [
                'booking_id' => $completed?->id,
                'title' => 'Faith & Noel',
                'event_date' => $completed?->event_date ?? now()->subMonth(),
                'venue' => 'Waterfront Cebu City Hotel',
                'theme' => 'Classic Elegance',
                'summary' => 'An ivory-and-gold ballroom reception with cascading florals and candlelit tables.',
                'story' => "Faith and Noel wanted a timeless celebration for their families. We dressed the ballroom in ivory drapes, gold chargers, and tall centerpieces of white roses and baby's breath.\n\nA floral arch framed the couple's table, and warm fairy lights carried the glow from the ceremony to the last dance.",
                'testimonial' => 'Everything looked exactly like our mood board, only more beautiful. We could finally relax and enjoy our day.',
                'testimonial_by' => 'Faith & Noel',
                'is_featured' => true,
            ],
            [
                'title' => 'Isabel & Marco',
                'event_date' => '2026-02-14',
                'venue' => 'Casa Gorordo Museum, Cebu City',
                'theme' => 'Garden Romance',
                'summary' => "A Valentine's Day garden wedding in blush and sage among heritage stone walls.",
                'story' => "Under the old acacia trees of Casa Gorordo, we created an aisle of blush peonies and eucalyptus garlands.\n\nThe reception tables mixed wooden chargers with crystal glassware for a romantic, relaxed garden feel.",
                'testimonial' => 'Our guests still talk about the flowers. Thank you for making our heritage-garden dream real.',
                'testimonial_by' => 'Isabel & Marco',
            ],
            [
                'title' => 'Trisha & Jun',
                'event_date' => '2025-12-06',
                'venue' => 'Shangri-La Mactan Resort',
                'theme' => 'Beach Wedding',
                'summary' => 'A sunset ceremony by the sea with tropical blooms and lantern-lit dinner tables.',
                'story' => 'We built a bamboo arch draped in white chiffon right on the sand, with tropical florals in coral and cream. As the sun set, lanterns lined the path to a seaside dinner under the stars.',
            ],
            [
                'title' => 'Camille & Andre',
                'event_date' => '2025-09-20',
                'venue' => 'Marco Polo Plaza Cebu',
                'theme' => 'Grand Ballroom',
                'summary' => 'A black-tie evening of gold accents, crystal chandeliers, and towering arrangements.',
            ],
            [
                'title' => 'Lea & Paolo',
                'event_date' => '2025-06-28',
                'venue' => 'Radisson Blu Cebu',
                'theme' => 'Rustic Charm',
                'summary' => 'Wood, greenery, and Edison lights for a warm, laid-back celebration with friends.',
            ],
            [
                'title' => 'Mika & Ron',
                'event_date' => '2025-04-12',
                'venue' => 'Sacred Heart Parish, Cebu City',
                'theme' => 'Intimate Ceremony',
                'summary' => 'An intimate church ceremony with white florals and a candlelit family dinner.',
            ],
        ];

        foreach ($entries as $data) {
            PortfolioEntry::firstOrCreate(
                ['title' => $data['title']],
                $data + ['consent_confirmed' => true, 'is_published' => true],
            );
        }
    }
}
