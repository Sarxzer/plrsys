const toggle = document.querySelector('.nav-toggle');
const links = document.querySelector('.nav-links');

toggle.addEventListener('click', () => {
    const isOpen = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', isOpen);
});

// close drawer when a link is clicked
links.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
        links.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', false);
    });
});


// Auto-hide alerts after 3 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.animation = 'fadeOut 0.5s ease-out forwards';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 3000);
});

// Inline edit toggles for manage system fields
document.querySelectorAll('.inline-edit').forEach(wrapper => {
    const display = wrapper.querySelector('.inline-edit-display');
    const form = wrapper.querySelector('.inline-edit-form');
    const cancel = wrapper.querySelector('.inline-edit-cancel');
    const input = wrapper.querySelector('.inline-edit-input, .inline-edit-select');

    if (!display || !form || !cancel || !input) {
        return;
    }

    input.dataset.initialValue = input.value;

    const startEdit = () => {
        wrapper.classList.add('is-editing');
        if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
            input.focus();
            input.select();
        } else {
            input.focus();
        }
    };

    const stopEdit = () => {
        wrapper.classList.remove('is-editing');
        input.value = input.dataset.initialValue || '';
    };

    display.addEventListener('click', startEdit);
    cancel.addEventListener('click', event => {
        event.preventDefault();
        stopEdit();
    });
    form.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            stopEdit();
        }
    });
});

// Cookie consent banner
const cookieBanner = document.querySelector('[data-cookie-banner]');
if (cookieBanner) {
    const acceptButton = cookieBanner.querySelector('[data-cookie-accept]');
    const rejectButton = cookieBanner.querySelector('[data-cookie-reject]');

    const setConsent = value => {
        try {
            localStorage.setItem('cookie_consent', value);
        } catch (error) {
            // Ignore storage errors (private mode, disabled storage)
        }
        cookieBanner.classList.remove('is-visible');
    };

    let storedConsent = null;
    try {
        storedConsent = localStorage.getItem('cookie_consent');
    } catch (error) {
        storedConsent = null;
    }

    if (!storedConsent) {
        cookieBanner.classList.add('is-visible');
    }

    if (acceptButton) {
        acceptButton.addEventListener('click', () => setConsent('accepted'));
    }
    if (rejectButton) {
        rejectButton.addEventListener('click', () => setConsent('rejected'));
    }
}