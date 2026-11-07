document.querySelectorAll('[data-banner-editor]').forEach((editor) => {
    const source = editor.querySelector('[data-banner-image]');
    const alt = editor.querySelector('[data-banner-alt]');
    const image = editor.querySelector('[data-banner-preview]');
    const placeholder = editor.querySelector('[data-banner-placeholder]');
    const error = editor.querySelector('[data-banner-error]');
    let timer;
    const update = () => {
        image.hidden = true;
        placeholder.hidden = false;
        error.hidden = true;
        image.removeAttribute('src');
        try {
            const url = new URL(source.value);
            if (!['http:', 'https:'].includes(url.protocol)) return;
            image.src = url.href;
        } catch (_) { /* Keep the empty preview for incomplete URLs. */ }
    };
    image.addEventListener('load', () => { image.hidden = false; placeholder.hidden = true; });
    image.addEventListener('error', () => { image.hidden = true; placeholder.hidden = false; error.hidden = false; });
    source.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(update, 400); });
    alt.addEventListener('input', () => { image.alt = alt.value; });
    update();
});
