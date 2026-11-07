(() => {
    const app = document.querySelector('.nexora');
    if (!app) return;
    app.classList.add('nx-js');
    const read = (key, fallback) => { try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; } };
    const save = (key, value) => { try { localStorage.setItem(key, value); } catch { /* Preferences are optional in private browsing. */ } };
    const themes = [...document.querySelectorAll('[data-theme-select]')];
    function setTheme(theme) {
        const selected = theme === 'light' ? 'light' : 'dark';
        app.dataset.theme = selected;
        themes.forEach(select => { select.value = selected; });
        save('shop-admin-theme', selected);
    }
    setTheme(read('shop-admin-theme', 'dark'));
    themes.forEach(select => select.addEventListener('change', () => setTheme(select.value)));
    const collapseButton = document.querySelector('[data-collapse]');
    const compactToggle = document.querySelector('[data-compact-toggle]');
    const navGroups = [...document.querySelectorAll('[data-nav-group]')];
    const isDesktop = () => window.matchMedia('(min-width: 701px)').matches;
    function setCollapsed(collapsed) {
        app.classList.toggle('sidebar-collapsed', collapsed);
        collapseButton?.setAttribute('aria-expanded', String(!collapsed));
        if (compactToggle) compactToggle.checked = collapsed;
        save('shop-admin-sidebar', String(collapsed));
    }
    setCollapsed(read('shop-admin-sidebar', 'false') === 'true');
    collapseButton?.addEventListener('click', () => setCollapsed(!app.classList.contains('sidebar-collapsed')));
    compactToggle?.addEventListener('change', () => setCollapsed(compactToggle.checked));
    const initialGroup = navGroups.find(group => group.classList.contains('has-active-page'))
        ?? navGroups.find(group => group.dataset.navGroup === read('e24-open-menu', ''));
    navGroups.forEach(group => { group.open = group === initialGroup; });
    navGroups.forEach(group => {
        group.addEventListener('toggle', () => {
            if (group.open) {
                navGroups.forEach(other => { if (other !== group) other.open = false; });
                save('e24-open-menu', group.dataset.navGroup);
            } else if (!navGroups.some(other => other.open)) {
                save('e24-open-menu', '');
            }
        });
        group.querySelector('summary')?.addEventListener('click', event => {
            if (isDesktop() && app.classList.contains('sidebar-collapsed')) {
                event.preventDefault();
                setCollapsed(false);
                group.open = true;
            }
        });
    });
    const sectionLinks = [...document.querySelectorAll('[data-nav-section]')];
    function updateDashboardSection() {
        if (!document.querySelector('#analytics')) return;
        const section = window.location.hash === '#analytics' ? 'analytics' : 'overview';
        sectionLinks.forEach(link => {
            const active = link.dataset.navSection === section;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', section === 'analytics' ? 'location' : 'page');
            else link.removeAttribute('aria-current');
        });
    }
    updateDashboardSection();
    window.addEventListener('hashchange', updateDashboardSection);
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    const sidebar = document.querySelector('#workspace-navigation');
    const desktopLayout = window.matchMedia('(min-width: 701px)');
    const syncSidebarAccessibility = () => {
        if (sidebar) sidebar.inert = !desktopLayout.matches && !app.classList.contains('mobile-menu-open');
    };
    const closeMenu = () => {
        app.classList.remove('mobile-menu-open');
        mobileMenu?.setAttribute('aria-expanded', 'false');
        syncSidebarAccessibility();
    };
    syncSidebarAccessibility();
    desktopLayout.addEventListener('change', syncSidebarAccessibility);
    document.querySelectorAll('.nx-submenu-link').forEach(link => link.addEventListener('click', closeMenu));
    mobileMenu?.addEventListener('click', () => {
        const opened = app.classList.toggle('mobile-menu-open');
        mobileMenu.setAttribute('aria-expanded', String(opened));
        syncSidebarAccessibility();
    });
    document.addEventListener('keydown', event => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault(); document.querySelector('[data-search]')?.focus();
        }
        if (event.key === 'Escape') {
            const focusWasInSidebar = sidebar?.contains(document.activeElement) && !desktopLayout.matches;
            closeMenu();
            if (focusWasInSidebar) mobileMenu?.focus();
            document.querySelectorAll('.nx-popover[open]').forEach(details => { details.open = false; });
        }
    });
    document.addEventListener('click', event => {
        document.querySelectorAll('.nx-popover[open]').forEach(details => { if (!details.contains(event.target)) details.open = false; });
        if (!event.target.closest('.nx-sidebar, [data-mobile-menu]')) closeMenu();
    });
    document.querySelectorAll('[data-auto-submit]').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));
    const dialog = document.querySelector('#stock-dialog');
    const stockForm = document.querySelector('#stock-adjustment-form');
    document.querySelectorAll('[data-stock-adjust]').forEach(button => button.addEventListener('click', () => {
        if (!dialog || !stockForm) return;
        stockForm.reset();
        stockForm.action = button.dataset.action;
        document.querySelector('#stock-dialog-label').textContent = button.dataset.label;
        if (globalThis.crypto?.randomUUID) stockForm.elements.idempotency_key.value = crypto.randomUUID();
        document.querySelector('[data-return-reference]').hidden = true;
        stockForm.elements.reference.required = false;
        stockForm.elements.quantity.min = '1';
        const submit = stockForm.querySelector('[data-busy-submit]');
        submit.disabled = false;
        submit.textContent = 'Save stock change';
        dialog.showModal();
        stockForm.elements.type.focus();
    }));
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog?.close()));
    dialog?.addEventListener('click', event => { if (event.target === dialog && event.clientX) {
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    } });
    stockForm?.elements.type.addEventListener('change', () => {
        const returning = stockForm.elements.type.value === 'return';
        document.querySelector('[data-return-reference]').hidden = !returning;
        stockForm.elements.reference.required = returning;
        stockForm.elements.quantity.min = stockForm.elements.type.value === 'adjustment' ? '-1000000000' : '1';
    });
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
        const button = form.querySelector('[data-busy-submit]');
        if (button) { button.disabled = true; button.textContent = 'Saving…'; }
    }));
    window.addEventListener('pageshow', () => document.querySelectorAll('[data-busy-submit]').forEach(button => {
        button.disabled = false;
        if (!button.dataset.originalText) button.dataset.originalText = button.textContent;
        button.textContent = button.dataset.originalText;
    }));
})();
