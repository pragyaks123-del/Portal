const navToggle = document.querySelector('[data-nav-toggle]');
const navMenu = document.querySelector('[data-nav-menu]');
const siteHeader = document.querySelector('.site-header');
const transitionableLinks = Array.from(document.querySelectorAll('a[href]')).filter((link) => {
    const href = link.getAttribute('href');

    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }

    if (link.hasAttribute('download') || link.target === '_blank') {
        return false;
    }

    try {
        const url = new URL(link.href, window.location.href);

        return url.origin === window.location.origin;
    } catch {
        return false;
    }
});

document.body.classList.add('page-ready');

if (siteHeader) {
    const syncHeaderScrollState = () => {
        siteHeader.classList.toggle('is-scrolled', window.scrollY > 16);
    };

    window.addEventListener('scroll', syncHeaderScrollState, { passive: true });
    syncHeaderScrollState();
}

if (navToggle && navMenu && siteHeader) {
    const setNavState = (isOpen) => {
        navMenu.classList.toggle('open', isOpen);
        siteHeader.classList.toggle('nav-open', isOpen);
        navToggle.setAttribute('aria-expanded', String(isOpen));
    };

    navToggle.addEventListener('click', () => {
        setNavState(!navMenu.classList.contains('open'));
    });

    document.addEventListener('click', (event) => {
        if (!siteHeader.contains(event.target)) {
            setNavState(false);
        }
    });

    navMenu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLElement && event.target.tagName === 'A' && window.innerWidth <= 960) {
            setNavState(false);
        }
    });
}

transitionableLinks.forEach((link) => {
    link.addEventListener('click', (event) => {
        if (
            event.defaultPrevented
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || event.button !== 0
        ) {
            return;
        }

        const targetUrl = new URL(link.href, window.location.href);

        if (targetUrl.href === window.location.href) {
            return;
        }

        event.preventDefault();

        document.body.classList.add('page-transitioning');
        link.classList.add('is-activating');

        window.setTimeout(() => {
            window.location.href = targetUrl.href;
        }, 320);
    });
});

const rangeInput = document.querySelector('[data-range-label]');
if (rangeInput) {
    const label = document.getElementById(rangeInput.dataset.rangeLabel);
    const updateRange = () => {
        const value = Number(rangeInput.value);
        label.textContent = value > 0 ? `$${value.toLocaleString()}` : 'Any salary';
    };
    rangeInput.addEventListener('input', updateRange);
    updateRange();
}

const roleSwitch = document.querySelector('[data-role-switch]');
const companyField = document.querySelector('[data-company-field]');

if (roleSwitch && companyField) {
    const companyInput = companyField.querySelector('input[name="company_name"]');
    const updateRole = () => {
        const isEmployer = roleSwitch.value === 'employer';
        companyField.hidden = !isEmployer;
        if (companyInput) {
            companyInput.required = isEmployer;
            if (!isEmployer) {
                companyInput.value = '';
            }
        }
    };
    roleSwitch.addEventListener('change', updateRole);
    updateRole();
}
