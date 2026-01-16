(() => {
    const formatDateTime = (value) => {
        try {
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).format(value);
        } catch (error) {
            return value.toLocaleString();
        }
    };

    const updateDateTime = () => {
        const now = new Date();
        document.querySelectorAll('[data-navbar-datetime]').forEach((node) => {
            node.textContent = formatDateTime(now);
        });
    };

    const ensureDateTimeInNavbar = () => {
        const navbars = document.querySelectorAll('.navbar');
        if (!navbars.length) {
            return;
        }

        navbars.forEach((navbar) => {
            if (navbar.querySelector('.navbar-date-time')) {
                return;
            }

            const target = navbar.querySelector('.navbar-collapse .ms-auto')
                || navbar.querySelector('.ms-auto')
                || navbar.querySelector('.container-fluid')
                || navbar;

            const wrapper = document.createElement('div');
            wrapper.className = 'navbar-date-time d-flex align-items-center text-muted';
            wrapper.innerHTML = '<i class="fas fa-clock me-2"></i><span data-navbar-datetime></span>';

            if (target.classList && target.classList.contains('ms-auto')) {
                wrapper.classList.add('me-3');
                target.insertBefore(wrapper, target.firstChild);
            } else {
                wrapper.classList.add('ms-auto');
                target.appendChild(wrapper);
            }
        });
    };

    const hideFooters = () => {
        document.querySelectorAll('footer, #footer').forEach((footer) => {
            footer.style.display = 'none';
            footer.setAttribute('aria-hidden', 'true');
        });
    };

    const init = () => {
        ensureDateTimeInNavbar();
        hideFooters();
        updateDateTime();
        setInterval(updateDateTime, 1000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
