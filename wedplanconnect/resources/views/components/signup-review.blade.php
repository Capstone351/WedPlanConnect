@props(['user'])
{{-- Approve / Reject buttons for a Wedding Planner or Supplier who signed up themselves. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <form method="POST" action="{{ route('admin.users.approve', $user) }}">
        @csrf @method('PATCH')
        <button class="btn-success btn-sm"><x-icon name="check" class="size-4" /> Approve</button>
    </form>
    <details class="relative">
        <summary class="btn-secondary btn-sm cursor-pointer list-none">Reject</summary>
        <form method="POST" action="{{ route('admin.users.reject', $user) }}" class="card absolute right-0 z-20 mt-2 w-72 max-w-[calc(100vw-3rem)] space-y-2 p-4"
            data-confirm="Reject {{ $user->name }}'s sign-up? Their account is removed and they are told by email." data-confirm-ok="Reject">
            @csrf @method('DELETE')
            <label for="reject_reason_{{ $user->id }}" class="label">Reason <span class="font-normal text-stone-400">(optional, sent to them)</span></label>
            <textarea id="reject_reason_{{ $user->id }}" name="reason" rows="2" maxlength="500" class="input" placeholder="e.g. We couldn't confirm your business details."></textarea>
            <button class="btn-danger btn-sm w-full">Reject sign-up</button>
        </form>
    </details>
</div>
