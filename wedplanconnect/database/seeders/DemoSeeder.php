<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\Faq;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierPreference;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SAMPLE DATA for demonstrations and the capstone defense only. Not real FMT records.
 * Run with: php artisan db:seed --class=DemoSeeder   (all demo accounts use the password "Password123")
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Password123';

    public function run(): void
    {
        $user = fn (string $role, string $name, string $email, ?string $phone = null) => User::firstOrCreate(
            ['email' => $email],
            ['role' => $role, 'name' => $name, 'phone' => $phone, 'password' => self::PASSWORD],
        );

        $admin = $user('admin', 'Felicity M. Therese', 'admin@fmtweddings.test', '+639171110001');
        $planner = $user('planner', 'Maria Clara Uy', 'planner@fmtweddings.test', '+639171110002');
        $planner2 = $user('planner', 'Joshua Dela Cruz', 'joshua@fmtweddings.test', '+639171110003');

        $clients = collect([
            ['Ana & Miguel Santos', 'client@fmtweddings.test', '+639181234567'],
            ['Bea & Paolo Lim', 'bea.lim@example.com', '+639182345678'],
            ['Carla & Enzo Villanueva', 'carla.v@example.com', '+639183456789'],
            ['Dianne & Rico Tan', 'dianne.tan@example.com', '+639184567890'],
            ['Elaine & Mark Go', 'elaine.go@example.com', '+639185678901'],
            ['Faith & Noel Ramos', 'faith.ramos@example.com', '+639186789012'],
        ])->map(fn ($c) => $user('client', ...$c));

        $this->seedSuppliers();
        $vendor = $user('vendor', 'Bloom & Petal Florals', 'vendor@fmtweddings.test', '+639201112222');
        Supplier::where('name', 'Bloom & Petal Florals')->update(['user_id' => $vendor->id]);

        $this->seedInventory();
        $this->seedFaqs();

        $bookings = [
            // [client idx, planner, days from today, venue, package, amount, status]
            [0, $planner, 41, 'Casa Gorordo Museum, Cebu City', 'Garden Romance', 185000, 'confirmed'],
            [1, $planner, 18, 'Marco Polo Plaza Cebu', 'Grand Ballroom', 260000, 'confirmed'],
            [2, $planner, 75, 'Shangri-La Mactan Resort', 'Beach Wedding', 320000, 'pending'],
            [3, $planner2, 9, 'Sacred Heart Parish, Cebu City', 'Classic Elegance', 120000, 'confirmed'],
            [4, $planner2, 120, 'Radisson Blu Cebu', 'Rustic Charm', 150000, 'pending'],
            [5, $planner, -30, 'Waterfront Cebu City Hotel', 'Classic Elegance', 140000, 'completed'],
        ];

        foreach ($bookings as [$ci, $pl, $days, $venue, $package, $amount, $status]) {
            $client = $clients[$ci];
            $booking = Booking::create([
                'planner_id' => $pl->id, 'client_id' => $client->id, 'client_name' => $client->name,
                'contact_number' => $client->phone, 'event_date' => today()->addDays($days)->toDateString(),
                'venue' => $venue, 'package' => $package, 'total_amount' => $amount, 'status' => $status,
            ]);

            if (in_array($status, ['confirmed', 'completed'])) {
                $booking->ensureQrToken();
            }

            $this->seedBookingDetails($booking, $days, $status);
        }

        // A cancelled booking to demonstrate status history.
        Booking::create([
            'planner_id' => $planner->id, 'client_id' => $clients[4]->id, 'client_name' => 'Elaine & Mark Go',
            'contact_number' => $clients[4]->phone, 'event_date' => today()->addDays(60)->toDateString(),
            'venue' => 'Cebu Parklane International Hotel', 'package' => 'Intimate Ceremony', 'total_amount' => 90000, 'status' => 'cancelled',
        ]);

        // General operations task (no booking).
        Task::create([
            'booking_id' => null, 'assigned_to' => $planner->id, 'title' => 'Quarterly inventory count of fabrics & drapes',
            'due_date' => today()->addDays(5), 'priority' => 'low', 'status' => 'pending',
        ]);

        $this->call(DemoProductsSeeder::class);
    }

    private function seedBookingDetails(Booking $booking, int $days, string $status): void
    {
        $eventDate = $booking->event_date;
        $done = $status === 'completed';

        $taskList = [
            ['Initial consultation & mood board', -70, 'high'],
            ['Finalize decoration layout & floor plan', -45, 'high'],
            ['Confirm florist arrangement & color palette', -30, 'medium'],
            ['Book lights & sounds technical walkthrough', -21, 'medium'],
            ['Prepare centerpieces & table styling', -10, 'medium'],
            ['Venue ocular inspection with couple', -7, 'high'],
            ['Final payment reminder & contract review', -5, 'low'],
            ['Load-in and setup schedule confirmation', -2, 'high'],
        ];

        foreach ($taskList as [$title, $offset, $priority]) {
            $due = $eventDate->copy()->addDays($offset);
            $taskStatus = $done || $due->lt(today()->subDays(3)) ? 'completed' : ($due->lt(today()->addDays(7)) ? 'ongoing' : 'pending');

            // Leave one past-due task open on the nearest wedding to demonstrate overdue flagging.
            if ($days === 9 && $offset === -10) {
                $taskStatus = 'ongoing';
            }

            Task::create([
                'booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => $title,
                'due_date' => $due, 'priority' => $priority, 'status' => $taskStatus,
            ]);
        }

        $picks = [
            'Florist' => 'Bloom & Petal Florals',
            'Photographer' => 'Lumière Studio Cebu',
            'Caterer' => 'Casa Verde Catering',
            'Lights & Sounds' => 'Soundwave Events Cebu',
            'Hair & Makeup' => 'Glam Squad by Rica',
            'Cake' => 'Sugar & Lace Cakes',
        ];

        if ($status === 'pending') {
            // Pending bookings: client preferences only, awaiting planner review.
            foreach (['Florist', 'Photographer', 'Cake'] as $cat) {
                SupplierPreference::create(['booking_id' => $booking->id, 'supplier_id' => Supplier::where('name', $picks[$cat])->value('id')]);
            }
        } else {
            $statuses = $done ? array_fill(0, 6, 'confirmed') : ['confirmed', 'confirmed', 'confirmed', 'contacted', 'pending', 'confirmed'];
            foreach (array_values($picks) as $i => $name) {
                BookingSupplier::create(['booking_id' => $booking->id, 'supplier_id' => Supplier::where('name', $name)->value('id'), 'status' => $statuses[$i]]);
            }
            SupplierPreference::create(['booking_id' => $booking->id, 'supplier_id' => Supplier::where('name', $picks['Florist'])->value('id')]);
        }

        $amount = (float) $booking->total_amount;
        $schedule = match ($status) {
            'completed' => [[0.3, -120, 'bank_transfer'], [0.4, -60, 'gcash'], [0.3, -40, 'cash']],
            'confirmed' => [[0.3, -25, 'gcash'], [0.3, -8, 'bank_transfer']],
            default => [[0.2, -3, 'gcash']],
        };

        foreach ($schedule as $n => [$share, $ago, $method]) {
            Payment::create([
                'booking_id' => $booking->id, 'amount' => round($amount * $share, 2),
                'payment_date' => today()->addDays($ago), 'method' => $method,
                'receipt_number' => 'OR-'.str_pad((string) ($booking->id * 10 + $n), 5, '0', STR_PAD_LEFT),
                'notes' => $n === 0 ? 'Reservation / down payment' : 'Progress payment',
            ]);
        }
    }

    private function seedSuppliers(): void
    {
        $suppliers = [
            ['Bloom & Petal Florals', 'Florist', 'Fresh and preserved floral arrangements, bridal bouquets, arches, and aisle décor.', 35000],
            ['Mactan Blooms', 'Florist', 'Tropical-inspired floral styling for beach and garden weddings.', 28000],
            ['Lumière Studio Cebu', 'Photographer', 'Documentary-style wedding photography with same-day edit prints.', 45000],
            ['Kodak Moments PH', 'Photographer', 'Classic and editorial photo coverage, prenup shoots included.', 38000],
            ['Frame by Frame Films', 'Videographer', 'Cinematic wedding films and same-day edit videos.', 50000],
            ['Casa Verde Catering', 'Caterer', 'Filipino-fusion buffet and plated dinners for 100–500 guests.', 90000],
            ['Lechon Republic Events', 'Caterer', 'Famous Cebu lechon packages and grazing tables.', 60000],
            ['Soundwave Events Cebu', 'Lights & Sounds', 'Full lights & sounds setup, LED walls, and technical crew.', 40000],
            ['Glam Squad by Rica', 'Hair & Makeup', 'Bridal and entourage hair & makeup, trial session included.', 25000],
            ['Sugar & Lace Cakes', 'Cake', 'Multi-tier wedding cakes and dessert tables.', 18000],
            ['The Gown Atelier', 'Gowns & Suits', 'Made-to-order bridal gowns and suit rentals.', 55000],
            ['MC Jon Events', 'Host / Emcee', 'Bilingual wedding host and program coordinator.', 15000],
            ['Acoustic Soul Band', 'Band / Musicians', 'Live acoustic band for ceremony and reception.', 30000],
            ['Casa Gorordo Museum', 'Venue', 'Heritage garden venue in Parian, Cebu City.', 80000],
        ];

        foreach ($suppliers as $i => [$name, $category, $description, $price]) {
            Supplier::create([
                'name' => $name, 'category' => $category, 'description' => $description, 'starting_price' => $price,
                'phone' => '+63920'.str_pad((string) (1000000 + $i * 7919), 7, '0', STR_PAD_LEFT),
                'email' => strtolower(preg_replace('/[^a-z0-9]+/i', '', $name)).'@example.com',
                'availability' => $name === 'Kodak Moments PH' ? 'unavailable' : 'available',
            ]);
        }
    }

    private function seedInventory(): void
    {
        $items = [
            ['White chiavari chairs', 'Furniture', 180, 'pcs', 100, 450],
            ['Gold charger plates', 'Tableware', 220, 'pcs', 150, 120],
            ['Crystal wine glasses', 'Tableware', 90, 'pcs', 120, 95],
            ['Ivory chiffon drapes', 'Fabrics & Drapes', 40, 'panels', 20, 1200],
            ['Blush table runners', 'Fabrics & Drapes', 12, 'pcs', 30, 250],
            ['Warm white fairy lights', 'Lighting', 60, 'strands', 25, 350],
            ['Edison bulb string lights', 'Lighting', 8, 'strands', 10, 900],
            ['Geometric gold centerpiece stands', 'Centerpieces', 35, 'pcs', 20, 800],
            ['Glass cylinder vases', 'Centerpieces', 0, 'pcs', 30, 180],
            ['Circular floral arch frame', 'Arches & Backdrops', 3, 'pcs', 2, 7500],
            ['Rustic wooden backdrop panel', 'Arches & Backdrops', 2, 'sets', 2, 12000],
            ['Pillar candles (ivory)', 'Candles', 150, 'pcs', 80, 65],
            ['Floating candles', 'Candles', 45, 'pcs', 60, 40],
            ['Artificial peonies (blush)', 'Florals', 300, 'stems', 150, 55],
            ['Eucalyptus garland', 'Florals', 18, 'meters', 25, 220],
            ['Welcome sign easel', 'Signage', 6, 'pcs', 2, 1500],
            ['Acrylic table numbers', 'Signage', 40, 'sets', 30, 90],
            ['Cake stand (gold)', 'Miscellaneous', 5, 'pcs', 2, 1800],
        ];

        foreach ($items as [$name, $category, $qty, $unit, $min, $cost]) {
            $item = new InventoryItem(['name' => $name, 'category' => $category, 'quantity' => $qty, 'unit' => $unit, 'min_threshold' => $min, 'unit_cost' => $cost]);
            $item->movementReason = 'Opening stock';
            $item->save();
        }
    }

    private function seedFaqs(): void
    {
        $faqs = [
            ['Booking', 'How do I book FMT Weddings & Events for my wedding?', 'Message or call us to schedule a free consultation. Your Wedding Planner will check date and venue availability in real time, then create your booking. A reservation fee secures your date.', 'reserve, reservation, schedule, inquire, availability'],
            ['Booking', 'Can you do two weddings on the same day?', 'We can handle multiple events on one date only at different venues. Our system automatically blocks a second booking for the same date and venue to prevent double-booking.', 'same day, double booking, date taken'],
            ['Booking', 'How far in advance should we book?', 'We recommend booking 6 to 12 months ahead, especially for December, February, and June weddings, which fill up quickly.', 'advance, early, months before, peak season'],
            ['Booking', 'Can we change our wedding date?', 'Yes, subject to availability. Your planner will check the new date and venue for conflicts before updating your booking. Date changes within 60 days of the event may incur rebooking fees.', 'reschedule, move date, postpone'],
            ['Payments', 'How much is the reservation fee or down payment?', 'A 30% down payment reserves your date. The balance is paid in installments, with full payment due 7 days before the wedding.', 'downpayment, deposit, reservation fee, installment'],
            ['Payments', 'What payment methods do you accept?', 'We accept cash, GCash, and bank transfer. Online card payments are not available. Please send your proof of payment to your planner so it can be recorded.', 'gcash, bank, transfer, cash, pay, card'],
            ['Payments', 'How can I check my remaining balance?', 'Your client dashboard shows how much you have paid and the percentage of your contract settled. Your planner can also send you a PDF contract and payment summary.', 'balance, remaining, how much left, statement'],
            ['Payments', 'Is the reservation fee refundable?', 'The reservation fee is non-refundable but can be applied to a new date within 12 months if you reschedule, subject to availability.', 'refund, cancel, cancellation'],
            ['Packages', 'What decoration packages do you offer?', 'Our packages include Classic Elegance, Garden Romance, Rustic Charm, Grand Ballroom, Beach Wedding, and Intimate Ceremony. Each can be customized. Ask your planner for the current inclusions and rates.', 'package, inclusions, styles, theme, rates, price'],
            ['Packages', 'Can we customize our decoration theme and colors?', 'Absolutely. During the mood board consultation we tailor colors, florals, and styling to your vision within your package and budget.', 'customize, color, motif, theme, mood board'],
            ['Packages', 'Do you provide chairs, tables, and tableware?', 'Yes. We have chiavari chairs, charger plates, glassware, linens, and centerpieces in our own inventory, subject to availability for your date.', 'chairs, tables, plates, glass, linens, rentals'],
            ['Suppliers', 'Can I choose my own suppliers?', 'Yes! Browse the Supplier Catalog in your portal and add your favorites to your preferences. Your planner reviews them and handles all coordination and confirmation for you.', 'choose, pick, vendor, preference, catalog'],
            ['Suppliers', 'Can I contact the suppliers directly?', 'To keep coordination accountable, all supplier communication goes through your Wedding Planner. Just tell your planner what you need and they will coordinate it.', 'contact supplier, vendor number, phone, message supplier'],
            ['Suppliers', 'What do the supplier statuses mean?', 'Pending: not yet contacted. Contacted: your planner has reached out. Confirmed: the supplier is booked for your date. Unavailable: the supplier cannot serve your date, so your planner will suggest alternatives.', 'status, pending, contacted, confirmed, unavailable'],
            ['Status Portal', 'How do I use the QR code on my contract?', 'Scan it with your phone camera. We will email a 6-digit One-Time PIN to your registered email. Enter it to view your live wedding preparation status.', 'qr, scan, code, status page, track'],
            ['Status Portal', "I didn't receive my One-Time PIN. What should I do?", 'Check your spam folder, then tap "Send a new PIN" on the verification screen. PINs expire after 10 minutes and can be used only once. If it still does not arrive, contact your planner to confirm your email address.', 'otp, pin, code, email, not received'],
            ['Status Portal', 'Why do I need a PIN if I already have the QR code?', 'For your privacy. Anyone who photographs your printed contract could otherwise see your wedding details. The PIN ensures only you can open the status page, in line with the Data Privacy Act (RA 10173).', 'why pin, privacy, secure, safe'],
            ['Wedding Day', 'What time does your team set up on the wedding day?', 'Our team usually arrives 5 to 6 hours before the ceremony for full setup. Your planner confirms the exact load-in schedule with the venue a few days before.', 'setup, ingress, load in, arrive, time'],
            ['Wedding Day', 'Who handles teardown after the reception?', 'Our crew handles teardown and pull-out of all FMT decorations and rentals after the reception, following the venue’s egress rules.', 'teardown, pull out, egress, after party'],
            ['Account', 'How do I change my password?', 'Log in, open My Account from the menu, and use the Change Password form. If you forgot your password, use the "Forgot password?" link on the login page.', 'password, reset, forgot, login'],
        ];

        foreach ($faqs as [$category, $question, $answer, $keywords]) {
            Faq::create(compact('category', 'question', 'answer', 'keywords'));
        }
    }
}
