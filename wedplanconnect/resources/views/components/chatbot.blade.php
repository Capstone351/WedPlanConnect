{{-- WedBot: AI chatbot / FAQ assistant (UC-11) --}}
<div id="wedbot" class="fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3 print:hidden"
    data-ask-url="{{ route('chatbot.ask') }}" data-faqs-url="{{ route('chatbot.faqs') }}">
    <div data-bot-panel class="hidden w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gold-400/40 bg-gradient-to-r from-brand-800 to-brand-950 px-4 py-3 text-white">
            <div>
                <div class="font-display text-base font-semibold">WedBot</div>
                <div class="text-xs text-brand-100">Your {{ config('wedplan.business_name') }} assistant</div>
            </div>
            <button type="button" data-bot-close class="rounded p-1 text-brand-100 hover:bg-brand-600" aria-label="Close chat">✕</button>
        </div>
        <div data-bot-log class="flex h-80 flex-col gap-2 overflow-y-auto px-3 py-3">
            <div class="flex justify-start">
                <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-stone-100 px-3.5 py-2 text-sm text-stone-800">
                    Hi! I can answer common questions about our packages, payments, suppliers, and your wedding status page. Type a question or browse topics below.
                </div>
            </div>
        </div>
        <div class="border-t border-stone-100 bg-stone-50 px-3 py-2">
            <button type="button" data-bot-topics-toggle class="text-xs font-medium text-brand-700 hover:underline">Browse FAQ topics</button>
            <div data-bot-topics class="mt-2 hidden max-h-48 space-y-1.5 overflow-y-auto text-xs text-stone-500">Loading…</div>
        </div>
        <form data-bot-form class="flex gap-2 border-t border-stone-100 p-3">
            <input type="text" maxlength="500" class="input" placeholder="Ask a question…" aria-label="Your question" autocomplete="off">
            <button class="btn-primary px-3" aria-label="Send">➤</button>
        </form>
    </div>
    <button type="button" data-bot-open class="flex items-center gap-2 rounded-full bg-gradient-to-b from-brand-700 to-brand-900 px-4 py-3 text-sm font-medium text-gold-100 shadow-lg ring-1 ring-gold-400/60 hover:from-brand-800 hover:to-brand-950">
        <x-icon name="chat" class="size-5" /> Ask WedBot
    </button>
</div>
