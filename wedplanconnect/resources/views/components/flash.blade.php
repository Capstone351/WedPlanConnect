@if (session('status'))
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm transition-opacity duration-500" role="status" data-flash>
        <x-icon name="check" class="mt-0.5 size-5 shrink-0" />
        <div class="flex-1">{{ session('status') }}</div>
        <button type="button" class="-m-1 rounded p-1 text-emerald-600 hover:bg-emerald-100" data-dismiss aria-label="Dismiss"><x-icon name="x" class="size-4" /></button>
    </div>
@endif
@if ($errors->any())
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm" role="alert">
        <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
        <ul class="flex-1 space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="-m-1 rounded p-1 text-red-600 hover:bg-red-100" data-dismiss aria-label="Dismiss"><x-icon name="x" class="size-4" /></button>
    </div>
@endif
