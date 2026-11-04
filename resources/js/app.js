import './bootstrap';

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
};

ready(() => {
    const header = document.querySelector('[data-site-header]');
    if (header) {
        const setHeaderState = () => header.classList.toggle('is-scrolled', window.scrollY > 18);
        setHeaderState();
        window.addEventListener('scroll', setHeaderState, { passive: true });
    }

    const menuToggle = document.querySelector('[data-menu-toggle]');
    const mobileNav = document.querySelector('[data-mobile-nav]');
    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', () => {
            const opening = mobileNav.hasAttribute('hidden');
            mobileNav.toggleAttribute('hidden', !opening);
            menuToggle.setAttribute('aria-expanded', String(opening));
        });
        mobileNav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
            mobileNav.setAttribute('hidden', '');
            menuToggle.setAttribute('aria-expanded', 'false');
        }));
    }

    const sidebar = document.querySelector('[data-sidebar]');
    const sidebarOpen = document.querySelector('[data-sidebar-open]');
    const sidebarClose = document.querySelector('[data-sidebar-close]');
    const sidebarScrim = document.querySelector('[data-sidebar-scrim]');
    if (sidebar && sidebarScrim) {
        const toggleSidebar = (open) => {
            sidebar.classList.toggle('is-open', open);
            sidebarScrim.toggleAttribute('hidden', !open);
            document.body.style.overflow = open ? 'hidden' : '';
        };
        sidebarOpen?.addEventListener('click', () => toggleSidebar(true));
        sidebarClose?.addEventListener('click', () => toggleSidebar(false));
        sidebarScrim.addEventListener('click', () => toggleSidebar(false));
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') toggleSidebar(false);
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('.password-field')?.querySelector('[data-password-input]');
            if (!input) return;
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            button.textContent = reveal ? '◎' : '◉';
        });
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm)) event.preventDefault();
        });
    });
});
