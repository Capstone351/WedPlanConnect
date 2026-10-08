const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

// Confirmation dialogs for destructive actions: <form data-confirm="Are you sure?">
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Dismissible messages; success messages fade out on their own after 6 seconds.
document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-dismiss]');
    if (btn) btn.parentElement.remove();
});
document.querySelectorAll('[data-flash]').forEach((el) => {
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 6000);
});

// Copy buttons: <button data-copy="#input-id">
document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-copy]');
    if (!btn) return;
    const field = document.querySelector(btn.dataset.copy);
    if (!field) return;
    field.select();
    try {
        await navigator.clipboard.writeText(field.value);
    } catch {
        document.execCommand('copy');
    }
    const original = btn.textContent;
    btn.textContent = 'Copied ✓';
    setTimeout(() => (btn.textContent = original), 1800);
});

// Auto-submit filters: <select data-autosubmit>
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]')) {
        event.target.form?.submit();
    }
});

// Mobile sidebar toggle
document.querySelectorAll('[data-sidebar-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
        document.getElementById('sidebar')?.classList.toggle('-translate-x-full');
        document.getElementById('sidebar-backdrop')?.classList.toggle('hidden');
    });
});

// Toggle blocks: <button data-toggle="#id">
document.querySelectorAll('[data-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => document.querySelector(btn.dataset.toggle)?.classList.toggle('hidden'));
});

// Booking form: switch between existing client and new client account
document.querySelectorAll('[data-client-mode]').forEach((radio) => {
    const apply = () => {
        const mode = document.querySelector('[data-client-mode]:checked')?.value;
        [['client-existing', 'existing'], ['client-new', 'new']].forEach(([id, value]) => {
            const block = document.getElementById(id);
            if (!block) return;
            block.classList.toggle('hidden', mode !== value);
            block.querySelectorAll('input, select').forEach((el) => (el.disabled = mode !== value));
        });
    };
    radio.addEventListener('change', apply);
    apply();
});

// Pre-fill client name & phone from the selected client account
document.querySelector('[data-client-select]')?.addEventListener('change', (event) => {
    const option = event.target.selectedOptions[0];
    const name = document.querySelector('[name="client_name"]');
    const phone = document.querySelector('[name="contact_number"]');
    if (option?.dataset.name && name && !name.value) name.value = option.dataset.name;
    if (option?.dataset.phone && phone && !phone.value) phone.value = option.dataset.phone;
});

/*
 * WedBot — chatbot / FAQ assistant widget (UC-11)
 */
const bot = document.getElementById('wedbot');
if (bot) {
    const panel = bot.querySelector('[data-bot-panel]');
    const log = bot.querySelector('[data-bot-log]');
    const form = bot.querySelector('[data-bot-form]');
    const input = form.querySelector('input');
    const topics = bot.querySelector('[data-bot-topics]');
    let faqsLoaded = false;

    const bubble = (text, who, source) => {
        const row = document.createElement('div');
        row.className = who === 'user' ? 'flex justify-end' : 'flex justify-start';
        const box = document.createElement('div');
        box.className =
            who === 'user'
                ? 'max-w-[85%] rounded-2xl rounded-br-sm bg-brand-600 px-3.5 py-2 text-sm text-white whitespace-pre-line'
                : 'max-w-[85%] rounded-2xl rounded-bl-sm bg-stone-100 px-3.5 py-2 text-sm text-stone-800 whitespace-pre-line';
        box.textContent = text;
        if (source === 'ai') {
            const tag = document.createElement('div');
            tag.className = 'mt-1 text-[10px] uppercase tracking-wide text-stone-400';
            tag.textContent = 'AI-generated · please confirm details with your planner';
            box.appendChild(tag);
        }
        row.appendChild(box);
        log.appendChild(row);
        log.scrollTop = log.scrollHeight;
        return box;
    };

    const loadTopics = async () => {
        if (faqsLoaded) return;
        faqsLoaded = true;
        try {
            const res = await fetch(bot.dataset.faqsUrl, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            topics.innerHTML = '';
            Object.entries(data).forEach(([category, items]) => {
                const details = document.createElement('details');
                details.className = 'group rounded-lg border border-stone-200 bg-white';
                const summary = document.createElement('summary');
                summary.className = 'cursor-pointer list-none px-3 py-2 text-xs font-semibold text-stone-700';
                summary.textContent = `${category} (${items.length})`;
                details.appendChild(summary);
                items.forEach((faq) => {
                    const q = document.createElement('button');
                    q.type = 'button';
                    q.className = 'block w-full border-t border-stone-100 px-3 py-2 text-left text-xs text-brand-700 hover:bg-brand-50';
                    q.textContent = faq.question;
                    q.addEventListener('click', () => {
                        bubble(faq.question, 'user');
                        bubble(faq.answer, 'bot');
                    });
                    details.appendChild(q);
                });
                topics.appendChild(details);
            });
        } catch {
            topics.textContent = 'FAQ topics are unavailable right now.';
        }
    };

    bot.querySelector('[data-bot-open]').addEventListener('click', () => {
        panel.classList.toggle('hidden');
        if (!panel.classList.contains('hidden')) {
            loadTopics();
            input.focus();
        }
    });
    bot.querySelector('[data-bot-close]').addEventListener('click', () => panel.classList.add('hidden'));
    bot.querySelector('[data-bot-topics-toggle]').addEventListener('click', () => topics.classList.toggle('hidden'));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message) return;
        input.value = '';
        bubble(message, 'user');
        const pending = bubble('…', 'bot');
        try {
            const res = await fetch(bot.dataset.askUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ message }),
            });
            const data = await res.json();
            pending.parentElement.remove();
            if (!res.ok) throw new Error(data.message);
            bubble(data.answer, 'bot', data.source);
        } catch {
            pending.parentElement?.remove();
            bubble('Sorry, the assistant is unavailable right now. Please contact your Wedding Planner directly.', 'bot');
        }
    });
}
