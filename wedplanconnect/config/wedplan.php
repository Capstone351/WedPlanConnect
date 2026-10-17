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
    | Double-booking detection scope.
    | 'date_venue' — conflict when an active booking shares the same event date AND venue (manuscript definition).
    | 'date'       — conflict when any active booking exists on the same event date.
    */
    'conflict_scope' => env('WEDPLAN_CONFLICT_SCOPE', 'date_venue'),

    /*
    | QR + OTP status portal.
    */
    'otp_ttl_minutes' => 10,
    'otp_max_attempts' => 5,
    'qr_session_minutes' => 30,

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
