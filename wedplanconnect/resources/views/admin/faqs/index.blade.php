<x-layouts.app title="Chatbot FAQ" heading="Chatbot Knowledge Base" subheading="FAQ entries curated from FMT's operational experience. WedBot matches client questions against these.">
    <x-slot:actions>
        <a href="{{ route('admin.faqs.logs') }}" class="btn-secondary"><x-icon name="chat" class="size-4" /> Session logs</a>
        <a href="{{ route('admin.faqs.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add FAQ</a>
    </x-slot:actions>

    <div class="card">
        <form class="flex flex-wrap items-end gap-3 border-b border-stone-100 p-4">
            <div class="min-w-48 flex-1">
                <label class="label" for="q">Search questions</label>
                <input id="q" name="q" value="{{ request('q') }}" class="input">
            </div>
            <div>
                <label class="label" for="category">Category</label>
                <select id="category" name="category" class="input" data-autosubmit>
                    <option value="">All categories</option>
                    @foreach ($categories as $c)
                        <option @selected(request('category') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-secondary">Filter</button>
        </form>
        <ul class="divide-y divide-stone-100">
            @forelse ($faqs as $faq)
                <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <span class="badge-brand">{{ $faq->category }}</span>
                        <div class="mt-2 font-medium">{{ $faq->question }}</div>
                        <p class="mt-1 text-sm text-stone-600">{{ \Illuminate\Support\Str::limit($faq->answer, 220) }}</p>
                        @if ($faq->keywords)
                            <p class="mt-1 text-xs text-stone-400">Keywords: {{ $faq->keywords }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <a href="{{ route('admin.faqs.edit', $faq) }}" class="btn-secondary btn-sm">Edit</a>
                        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="Remove this FAQ entry?">@csrf @method('DELETE')<button class="btn-danger btn-sm">Remove</button></form>
                    </div>
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm text-stone-500">No FAQ entries yet.</li>
            @endforelse
        </ul>
        <div class="p-4">{{ $faqs->links() }}</div>
    </div>
</x-layouts.app>
