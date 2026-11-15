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
    | Inclusion (as printed on the package) => the preparation task it becomes on a confirmed booking.
    |   days     — due this many days before the wedding
    |   priority — how much it matters to the wedding, not how early it is due
    |   task     — the to-do, worded as an action
    |   details  — what "done" means for the planner
    */
    'package_inclusions' => [
        'Hotel for Dress-up' => ['days' => 120, 'priority' => 'high', 'task' => 'Reserve the dress-up hotel room',
            'details' => "Book a room near the ceremony venue for the couple's hair, make-up and dressing. Confirm the room is ready early on the wedding morning."],
        'Reception Area' => ['days' => 180, 'priority' => 'high', 'task' => 'Book the reception venue',
            'details' => "Reserve the reception hall for the date and guest count, pay the venue's deposit, and get the confirmed time slot in writing."],
        'Venue Decoration' => ['days' => 60, 'priority' => 'high', 'task' => 'Finalize the reception decoration design',
            'details' => 'Agree on the motif, colours, centerpieces and stage backdrop with the couple, then list the decoration items and the set-up time.'],
        'Church Decoration' => ['days' => 60, 'priority' => 'high', 'task' => 'Plan the church decoration',
            'details' => "Check the church's decoration rules, agree on the aisle, altar and pew flowers with the couple, and schedule the set-up."],
        'Bridal Car' => ['days' => 60, 'priority' => 'medium', 'task' => 'Book the bridal car',
            'details' => "Reserve the car and driver, confirm the pick-up time and route from the dress-up hotel to the church and reception, and arrange the car's flowers."],
        'Cake' => ['days' => 45, 'priority' => 'medium', 'task' => 'Order the wedding cake',
            'details' => "Confirm the package's number of layers, the flavour and design with the couple, and the delivery time at the reception."],
        'Bridal Dress' => ['days' => 120, 'priority' => 'high', 'task' => 'Arrange the bridal gown and fittings',
            'details' => 'Choose the gown with the bride, schedule the fittings, and set the final fitting about two weeks before the wedding.'],
        'Bridal Entourage' => ['days' => 90, 'priority' => 'medium', 'task' => 'Arrange the entourage attire',
            'details' => 'Collect the entourage list and sizes, agree on the colours with the couple, and schedule fittings and pick-up.'],
        'Confetti/Poppers' => ['days' => 14, 'priority' => 'low', 'task' => 'Prepare the confetti and poppers',
            'details' => 'Confirm the colours, quantity and moment they are used (grand entrance or exit), and hand them to the coordinators.'],
        'Photo/Video (w/ Pre-nup)' => ['days' => 150, 'priority' => 'high', 'task' => 'Book the photo and video team',
            'details' => "Reserve the photographer and videographer for the date, schedule the pre-nup shoot, and share the couple's shot list."],
        'Decor w/ Calling' => ['days' => 60, 'priority' => 'medium', 'task' => 'Arrange the decor with calling',
            'details' => 'Confirm what this inclusion covers, agree on the design with the couple, and schedule the set-up.'],
        'Make-up (10 persons)' => ['days' => 60, 'priority' => 'medium', 'task' => 'Book the make-up artists',
            'details' => 'Reserve make-up for up to 10 people, list who needs it, and set the schedule at the dress-up hotel.'],
        'Emcee w/ DJ' => ['days' => 90, 'priority' => 'medium', 'task' => 'Book the emcee and DJ',
            'details' => "Reserve the emcee and DJ, share the reception program, and collect the couple's song list."],
        'Invitation (30 pcs.)' => ['days' => 75, 'priority' => 'medium', 'task' => 'Order the 30 invitations',
            'details' => 'Finalize the wording and design with the couple, get their approval on the proof, and set the printing and delivery date.'],
        'Give-away (30 pcs.)' => ['days' => 30, 'priority' => 'low', 'task' => 'Order the 30 give-aways',
            'details' => 'Agree on the give-away item with the couple, order 30 pieces, and have them packed for the reception.'],
    ],
    /* On-the-day coordinators; ":count" is the package's number of coordinators. */
    'package_coordinators_task' => ['days' => 30, 'priority' => 'high', 'task' => 'Brief the :count on-the-day coordinators',
        'details' => 'Give each coordinator a role (couple, entourage, ceremony, reception), share the day-of timeline, and confirm their call times.'],
    /* Freebie (as printed on the package) => its task. Freebies without an entry get a generic "Prepare" task. */
    'package_freebies_days' => 14,
    'package_freebies' => [
        'Wine for Toasting' => ['task' => 'Prepare the wine for the toast',
            'details' => 'Free with the package. Have the wine and glasses ready at the reception before the toast.'],
        'Bridesmaids Robe for Dress-up/Makeup' => ['task' => "Prepare the bridesmaids' robes",
            'details' => "Free with the package. Collect the bridesmaids' sizes and have the robes ready at the dress-up hotel."],
    ],

    /*
    | Double-booking detection scope.
    | 'date_venue' — conflict when an active booking shares the same event date AND venue (manuscript definition).
    | 'date'       — conflict when any active booking exists on the same event date.
    */
    'conflict_scope' => env('WEDPLAN_CONFLICT_SCOPE', 'date_venue'),

    /*
    | Cancellation policy: the share of the down payment returned when the couple asks to cancel.
    */
    'cancellation_refund_percent' => 10,
    'cancellation_policy' => 'If the couple asks to cancel the booking, only :percent% of the down payment will be returned.',

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
