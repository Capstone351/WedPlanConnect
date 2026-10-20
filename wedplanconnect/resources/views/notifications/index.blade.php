<x-layouts.app title="Notifications" heading="Notifications" subheading="Orders and replies about the weddings you work on.">
    @if (auth()->user()->unreadNotifications()->exists())
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn-secondary">Mark all as read</button>
            </form>
        </x-slot:actions>
    @endif

    <div class="card">
        <ul class="divide-y divide-line">
            @forelse ($notifications as $n)
                <li>
                    <a href="{{ route('notifications.open', $n->id) }}" @class(['flex items-start gap-3 px-5 py-4 hover:bg-brand-50/40', 'bg-gold-50/50' => ! $n->read_at])>
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-600' => ! $n->read_at, 'bg-transparent' => $n->read_at])></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block', 'font-semibold' => ! $n->read_at, 'font-medium' => $n->read_at])>{{ $n->data['title'] ?? 'Notification' }}</span>
                            <span class="block text-sm text-stone-600">{{ $n->data['body'] ?? '' }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-stone-500">{{ $n->created_at->diffForHumans() }}</span>
                    </a>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-stone-500">No notifications yet.</li>
            @endforelse
        </ul>
        @if ($notifications->hasPages())
            <div class="p-4">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
