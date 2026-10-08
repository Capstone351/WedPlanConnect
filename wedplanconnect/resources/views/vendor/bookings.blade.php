<x-layouts.app title="Assigned Bookings" heading="Assigned Bookings" subheading="Bookings the Wedding Planner has assigned to your business.">
    <div class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Event date</th><th>Couple</th><th>Venue</th><th>Package</th><th>Booking</th><th>Coordination</th><th></th></tr></thead>
                <tbody>
                    @forelse ($assignments as $a)
                        <tr>
                            <td class="whitespace-nowrap">{{ $a->booking->event_date->format('M j, Y') }}</td>
                            <td class="font-medium">{{ $a->booking->client_name }}</td>
                            <td>{{ $a->booking->venue }}</td>
                            <td>{{ $a->booking->package }}</td>
                            <td><span class="badge-{{ $a->booking->statusBadge() }}">{{ ucfirst($a->booking->status) }}</span></td>
                            <td><span class="badge-{{ \App\Models\BookingSupplier::badge($a->status) }}">{{ ucfirst($a->status) }}</span></td>
                            <td class="text-right"><a href="{{ route('vendor.bookings.show', $a->booking_id) }}" class="btn-secondary btn-sm">Details</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-stone-500">You have not been assigned to any bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
