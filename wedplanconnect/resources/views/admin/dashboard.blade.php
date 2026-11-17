@php
    $peso = fn ($n) => '₱'.number_format($n, 2);
    $pesoShort = fn ($n) => $n >= 1000000 ? '₱'.rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.').'M' : ($n >= 1000 ? '₱'.number_format($n / 1000).'k' : '₱'.number_format($n));
    $maxTrend = max(1, $revenueTrend->max('value'));
    $statusTotal = $statusCounts->sum();
    $statusColors = ['pending' => 'bg-amber-400', 'confirmed' => 'bg-emerald-500', 'completed' => 'bg-sky-500', 'cancelled' => 'bg-stone-300'];
    $supplierTotal = $supplierStatuses->sum();
    $actionCount = $voidRequests->count() + $lockedUsers->count() + $unlinkedVendors->count() + $plannersToRemind->count() + $unansweredQuestions->count();
@endphp
<x-layouts.app title="Admin Dashboard" :heading="'Good day, '.strtok(auth()->user()->name, ' ').'!'" :subheading="'Business overview of '.config('wedplan.business_name').' · '.now()->format('l, F j, Y')">
    <x-slot:actions>
        <a href="{{ route('admin.faqs.index') }}" class="btn-secondary"><x-icon name="chat" class="size-4" /> Chatbot FAQ</a>
        <a href="{{ route('admin.users.create', ['role' => 'vendor']) }}" class="btn-secondary"><x-icon name="store" class="size-4" /> New supplier account</a>
        <a href="{{ route('admin.users.create', ['role' => 'planner']) }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New planner</a>
    </x-slot:actions>

    {{-- Action centre: what only the Admin can resolve, each with its fix one click away. --}}
    <div class="card mb-6">
        <div class="card-header">
            <h2 class="card-title">Needs your attention</h2>
            @if ($actionCount)
                <span class="badge-amber">{{ $actionCount }} to do</span>
            @else
                <span class="badge-green">All clear</span>
            @endif
        </div>
        @if ($actionCount)
            <ul class="divide-y divide-line">
                @foreach ($voidRequests as $p)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 text-sm">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-red-50 text-red-600 ring-1 ring-red-200"><x-icon name="cash" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $p->voidRequester?->name ?? 'A planner' }} asks to void ₱{{ number_format($p->amount, 2) }} for {{ $p->booking->client_name }}</div>
                            <div class="text-xs text-stone-500">{{ $p->methodLabel() }} · paid {{ $p->payment_date->format('M j, Y') }}{{ $p->receipt_number ? ' · OR '.$p->receipt_number : '' }} · asked {{ $p->void_requested_at->diffForHumans() }}</div>
                            <div class="mt-1 text-xs text-stone-700">Reason: “{{ $p->void_request_reason }}”</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.payments.void', $p) }}" data-confirm="Void this ₱{{ number_format($p->amount, 2) }} payment? It stays on record, crossed out, and the couple is told." data-confirm-ok="Void it">
                                @csrf @method('PATCH')
                                <button class="btn-danger btn-sm">Void it</button>
                            </form>
                            <details class="relative">
                                <summary class="btn-secondary btn-sm cursor-pointer list-none">Keep it</summary>
                                <form method="POST" action="{{ route('admin.payments.keep', $p) }}" class="card absolute right-0 z-20 mt-2 w-72 max-w-[calc(100vw-3rem)] space-y-2 p-4">
                                    @csrf @method('PATCH')
                                    <label for="keep_note_{{ $p->id }}" class="label">Note to the planner <span class="font-normal text-stone-400">(optional)</span></label>
                                    <textarea id="keep_note_{{ $p->id }}" name="note" rows="2" maxlength="500" class="input" placeholder="e.g. The receipt shows it was paid."></textarea>
                                    <button class="btn-primary btn-sm w-full">Keep the payment</button>
                                </form>
                            </details>
                        </div>
                    </li>
                @endforeach
                @foreach ($lockedUsers as $u)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 text-sm">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-red-50 text-red-600 ring-1 ring-red-200"><x-icon name="lock" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $u->name }}'s account is locked</div>
                            <div class="text-xs text-stone-500">{{ \App\Models\User::ROLES[$u->role] ?? ucfirst($u->role) }} · too many failed sign-ins · unlocks by itself {{ $u->locked_until->diffForHumans() }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.users.unlock', $u) }}">@csrf @method('PATCH')<button class="btn-secondary btn-sm">Unlock now</button></form>
                    </li>
                @endforeach
                @foreach ($plannersToRemind as $p)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 text-sm">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-amber-50 text-amber-600 ring-1 ring-amber-200"><x-icon name="clipboard" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $p->name }} is falling behind</div>
                            <div class="text-xs text-stone-500">
                                {{ collect([$p->overdue_tasks_count ? $p->overdue_tasks_count.' overdue '.str('task')->plural($p->overdue_tasks_count) : null, $p->unclosed_count ? $p->unclosed_count.' past '.str('wedding')->plural($p->unclosed_count).' not marked completed or cancelled' : null])->filter()->join(' · ') }}
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.planners.remind', $p) }}">@csrf<button class="btn-secondary btn-sm"><x-icon name="bell" class="size-4" /> Send reminder</button></form>
                    </li>
                @endforeach
                @foreach ($unlinkedVendors as $v)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 text-sm">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-50 text-sky-600 ring-1 ring-sky-200"><x-icon name="store" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ $v->name }} can't manage products yet</div>
                            <div class="text-xs text-stone-500">Vendor account not linked to a supplier profile</div>
                        </div>
                        <a href="{{ route('admin.users.edit', $v) }}" class="btn-secondary btn-sm">Link supplier</a>
                    </li>
                @endforeach
                @foreach ($unansweredQuestions as $q)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-3 text-sm">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600 ring-1 ring-brand-200"><x-icon name="chat" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">WedBot couldn't answer “{{ \Illuminate\Support\Str::limit($q->question, 90) }}”</div>
                            <div class="text-xs text-stone-500">Asked {{ $q->created_at->diffForHumans() }}</div>
                        </div>
                        <a href="{{ route('admin.faqs.create', ['question' => $q->question]) }}" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Add as FAQ</a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex items-center gap-3 px-5 py-5 text-sm text-stone-600">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200"><x-icon name="check" class="size-4" /></span>
                Nothing needs you right now: no payments to void, no locked accounts, every vendor is linked, planners are on track, and WedBot answered every recent question.
            </div>
        @endif
    </div>

    {{-- Headline: money in, money owed, and how much of it has been collected. --}}
    <div class="hero-panel">
        <div class="relative grid gap-8 p-7 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <div class="eyebrow text-gold-300">Payments collected</div>
                <div class="mt-2 font-display text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl">{{ $peso($collected) }}</div>
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-white/5 px-4 py-3 ring-1 ring-gold-300/20">
                        <div class="text-[11px] tracking-[0.16em] text-gold-300/90 uppercase">This month</div>
                        <div class="mt-1 font-display text-xl font-semibold tabular-nums">{{ $peso($collectedThisMonth) }}</div>
                    </div>
                    <div class="rounded-xl bg-white/5 px-4 py-3 ring-1 ring-gold-300/20">
                        <div class="text-[11px] tracking-[0.16em] text-gold-300/90 uppercase">Still to collect</div>
                        <div class="mt-1 font-display text-xl font-semibold tabular-nums">{{ $peso($outstanding) }}</div>
                    </div>
                    <div class="rounded-xl bg-white/5 px-4 py-3 ring-1 ring-gold-300/20">
                        <div class="text-[11px] tracking-[0.16em] text-gold-300/90 uppercase">Upcoming weddings</div>
                        <div class="mt-1 font-display text-xl font-semibold tabular-nums">{{ $upcomingCounts->sum() }}
                            <span class="text-sm font-normal text-brand-100/80">· {{ $upcomingCounts['confirmed'] ?? 0 }} confirmed</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-center">
                <div class="relative inline-grid size-32 place-items-center">
                    <svg viewBox="0 0 120 120" class="absolute inset-0 -rotate-90" aria-hidden="true">
                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="9" class="stroke-white/15" />
                        <circle cx="60" cy="60" r="52" fill="none" stroke-width="9" stroke-linecap="round" class="stroke-gold-300"
                            stroke-dasharray="{{ 2 * pi() * 52 }}" stroke-dashoffset="{{ 2 * pi() * 52 * (1 - $collectionRate / 100) }}" />
                    </svg>
                    <div class="text-center">
                        <div class="font-display text-3xl font-semibold tabular-nums">{{ $collectionRate }}%</div>
                        <div class="text-[10px] tracking-[0.18em] text-gold-300 uppercase">collected</div>
                    </div>
                </div>
                <p class="mt-2 max-w-40 text-center text-xs text-brand-100/80">of all active contract amounts</p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Wedding planners" :value="$userCounts['planner'] ?? 0" :hint="$planners->where('upcoming_count', '>', 0)->count().' with upcoming weddings'" icon="user" />
        <x-stat label="Couple-clients" :value="$userCounts['client'] ?? 0" :hint="$newUsersThisMonth.' new account(s) this month'" icon="heart" tone="gold" />
        <x-stat label="Open tasks" :value="$openTasks" :hint="$overdueCount.' overdue'" icon="clipboard" :tone="$overdueCount ? 'red' : 'blue'" />
        <x-stat label="Low-stock items" :value="$lowStock->count()" hint="FMT inventory at or below minimum" icon="cube" :tone="$lowStock->isNotEmpty() ? 'amber' : 'green'" />
    </div>

    @if ($setup)
        <div class="card mt-6 border-gold-300">
            <div class="card-header"><h2 class="card-title">Finish setting up WedPlanConnect</h2></div>
            <ul class="divide-y divide-line">
                @foreach ($setup as [$label, , $url])
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span>{{ $label }}</span>
                        <a href="{{ $url }}" class="btn-secondary btn-sm">Start</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Revenue trend: one series, so no legend; each bar names its month and amount. --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Revenue trend</h2>
                <span class="text-xs text-stone-500">Payments received, last 6 months</span>
            </div>
            <div class="card-body">
                <div class="flex h-52 items-end gap-3 border-b border-line sm:gap-5">
                    @foreach ($revenueTrend as $m)
                        <div class="group relative flex h-full flex-1 flex-col items-center justify-end">
                            <span class="mb-1.5 text-[11px] font-medium text-stone-500 tabular-nums">{{ $m['value'] > 0 ? $pesoShort($m['value']) : '' }}</span>
                            <div class="w-full max-w-14 rounded-t-[4px] bg-brand-500 transition group-hover:bg-brand-700 {{ $loop->last ? '' : 'opacity-80' }}"
                                style="height: {{ max($m['value'] > 0 ? 3 : 0, $m['value'] / $maxTrend * 85) }}%"
                                title="{{ $m['label'] }}: {{ $peso($m['value']) }}"></div>
                            <div class="pointer-events-none absolute -top-2 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full rounded-lg bg-ink px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-lg group-hover:block">
                                {{ $m['label'] }} · {{ $peso($m['value']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-3 sm:gap-5">
                    @foreach ($revenueTrend as $m)
                        <div @class(['flex-1 text-center text-xs', 'font-semibold text-ink' => $loop->last, 'text-stone-500' => ! $loop->last])>{{ $m['label'] }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Booking pipeline: every status is labelled with its count, so colour is never the only cue. --}}
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Booking pipeline</h2>
                <span class="text-xs text-stone-500">{{ $statusTotal }} total</span>
            </div>
            <div class="card-body">
                <div class="flex h-3 gap-0.5 overflow-hidden rounded-full bg-stone-100">
                    @foreach (\App\Models\Booking::STATUSES as $s)
                        @if ($statusCounts[$s] ?? 0)
                            <div class="{{ $statusColors[$s] }}" style="width: {{ $statusCounts[$s] / $statusTotal * 100 }}%" title="{{ ucfirst($s) }}: {{ $statusCounts[$s] }}"></div>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-5 space-y-3">
                    @foreach (\App\Models\Booking::STATUSES as $s)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="size-2.5 rounded-full {{ $statusColors[$s] }}"></span>
                            <span class="flex-1">{{ ucfirst($s) }}</span>
                            <span class="font-semibold tabular-nums">{{ $statusCounts[$s] ?? 0 }}</span>
                            <span class="w-10 text-right text-xs text-stone-500 tabular-nums">{{ $statusTotal ? round(($statusCounts[$s] ?? 0) / $statusTotal * 100) : 0 }}%</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-5 border-t border-line pt-4">
                    <div class="text-[11px] font-semibold tracking-[0.14em] text-gold-700 uppercase">Suppliers on upcoming weddings</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (\App\Models\BookingSupplier::STATUSES as $s)
                            <span class="badge-{{ \App\Models\BookingSupplier::badge($s) }}">{{ ucfirst($s) }} · {{ $supplierStatuses[$s] ?? 0 }}</span>
                        @endforeach
                    </div>
                    @if (! $supplierTotal)
                        <p class="mt-2 text-xs text-stone-500">No suppliers assigned yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Planner workload --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Planner workload</h2>
                <a href="{{ route('admin.users.index') }}" class="text-sm link">Manage accounts</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Planner</th><th class="text-right">Upcoming weddings</th><th class="text-right">Open tasks</th><th class="text-right">Overdue</th></tr></thead>
                    <tbody>
                        @forelse ($planners as $p)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 ring-1 ring-gold-300/60">
                                            {{ collect(explode(' ', $p->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}
                                        </span>
                                        <div>
                                            <div class="font-medium">{{ $p->name }}</div>
                                            <div class="text-xs text-stone-500">{{ $p->last_login_at ? 'Last signed in '.$p->last_login_at->diffForHumans() : 'Never signed in' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right font-semibold tabular-nums">{{ $p->upcoming_count }}</td>
                                <td class="text-right tabular-nums">{{ $p->open_tasks_count }}</td>
                                <td class="text-right">
                                    @if ($p->overdue_tasks_count)
                                        <span class="badge-red">{{ $p->overdue_tasks_count }} overdue</span>
                                    @else
                                        <span class="badge-green">On track</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-stone-500">No Wedding Planner accounts yet. <a href="{{ route('admin.users.create', ['role' => 'planner']) }}" class="link">Add one</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- User accounts --}}
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">User accounts</h2>
                <a href="{{ route('admin.users.index') }}" class="text-sm link">Manage</a>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-3">
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <div class="rounded-xl bg-stone-50 p-3 ring-1 ring-line">
                            <div class="font-display text-2xl font-semibold tabular-nums">{{ $userCounts[$key] ?? 0 }}</div>
                            <div class="text-xs text-stone-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <span class="badge-gray">{{ $deactivatedCount }} deactivated</span>
                    <span class="{{ $lockedUsers->isNotEmpty() ? 'badge-red' : 'badge-gray' }}">{{ $lockedUsers->count() }} locked</span>
                </div>
                <div class="mt-5 border-t border-line pt-4">
                    <div class="text-[11px] font-semibold tracking-[0.14em] text-gold-700 uppercase">Newest accounts</div>
                    <ul class="mt-2 divide-y divide-line">
                        @foreach ($recentUsers as $u)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <div class="min-w-0">
                                    <a href="{{ route('admin.users.edit', $u) }}" class="block truncate font-medium hover:text-brand-700">{{ $u->name }}</a>
                                    <div class="text-xs text-stone-500">{{ $u->created_at->diffForHumans() }}</div>
                                </div>
                                <span class="badge-brand shrink-0">{{ \App\Models\User::ROLES[$u->role] ?? ucfirst($u->role) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        {{-- Upcoming weddings (read-only: planners manage the bookings) --}}
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Upcoming weddings</h2>
                <span class="text-xs text-stone-500">Next {{ $upcoming->count() }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Couple</th><th>Venue</th><th>Planner</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($upcoming as $b)
                            <tr>
                                <td class="whitespace-nowrap">{{ $b->event_date->format('M j, Y') }}<div class="text-xs text-stone-500">{{ $b->daysToGo() }} days to go</div></td>
                                <td class="font-medium">{{ $b->client_name }}</td>
                                <td>{{ $b->venueLabel() }}</td>
                                <td>{{ $b->planner->name }}</td>
                                <td><span class="badge-{{ $b->statusBadge() }}">{{ ucfirst($b->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-stone-500">No upcoming weddings.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- WedBot activity --}}
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">WedBot this month</h2>
                <a href="{{ route('admin.faqs.logs') }}" class="text-sm link">View logs</a>
            </div>
            <div class="card-body">
                <div class="font-display text-3xl font-semibold tabular-nums">{{ $chatbot['questions'] }}</div>
                <div class="text-xs text-stone-500">questions asked</div>
                @php
                    $unanswered = max(0, $chatbot['questions'] - $chatbot['faq'] - $chatbot['ai']);
                    $rows = [['Answered from FAQs', $chatbot['faq'], 'bg-brand-500'], ['Answered by Gemini AI', $chatbot['ai'], 'bg-gold-400'], ['Not answered', $unanswered, 'bg-stone-300']];
                @endphp
                <ul class="mt-4 space-y-3">
                    @foreach ($rows as [$label, $count, $color])
                        <li class="text-sm">
                            <div class="flex justify-between"><span>{{ $label }}</span><span class="font-semibold tabular-nums">{{ $count }}</span></div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-stone-100">
                                <div class="h-full rounded-full {{ $color }}" style="width: {{ $chatbot['questions'] ? $count / $chatbot['questions'] * 100 : 0 }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-stone-500">{{ $chatbot['faqCount'] }} FAQ answer(s) in the knowledge base. <a href="{{ route('admin.faqs.index') }}" class="link">Manage FAQs</a></p>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Overdue tasks</h2>
                <span class="{{ $overdueCount ? 'badge-red' : 'badge-green' }}">{{ $overdueCount }} overdue</span>
            </div>
            <ul class="divide-y divide-line">
                @forelse ($overdueTasks as $t)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $t->title }}</div>
                            <div class="text-xs text-stone-500">{{ $t->booking?->client_name ?? 'General' }} · {{ $t->assignee?->name }}</div>
                        </div>
                        <span class="badge-red shrink-0">Due {{ $t->due_date->format('M j') }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-stone-500">No overdue tasks. 🎉</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Inventory alerts</h2>
                <span class="{{ $lowStock->isNotEmpty() ? 'badge-amber' : 'badge-green' }}">{{ $lowStock->count() }} low</span>
            </div>
            <ul class="divide-y divide-line">
                @forelse ($lowStock->take(5) as $i)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $i->name }}</div>
                            <div class="text-xs text-stone-500">{{ $i->category }} · min {{ $i->min_threshold }} {{ $i->unit }}</div>
                        </div>
                        <span class="badge-{{ $i->stockBadge() }} shrink-0">{{ $i->quantity }} {{ $i->unit }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-stone-500">All stock levels are healthy.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
