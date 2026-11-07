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
