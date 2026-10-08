<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotSession;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chatbot knowledge base curated by the Admin from FMT's operational history.
 */
class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $faqs = Faq::when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), fn ($q) => $q->where('question', 'like', '%'.$request->string('q').'%'))
            ->orderBy('category')->orderBy('question')
            ->paginate(20)->withQueryString();

        $categories = Faq::distinct()->orderBy('category')->pluck('category');

        return view('admin.faqs.index', compact('faqs', 'categories'));
    }

    public function create(): View
    {
        return view('admin.faqs.form', ['faq' => new Faq, 'categories' => Faq::distinct()->pluck('category')]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ entry added.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.faqs.form', ['faq' => $faq, 'categories' => Faq::distinct()->pluck('category')]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        return redirect()->route('admin.faqs.index')->with('status', 'FAQ entry updated.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('status', 'FAQ entry removed.');
    }

    /** Session log, highlighting questions the FAQ base could not answer. */
    public function logs(Request $request): View
    {
        $sessions = ChatbotSession::with('user')
            ->when($request->input('filter') === 'unanswered', fn ($q) => $q->whereNull('faq_id'))
            ->when($request->input('filter') === 'ai', fn ($q) => $q->where('api_used', true))
            ->latest()
            ->paginate(25)->withQueryString();

        $stats = [
            'total' => ChatbotSession::count(),
            'faq' => ChatbotSession::whereNotNull('faq_id')->count(),
            'ai' => ChatbotSession::where('api_used', true)->count(),
            'unanswered' => ChatbotSession::whereNull('faq_id')->where('api_used', false)->count(),
        ];

        return view('admin.faqs.logs', compact('sessions', 'stats'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
