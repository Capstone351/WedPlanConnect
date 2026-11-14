<?php

return [

    /*
    | Business identity shown on pages, contracts, and reports.
    */
    'business_name' => env('WEDPLAN_BUSINESS_NAME', 'FMT Weddings & Events'),
    'business_full_name' => env('WEDPLAN_BUSINESS_FULL_NAME', 'WedPlanConnect'),
    'business_address' => env('WEDPLAN_BUSINESS_ADDRESS', 'Cebu City, Philippines'),

    /*
    | Contact details shown on the public front page (from the business card).
    */
    'contacts' => [
        ['name' => 'Randy Pilar', 'phone' => '0915-044-0664'],
        ['name' => 'Niña Therese Pilar', 'phone' => '0927-352-2181'],
    ],
    'facebook_pages' => [
        ['label' => 'Randy Paredes Pilar', 'url' => 'https://www.facebook.com/search/top?q='.rawurlencode('Randy Paredes Pilar')],
        ['label' => 'Felicity Miracle Therese (Wedding Page)', 'url' => 'https://www.facebook.com/search/top?q='.rawurlencode('Felicity Miracle Therese')],
    ],

    /*
    | Wedding packages couples choose from (Weddings & More by Felicity Miracle Thérese).
    | Every package shares the same inclusions; they differ in price, guests (pax), cake layers,
    | on-the-day coordinators, and freebies.
    */
    'packages' => [
        'Package A' => ['price' => 200000, 'guests' => 100, 'cake_layers' => 4, 'coordinators' => 4, 'freebies' => ['Wine for Toasting', 'Bridesmaids Robe for Dress-up/Makeup']],
        'Package B' => ['price' => 160000, 'guests' => 70, 'cake_layers' => 3, 'coordinators' => 3, 'freebies' => ['Wine for Toasting', 'Bridesmaids Robe for Dress-up/Makeup']],
        'Package C' => ['price' => 130000, 'guests' => 50, 'cake_layers' => 2, 'coordinators' => 2, 'freebies' => ['Wine for Toasting']],
    ],
    /*
    | Inclusion => how many days before the wedding it should be arranged.
    | Each inclusion becomes a task on the booking's preparation list, due that many days before the date.
    */
    'package_inclusions' => [
        'Hotel for Dress-up' => 120,
        'Reception Area' => 180,
        'Venue Decoration' => 60,
        'Church Decoration' => 60,
        'Bridal Car' => 60,
        'Cake' => 45,
        'Bridal Dress' => 120,
        'Bridal Entourage' => 90,
        'Confetti/Poppers' => 14,
        'Photo/Video (w/ Pre-nup)' => 150,
        'Decor w/ Calling' => 60,
        'Make-up (10 persons)' => 60,
        'Emcee w/ DJ' => 90,
        'Invitation (30 pcs.)' => 75,
        'Give-away (30 pcs.)' => 30,
    ],
    'package_coordinators_lead_days' => 30,
    'package_freebies_lead_days' => 14,
    /*
    | How much each inclusion matters to the wedding (not how early it is due).
    | Inclusions not listed are medium; freebies are always low.
    */
    'package_high_priority' => ['Reception Area', 'Church Decoration', 'Venue Decoration', 'Bridal Dress', 'Photo/Video (w/ Pre-nup)', 'Hotel for Dress-up'],
    'package_low_priority' => ['Confetti/Poppers', 'Give-away (30 pcs.)'],

    /*
    | Double-booking detection scope.
    | 'date_venue' — conflict when an active booking shares the same event date AND venue (manuscript definition).
    | 'date'       — conflict when any active booking exists on the same event date.
    */
    'conflict_scope' => env('WEDPLAN_CONFLICT_SCOPE', 'date_venue'),

    /*
    | Days an unconfirmed (pending) booking holds the planner's date before it is released.
    */
    'pending_hold_days' => (int) env('WEDPLAN_PENDING_HOLD_DAYS', 14),

    /*
    | QR + OTP status portal.
    */
    'otp_ttl_minutes' => 10,
    'otp_max_attempts' => 5,
    'qr_session_minutes' => 30,
    // Public address (e.g. an ngrok domain) used in QR codes so they open from any network, not just this Wi-Fi.
    'public_url' => env('PUBLIC_URL'),

    /*
    | Chatbot: keyword-match confidence threshold (0–1) and optional Gemini fallback.
    */
    'chatbot' => [
        'threshold' => (float) env('CHATBOT_THRESHOLD', 0.5),
        'gemini_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'gemini_backup_model' => env('GEMINI_BACKUP_MODEL', 'gemini-3.5-flash-lite'),
        'fallback' => "I'm sorry, I don't have an answer for that yet. Please contact your Wedding Planner directly and they will be happy to help.",
    ],

];
