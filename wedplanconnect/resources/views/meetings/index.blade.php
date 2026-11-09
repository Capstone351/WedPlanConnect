<x-layouts.app title="Meetings" heading="Meetings" subheading="Meetings couples asked to have with you. Confirm or decline each request.">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Upcoming</h2>
            @if ($n = $upcoming->where('status', 'requested')->count())
                <span class="badge-gold">{{ $n }} waiting for your reply</span>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>When</th><th>Couple</th>@if (auth()->user()->role === 'admin')<th>Planner</th>@endif<th>Meeting</th><th>Status</th><th class="text-right">Reply</th></tr></thead>
                <tbody>
                    @forelse ($upcoming as $m)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $m->when() }}</td>
                            <td>
                                <div class="font-medium">{{ $m->client->coupleNames() ?? $m->client->name }}</div>
                                <div class="text-xs text-stone-500">{{ $m->client->email }}@if ($m->client->phone) · {{ $m->client->phone }}@endif</div>
                            </td>
                            @if (auth()->user()->role === 'admin')<td>{{ $m->planner->name }}</td>@endif
                            <td>
                                <div>{{ $m->modeLabel() }}@if ($m->location) · {{ $m->location }}@endif</div>
                                @if ($m->agenda)<div class="max-w-xs text-xs text-stone-500">"{{ $m->agenda }}"</div>@endif
                            </td>
                            <td><span class="badge-{{ $m->statusBadge() }}">{{ ucfirst($m->status) }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('meetings.respond', $m) }}" class="flex flex-wrap items-center justify-end gap-1"
                                    @if ($m->status === 'confirmed') data-confirm="Cancel this confirmed meeting? The couple will be notified." @endif>
                                    @csrf @method('PATCH')
                                    <input name="response_note" maxlength="255" class="input w-44 py-1 text-xs" placeholder="Note (optional)" aria-label="Note to the couple">
                                    @if ($m->status === 'requested')
                                        <button name="status" value="confirmed" class="btn-success btn-sm">Confirm</button>
                                        <button name="status" value="declined" class="btn-secondary btn-sm">Decline</button>
                                    @else
                                        <button name="status" value="declined" class="btn-secondary btn-sm">Cancel</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-stone-500">No upcoming meetings.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($past->isNotEmpty())
        <div class="card mt-6">
            <div class="card-header"><h2 class="card-title">Past, declined and cancelled</h2></div>
            <ul class="divide-y divide-line">
                @foreach ($past as $m)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 text-sm">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $m->when() }} · {{ $m->client->coupleNames() ?? $m->client->name }}</div>
                            <div class="text-xs text-stone-500">{{ $m->modeLabel() }}@if ($m->response_note) · "{{ $m->response_note }}"@endif</div>
                        </div>
                        <span class="badge-{{ $m->statusBadge() }}">{{ $m->status === 'requested' ? 'No reply' : ucfirst($m->status) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-layouts.app>
