@php $editing = $faq->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit FAQ' : 'Add FAQ'" :heading="$editing ? 'Edit FAQ Entry' : 'Add FAQ Entry'">
    <form method="POST" action="{{ $editing ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body space-y-5">
            <div>
                <label for="category" class="label">Category</label>
                <input id="category" name="category" list="faq-categories" value="{{ old('category', $faq->category) }}" required maxlength="50" class="input" placeholder="e.g. Payments">
                <datalist id="faq-categories">
                    @foreach ($categories as $c)<option value="{{ $c }}">@endforeach
                </datalist>
            </div>
            <div>
                <label for="question" class="label">Question</label>
                <input id="question" name="question" value="{{ old('question', $faq->question) }}" required maxlength="1000" class="input">
            </div>
            <div>
                <label for="answer" class="label">Answer</label>
                <textarea id="answer" name="answer" rows="6" required maxlength="5000" class="input">{{ old('answer', $faq->answer) }}</textarea>
            </div>
            <div>
                <label for="keywords" class="label">Extra keywords <span class="font-normal text-stone-400">(optional, comma-separated)</span></label>
                <input id="keywords" name="keywords" value="{{ old('keywords', $faq->keywords) }}" maxlength="255" class="input" placeholder="downpayment, deposit, reservation fee">
                <p class="hint">Synonyms clients might use. Improves keyword matching.</p>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route('admin.faqs.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save</button>
        </div>
    </form>
</x-layouts.app>
