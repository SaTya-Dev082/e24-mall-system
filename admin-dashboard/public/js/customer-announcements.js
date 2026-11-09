const announcementForm = document.querySelector('[data-announcement-form]');
if (announcementForm) {
    const audience = document.getElementById('announcement-audience');
    const recipientArea = announcementForm.querySelector('[data-announcement-recipient]');
    const recipient = document.getElementById('announcement-recipient');
    const search = document.getElementById('announcement-search');
    const status = announcementForm.querySelector('[data-recipient-status]');
    const sync = () => { recipientArea.hidden = audience.value === 'all'; recipient.required = audience.value === 'customer'; recipient.disabled = audience.value === 'all'; };
    audience.addEventListener('change', sync); sync();
    let timer, request, version = 0;
    search.addEventListener('input', () => {
        clearTimeout(timer); request?.abort();
        const current = ++version;
        timer = setTimeout(async () => {
            request = new AbortController();
            status.textContent = 'Finding customers…';
            try {
                const url = new URL(search.dataset.searchUrl); url.searchParams.set('search', search.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal });
                if (!response.ok) throw new Error('Search failed');
                const result = await response.json();
                if (current !== version) return;
                recipient.replaceChildren(new Option('Select a customer', ''));
                result.customers.forEach(customer => recipient.add(new Option(`${customer.name} · ${customer.email}`, customer.id)));
                status.textContent = result.customers.length ? 'Showing up to 30 matching customers.' : 'No active customers found.';
            } catch (error) { if (error.name !== 'AbortError' && current === version) status.textContent = 'Could not load customers. Please try again.'; }
        }, 300);
    });
}
