const fallback = '/images/product-placeholder.svg';
document.querySelectorAll('[data-fallback]').forEach(image => {
 image.addEventListener('error', () => { if (!image.src.endsWith(fallback)) image.src = fallback; });
 if (image.complete && !image.naturalWidth) image.src = fallback;
});
document.querySelectorAll('[data-gallery]').forEach(button => button.addEventListener('click', () => {
 const image = document.getElementById('gallery-main');
 image.src = button.dataset.gallery;
 image.alt = button.dataset.alt;
 document.querySelectorAll('[data-gallery]').forEach(thumbnail => thumbnail.setAttribute('aria-pressed', String(thumbnail === button)));
}));
document.querySelectorAll('input[name="variant"]').forEach(input => input.addEventListener('change', () => {
 document.getElementById('selected-price').textContent = `$${input.dataset.price}`;
 document.getElementById('selected-stock').textContent = Number(input.dataset.stock) > 0 ? `${input.dataset.stock} available` : 'Out of stock';
}));
const menu = document.querySelector('.mobile-menu');
const navigation = document.getElementById('category-navigation');
menu?.addEventListener('click', () => {
    const expanded = menu.getAttribute('aria-expanded') !== 'true';
    menu.setAttribute('aria-expanded', String(expanded));
    navigation.classList.toggle('menu-open', expanded);
});
navigation?.addEventListener('keydown', event => {
    if (event.key === 'Escape' && menu?.getAttribute('aria-expanded') === 'true') {
        menu.click();
        menu.focus();
    }
});
document.querySelectorAll('[data-banner-carousel]').forEach(carousel => {
    const slides = [...carousel.querySelectorAll('[data-banner-slide]')];
    if (slides.length < 2) return;
    const dots = [...carousel.querySelectorAll('[data-banner-dot]')];
    const toggle = carousel.querySelector('[data-banner-toggle]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let timer;
    let paused = motion.matches;
    let hovered = false;
    let touchStart;
    function schedule() {
        window.clearTimeout(timer);
        if (!paused && !hovered && !document.hidden && !carousel.contains(document.activeElement)) {
            timer = window.setTimeout(() => showBanner(index + 1), 3000);
        }
    }
    function showBanner(next) {
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, position) => { slide.hidden = position !== index; });
        dots.forEach((dot, position) => dot.setAttribute('aria-pressed', String(position === index)));
        carousel.querySelector('[data-banner-position]').textContent = `${index + 1} / ${slides.length}`;
        schedule();
    }
    function updateToggle() {
        toggle.textContent = paused ? 'Play' : 'Pause';
        toggle.setAttribute('aria-label', paused ? 'Start automatic banner rotation' : 'Pause automatic banner rotation');
    }
    carousel.querySelector('[data-banner-prev]').addEventListener('click', () => showBanner(index - 1));
    carousel.querySelector('[data-banner-next]').addEventListener('click', () => showBanner(index + 1));
    dots.forEach((dot, position) => dot.addEventListener('click', () => showBanner(position)));
    toggle.addEventListener('click', () => { paused = !paused; updateToggle(); schedule(); });
    carousel.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    carousel.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    carousel.addEventListener('focusin', schedule);
    carousel.addEventListener('focusout', () => window.setTimeout(schedule, 0));
    document.addEventListener('visibilitychange', schedule);
    motion.addEventListener('change', () => { paused = motion.matches; updateToggle(); schedule(); });
    carousel.addEventListener('touchstart', event => { touchStart = event.changedTouches[0]; window.clearTimeout(timer); }, { passive: true });
    carousel.addEventListener('touchend', event => {
        if (!touchStart) return;
        const end = event.changedTouches[0];
        const delta = end.clientX - touchStart.clientX;
        if (Math.abs(delta) > 50 && Math.abs(delta) > Math.abs(end.clientY - touchStart.clientY)) showBanner(index + (delta < 0 ? 1 : -1));
        else schedule();
        touchStart = null;
    }, { passive: true });
    updateToggle();
    schedule();
});
const cardDescriptions = [...document.querySelectorAll('[data-card-description]')].map(element => ({
    element,
    text: element.querySelector('[data-description-text]'),
    more: element.querySelector('[data-description-more]'),
    original: element.querySelector('[data-description-text]').textContent,
}));
function fitCardDescription({ element, text, more, original }) {
    if (!element.clientWidth) return;
    text.textContent = original;
    more.hidden = true;
    if (element.scrollHeight <= element.clientHeight + 1) return;
    more.hidden = false;
    const characters = Array.from(original);
    let low = 0;
    let high = characters.length;
    while (low < high) {
        const middle = Math.ceil((low + high) / 2);
        text.textContent = characters.slice(0, middle).join('').trimEnd();
        if (element.scrollHeight <= element.clientHeight + 1) low = middle;
        else high = middle - 1;
    }
    text.textContent = characters.slice(0, low).join('').trimEnd();
}
let descriptionFrame;
function fitCardDescriptions() {
    window.cancelAnimationFrame(descriptionFrame);
    descriptionFrame = window.requestAnimationFrame(() => cardDescriptions.forEach(fitCardDescription));
}
if (cardDescriptions.length) {
    const observer = new ResizeObserver(fitCardDescriptions);
    cardDescriptions.forEach(({ element }) => observer.observe(element));
    document.fonts.ready.then(fitCardDescriptions);
    fitCardDescriptions();
}
const detailQuantity = document.getElementById('detail-quantity');
function updateDetailQuantity(maximum) {
    if (!detailQuantity) return;
    const maximumQuantity = Math.max(1, Math.min(99, maximum));
    detailQuantity.max = String(maximumQuantity);
    detailQuantity.value = String(Math.max(1, Math.min(maximumQuantity, Number(detailQuantity.value) || 1)));
    document.querySelectorAll('[data-quantity-step]').forEach(button => {
        button.disabled = Number(button.dataset.quantityStep) < 0
            ? Number(detailQuantity.value) <= 1 : Number(detailQuantity.value) >= maximumQuantity;
    });
}
if (detailQuantity) {
    document.querySelectorAll('[data-quantity-step]').forEach(button => button.addEventListener('click', () => {
        detailQuantity.value = String((Number(detailQuantity.value) || 1) + Number(button.dataset.quantityStep));
        updateDetailQuantity(Number(detailQuantity.max));
    }));
    document.querySelectorAll('input[name="variant"]').forEach(input => input.addEventListener('change', () => updateDetailQuantity(Number(input.dataset.stock))));
    detailQuantity.addEventListener('change', () => updateDetailQuantity(Number(detailQuantity.max)));
    updateDetailQuantity(99);
}
// Queue absolute cart updates so rapid edits to different rows cannot overwrite each other.
let cartSaveTimer;
let cartBusy = false;
const pendingCartWrites = new Map();
function markCartPending() {
    const checkout = document.querySelector('[data-cart-checkout]');
    checkout?.setAttribute('aria-disabled', 'true');
    const status = document.querySelector('.cart-inline-status');
    if (status) status.textContent = 'Saving…';
}
function queueCartForm(form, submitter = null, immediate = false) {
    if (cartBusy || !form.reportValidity()) return;
    const action = submitter?.getAttribute('formaction') || form.action;
    pendingCartWrites.set(action, { action, data: new FormData(form), focusId: document.activeElement?.id });
    markCartPending();
    window.clearTimeout(cartSaveTimer);
    if (immediate) flushCartWrites();
    else cartSaveTimer = window.setTimeout(flushCartWrites, 400);
}
async function flushCartWrites() {
    if (cartBusy || !pendingCartWrites.size) return;
    const area = document.querySelector('.cart-page-wrap');
    const status = area.querySelector('.cart-inline-status');
    const writes = [...pendingCartWrites.values()];
    pendingCartWrites.clear();
    cartBusy = true;
    area.setAttribute('aria-busy', 'true');
    area.querySelectorAll('.cart-layout').forEach(panel => { panel.inert = true; });
    let latest;
    let error = '';
    try {
        for (const write of writes) {
            const response = await fetch(write.action, { method: 'POST', headers: { Accept: 'application/json' }, body: write.data });
            const result = await response.json();
            if (!response.ok) {
                error = Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save. Please check your selection.';
                break;
            }
            latest = result;
        }
    } catch {
        error = 'Connection problem. Some changes may have been saved. Check your cart before retrying.';
    }
    if (error) {
        // Recover server state without repeating any write after an uncertain network failure.
        try {
            const response = await fetch(window.location.href, { headers: { Accept: 'application/json' } });
            if (response.ok) latest = await response.json();
        } catch { /* Keep the error visible; do not claim the displayed values were saved. */ }
    }
    if (latest?.html) {
        const template = document.createElement('template');
        template.innerHTML = latest.html;
        area.replaceWith(template.content);
        document.querySelectorAll('.header-cart-count').forEach(badge => { badge.textContent = latest.count; });
        document.querySelector('.header-cart')?.setAttribute('aria-label', `Cart, ${latest.count} items`);
        const focusId = writes.at(-1)?.focusId;
        if (focusId) document.getElementById(focusId)?.focus({ preventScroll: true });
        syncCartSelection();
    } else {
        area.removeAttribute('aria-busy');
        area.querySelectorAll('.cart-layout').forEach(panel => { panel.inert = false; });
    }
    const liveStatus = document.querySelector('.cart-inline-status') || status;
    liveStatus.textContent = error;
    if (!error) document.querySelector('[data-cart-checkout]')?.removeAttribute('aria-disabled');
    cartBusy = false;
}
function syncCartSelection() {
    const boxes = [...document.querySelectorAll('[data-cart-select]')];
    const all = document.querySelector('[data-cart-select-all]');
    if (!all) return;
    const count = boxes.filter(box => box.checked).length;
    all.checked = count === boxes.length;
    all.indeterminate = count > 0 && count < boxes.length;
    document.querySelector('.cart-remove-selected').disabled = count === 0;
}
document.addEventListener('submit', event => {
    const form = event.target;
    if (form.matches('[data-cart-quantity], #cart-selection, .cart-remove-form')) {
        event.preventDefault();
        queueCartForm(form, event.submitter, true);
    }
});
document.addEventListener('click', event => {
    if (event.target.closest('[data-cart-checkout][aria-disabled="true"]')) { event.preventDefault(); return; }
    const button = event.target.closest('[data-cart-step]');
    if (!button || cartBusy) return;
    const form = button.closest('form');
    const input = form.querySelector('input[name="quantity"]');
    input.value = String(Math.max(1, Math.min(Number(input.max), (Number(input.value) || 1) + Number(button.dataset.cartStep))));
    form.querySelectorAll('[data-cart-step]').forEach(control => { control.disabled = Number(control.dataset.cartStep) < 0 ? Number(input.value) <= 1 : Number(input.value) >= Number(input.max); });
    queueCartForm(form);
});
document.addEventListener('change', event => {
    if (cartBusy) return;
    if (event.target.matches('[data-cart-quantity] input[name="quantity"]')) queueCartForm(event.target.form);
    if (event.target.matches('[data-cart-select], [data-cart-select-all]')) {
        if (event.target.matches('[data-cart-select-all]')) document.querySelectorAll('[data-cart-select]').forEach(box => { box.checked = event.target.checked; });
        syncCartSelection();
        queueCartForm(document.getElementById('cart-selection'));
    }
});
syncCartSelection();
const notificationsPanel = document.querySelector('.header-notifications');
if (notificationsPanel) {
    document.addEventListener('click', event => {
        if (!notificationsPanel.contains(event.target)) notificationsPanel.open = false;
    });
    notificationsPanel.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            notificationsPanel.open = false;
            notificationsPanel.querySelector('summary').focus();
        }
    });
}
// Product cards add directly, or request an explicit option without navigating away.
function chooseCartOption(options) {
    return new Promise(resolve => {
        const dialog = document.createElement('dialog');
        dialog.className = 'cart-option-dialog';
        const form = document.createElement('form');
        form.method = 'dialog';
        const title = document.createElement('h2');
        title.id = 'cart-option-title';
        title.textContent = 'Choose an option';
        dialog.setAttribute('aria-labelledby', title.id);
        form.append(title);
        options.forEach(option => {
            const label = document.createElement('label');
            const radio = document.createElement('input');
            radio.type = 'radio'; radio.name = 'option'; radio.value = String(option.id); radio.required = true;
            const text = document.createElement('span');
            text.textContent = `${option.name} · $${option.price} · ${option.stock} available`;
            label.append(radio, text); form.append(label);
        });
        const actions = document.createElement('div'); actions.className = 'cart-option-actions';
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.textContent = 'Cancel';
        cancel.addEventListener('click', () => dialog.close());
        const confirm = document.createElement('button'); confirm.type = 'submit'; confirm.textContent = 'Add to Cart';
        actions.append(cancel, confirm); form.append(actions);
        form.addEventListener('submit', event => {
            event.preventDefault();
            dialog.close(new FormData(form).get('option'));
        });
        dialog.append(form); document.body.append(dialog);
        dialog.addEventListener('close', () => { const result = dialog.returnValue; dialog.remove(); resolve(result || null); }, { once: true });
        dialog.showModal();
    });
}
document.querySelectorAll('[data-card-cart]').forEach(form => {
    const button = form.querySelector('button[type="submit"]');
    const feedback = form.querySelector('.card-cart-feedback');
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (button.disabled) return;
        button.disabled = true;
        button.textContent = 'Adding…'; feedback.textContent = '';
        try {
            const data = new FormData(form);
            const send = () => fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: data });
            let response = await send();
            let result = await response.json();
            if (response.status === 409 && result.options) {
                const option = await chooseCartOption(result.options);
                if (!option) return;
                data.set('variant', option);
                response = await send(); result = await response.json();
            }
            if (!response.ok) {
                feedback.textContent = Object.values(result.errors || {}).flat()[0] || result.message || 'Could not add item. Please try again.';
                return;
            }
            document.querySelectorAll('.header-cart-count').forEach(badge => { badge.textContent = result.count; });
            document.querySelector('.header-cart')?.setAttribute('aria-label', `Cart, ${result.count} items`);
            feedback.textContent = result.message;
        } catch {
            feedback.textContent = 'Connection problem. Check your cart before retrying.';
        } finally {
            button.disabled = false; button.textContent = 'Add to Cart';
        }
    });
});
