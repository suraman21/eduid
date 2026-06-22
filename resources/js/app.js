import './bootstrap';

/*
 * Global behaviours wired through delegated listeners. Under the strict CSP
 * (script-src 'self' 'nonce-…') inline event-handler attributes (onclick, …)
 * are blocked, so the markup uses data-* attributes handled here instead.
 *
 *   data-href="URL"                       → navigate on click (clickable rows)
 *   data-print                            → window.print() on click
 *   data-confirm="message"  (on <form>)   → confirm() before submit
 *   data-autosubmit         (on <input>)  → submit the parent form on change
 *   data-toggle-target="id"               → show/hide #id on <select> change
 *   data-toggle-value="x"                    (shown when select value === x)
 */
document.addEventListener('click', (e) => {
    const nav = e.target.closest('[data-href]');
    if (nav) {
        // Ignore clicks that originate on a real link/button inside the row.
        if (!e.target.closest('a, button')) {
            window.location = nav.getAttribute('data-href');
        }
        return;
    }

    if (e.target.closest('[data-print]')) {
        window.print();
    }
});

document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
    }
});

document.addEventListener('change', (e) => {
    const auto = e.target.closest('[data-autosubmit]');
    if (auto && auto.form) {
        auto.form.submit();
        return;
    }

    const toggler = e.target.closest('[data-toggle-target]');
    if (toggler) {
        const target = document.getElementById(toggler.getAttribute('data-toggle-target'));
        if (target) {
            const showOn = toggler.getAttribute('data-toggle-value');
            target.style.display = toggler.value === showOn ? 'block' : 'none';
        }
    }
});
