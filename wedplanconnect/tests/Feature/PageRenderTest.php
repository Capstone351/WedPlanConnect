<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: every screen renders for its role against the demo data set.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::where('role', 'admin')->first();
        $booking = Booking::where('status', 'confirmed')->first();

        foreach ([
            '/admin', '/admin/users', '/admin/users/create', "/admin/users/{$admin->id}/edit", '/admin/faqs', '/admin/faqs/create', '/admin/faqs/logs',
            '/bookings', '/bookings/create', "/bookings/{$booking->id}", "/bookings/{$booking->id}/edit", "/bookings/{$booking->id}/qr",
            '/suppliers', '/suppliers/create', '/tasks', '/tasks/create', '/inventory', '/inventory/create',
            '/payments', "/payments?booking_id={$booking->id}", '/reports', '/account',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_planner_pages_render(): void
    {
        $planner = User::where('email', 'planner@fmtweddings.test')->first();

        foreach (['/planner', '/bookings', '/tasks', '/inventory', '/payments', '/reports', '/reports/daily-operations'] as $url) {
            $this->actingAs($planner)->get($url)->assertOk();
        }
    }

    public function test_client_pages_render(): void
    {
        $client = User::where('email', 'client@fmtweddings.test')->first();
        $booking = $client->clientBookings()->first();

        foreach (['/my', '/my/catalog', "/my/status/{$booking->id}"] as $url) {
            $this->actingAs($client)->get($url)->assertOk();
        }
    }

    public function test_vendor_pages_render_and_hide_client_contacts(): void
    {
        $vendor = User::where('role', 'vendor')->first();
        $booking = $vendor->supplierProfile->bookings()->first();

        $this->actingAs($vendor)->get('/vendor')->assertOk();
        $this->actingAs($vendor)->get('/vendor/bookings')->assertOk();
        $this->actingAs($vendor)->get('/vendor/profile')->assertOk();
        $this->actingAs($vendor)->get("/vendor/bookings/{$booking->id}")->assertOk()->assertDontSee($booking->contact_number);
    }

    public function test_client_catalog_hides_supplier_contacts(): void
    {
        $client = User::where('email', 'client@fmtweddings.test')->first();
        $supplier = \App\Models\Supplier::first();

        $this->actingAs($client)->get('/my/catalog')->assertSee($supplier->name)->assertDontSee($supplier->phone);
    }
}
