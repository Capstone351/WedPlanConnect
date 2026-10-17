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

// Front-page hero slideshow: cross-fades every 6 seconds (paused for users who prefer reduced motion).
const slideshow = document.querySelector('[data-slideshow]');
if (slideshow && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const slides = [...slideshow.querySelectorAll('[data-slide]')];
    const dots = [...slideshow.querySelectorAll('[data-slide-dot]')];
    let current = 0;
    setInterval(() => {
        const next = (current + 1) % slides.length;
        slides[current].classList.replace('opacity-100', 'opacity-0');
        slides[next].classList.replace('opacity-0', 'opacity-100');
        dots[current]?.classList.remove('w-8', 'bg-gold-300');
        dots[current]?.classList.add('w-3', 'bg-white/40');
        dots[next]?.classList.remove('w-3', 'bg-white/40');
        dots[next]?.classList.add('w-8', 'bg-gold-300');
        current = next;
    }, 6000);
}

// Wedding gallery lightbox
const lightbox = document.getElementById('lightbox');
if (lightbox) {
    const items = [...document.querySelectorAll('[data-gallery-index]')];
    const img = lightbox.querySelector('[data-lightbox-img]');
    const caption = lightbox.querySelector('[data-lightbox-caption]');
    let current = 0;

    const show = (i) => {
        current = (i + items.length) % items.length;
        img.src = items[current].dataset.src;
        img.alt = items[current].dataset.caption || `Photo ${current + 1}`;
        caption.textContent = `${items[current].dataset.caption || ''}  (${current + 1} of ${items.length})`.trim();
        lightbox.classList.replace('hidden', 'flex');
    };
    const close = () => lightbox.classList.replace('flex', 'hidden');

    items.forEach((el, i) => el.addEventListener('click', () => show(i)));
    lightbox.querySelector('[data-lightbox-close]').addEventListener('click', close);
    lightbox.querySelector('[data-lightbox-prev]').addEventListener('click', () => show(current - 1));
    lightbox.querySelector('[data-lightbox-next]').addEventListener('click', () => show(current + 1));
    lightbox.addEventListener('click', (e) => e.target === lightbox && close());
    document.addEventListener('keydown', (e) => {
        if (lightbox.classList.contains('hidden')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') show(current - 1);
        if (e.key === 'ArrowRight') show(current + 1);
    });
}

// Number-only fields (e.g. 11-digit mobile number): strip anything that isn't a digit as the user types.
document.addEventListener('input', (event) => {
    const field = event.target;
    if (!field.matches?.('[data-digits-only]')) return;
    const digits = field.value.replace(/\D/g, '').slice(0, field.maxLength > 0 ? field.maxLength : undefined);
    if (digits !== field.value) field.value = digits;
});

// Money fields: allow digits, commas and a decimal point; show thousands separators.
const parseMoney = (v) => parseFloat(String(v).replace(/[^0-9.]/g, '')) || 0;
const peso = (n) => '₱' + Math.round(n).toLocaleString('en-PH');
document.addEventListener('input', (event) => {
    const field = event.target;
    if (!field.matches?.('[data-money]')) return;
    const cleaned = field.value.replace(/[^0-9.,]/g, '');
    if (cleaned !== field.value) field.value = cleaned;
});
document.addEventListener(
    'blur',
    (event) => {
        const field = event.target;
        if (!field.matches?.('[data-money]') || field.value.trim() === '') return;
        const n = parseMoney(field.value);
        field.value = n.toLocaleString('en-PH', { maximumFractionDigits: 2 });
    },
    true,
);

// Wedding budget: live totals
const budgetForm = document.querySelector('[data-budget-form]');
if (budgetForm) {
    const q = (s) => budgetForm.querySelector(s);
    const update = () => {
        const total = parseMoney(q('[data-budget-total]').value);
        const allocated = [...budgetForm.querySelectorAll('[data-budget-alloc]')].reduce((sum, f) => sum + parseMoney(f.value), 0);
        const guests = parseInt(q('[data-budget-guests]').value, 10) || 0;
        const over = allocated > total;
        q('[data-budget-show-total]').textContent = peso(total);
        q('[data-budget-allocated]').textContent = peso(allocated);
        q('[data-budget-remaining]').textContent = over ? '−' + peso(allocated - total) : peso(total - allocated);
        q('[data-budget-per-guest]').textContent = guests && total ? peso(total / guests) : '–';
        q('[data-budget-bar]').style.width = (total ? Math.min(100, (allocated / total) * 100) : 0) + '%';
        q('[data-budget-bar]').classList.toggle('from-red-300', over);
        q('[data-budget-over]').classList.toggle('hidden', !over);
    };
    budgetForm.addEventListener('input', update);
    update();
}

// Booking set-up: show how a product and quantity would fit the couple's budget before adding it.
document.querySelectorAll('[data-setup-budget]').forEach((form) => {
    const budget = JSON.parse(form.dataset.setupBudget);
    const product = form.querySelector('[name="supplier_product_id"]');
    const quantity = form.querySelector('[name="quantity"]');
    const hint = form.querySelector('[data-setup-hint]');
    const tones = { ok: ['bg-emerald-50', 'text-emerald-800', 'ring-emerald-200'], over: ['bg-red-50', 'text-red-700', 'ring-red-200'] };

    const update = () => {
        const option = product.selectedOptions[0];
        const price = parseFloat(option?.dataset.price);
        if (!option?.value || Number.isNaN(price)) {
            hint.classList.add('hidden');
            return;
        }

        const cost = price * (parseInt(quantity.value, 10) || 0);
        const category = option.dataset.category;
        const parts = [`Adds ${peso(cost)}.`];
        let over = false;
        const describe = (left, label) => {
            const after = left - cost;
            over ||= after < 0;
            parts.push(after < 0 ? `${peso(-after)} over the ${label}.` : `${peso(after)} left in the ${label}.`);
        };
        if (budget.categories[category] !== undefined) describe(budget.categories[category], `${category} budget`);
        if (budget.overall !== null) describe(budget.overall, 'total budget');

        hint.textContent = parts.join(' ');
        hint.classList.remove('hidden', ...tones.ok, ...tones.over);
        hint.classList.add(...(over ? tones.over : tones.ok));
    };

    product.addEventListener('change', update);
    quantity.addEventListener('input', update);
});

// Planner choice cards: update the "Selected" label as soon as a card is picked.
document.querySelectorAll('input[name="planner_id"][type="radio"]').forEach((radio) => {
    radio.addEventListener('change', () => {
        document.querySelectorAll('input[name="planner_id"][type="radio"]').forEach((r) => {
            const card = r.closest('label');
            card.classList.toggle('ring-2', r.checked);
            card.classList.toggle('ring-gold-400', r.checked);
            const btn = card.querySelector('.btn-secondary');
            if (btn) btn.textContent = r.checked ? 'Selected' : 'Select this planner';
        });
    });
});

// Wedding date: live preview + availability hint (no other couples' details are shown).
const dateForm = document.querySelector('[data-date-form]');
if (dateForm) {
    const dateInput = dateForm.querySelector('[data-date-input]');
    const venueInput = dateForm.querySelector('[data-venue-input]');
    const hint = dateForm.querySelector('[data-date-hint]');
    const preview = dateForm.querySelector('[data-date-preview]');
    const days = dateForm.querySelector('[data-days-preview]');
    let timer;

    const showHint = (text, tone) => {
        hint.textContent = text;
        hint.className = 'mt-2 rounded-lg px-3 py-2 text-xs ' + (tone === 'bad' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : tone === 'warn' ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200');
    };

    const check = async () => {
        if (!dateInput.value) {
            hint.classList.add('hidden');
            preview.textContent = 'Choose a date';
            days.textContent = '';
            return;
        }
        const d = new Date(dateInput.value + 'T00:00:00');
        preview.textContent = d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const diff = Math.round((d - today) / 86400000);
        days.textContent = diff > 0 ? `${diff} days to go` : '';

        try {
            const url = new URL(dateForm.dataset.availabilityUrl, location.origin);
            url.searchParams.set('date', dateInput.value);
            if (venueInput.value.trim()) url.searchParams.set('venue', venueInput.value.trim());
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (data.past) showHint('Please choose a future date.', 'bad');
            else if (data.venue_taken) showHint('That venue is already booked with us on this date. Consider your backup date or another venue.', 'bad');
            else if (data.weddings > 0) showHint(`We already have ${data.weddings} wedding${data.weddings > 1 ? 's' : ''} on this date. Your planner will confirm availability.`, 'warn');
            else showHint('Good news! We have no weddings booked on this date yet.', 'ok');
        } catch {
            hint.classList.add('hidden');
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(check, 300);
    };
    dateInput.addEventListener('change', schedule);
    venueInput.addEventListener('input', schedule);
    if (dateInput.value) check();
}

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

// Pre-fill client name & phone from the selected client account, and show the planner they chose.
const clientSelect = document.querySelector('[data-client-select]');
if (clientSelect) {
    const applyClient = (fill) => {
        const option = clientSelect.selectedOptions[0];
        const name = document.querySelector('[name="client_name"]');
        const phone = document.querySelector('[name="contact_number"]');
        if (fill && option?.dataset.name && name && !name.value) name.value = option.dataset.name;
        if (fill && option?.dataset.phone && phone && !phone.value) phone.value = option.dataset.phone;

        const hint = document.querySelector('[data-client-planner-hint]');
        const planner = document.getElementById('planner_id');
        if (hint) {
            const notes = [];
            if (option?.dataset.plannerName) notes.push(`★ Chose ${option.dataset.plannerName} as their planner.`);
            if (option?.dataset.dateLabel) notes.push(`📅 Wants to marry on ${option.dataset.dateLabel}.`);
            if (option?.dataset.venue) notes.push(`Preferred venue: ${option.dataset.venue}.`);
            hint.classList.toggle('hidden', notes.length === 0);
            hint.textContent = notes.join(' ');
        }
        if (fill && planner && option?.dataset.planner && !planner.value) planner.value = option.dataset.planner;
        const date = document.getElementById('event_date');
        const venue = document.getElementById('venue');
        if (fill && date && option?.dataset.date && !date.value) date.value = option.dataset.date;
        if (fill && venue && option?.dataset.venue && !venue.value) venue.value = option.dataset.venue;
    };
    clientSelect.addEventListener('change', () => applyClient(true));
    applyClient(false);
}

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
