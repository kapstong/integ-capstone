(() => {
    const initTabPersistence = () => {
        if (!window.bootstrap || !bootstrap.Tab) {
            return;
        }

        const tabTriggers = document.querySelectorAll('[data-bs-toggle="tab"]');
        if (!tabTriggers.length) {
            return;
        }

        const storageKey = `activeTab:${window.location.pathname}`;
        const getTriggerKey = (trigger) => (
            trigger.id
            || trigger.getAttribute('data-bs-target')
            || trigger.getAttribute('href')
        );

        tabTriggers.forEach((trigger) => {
            trigger.addEventListener('shown.bs.tab', (event) => {
                const key = getTriggerKey(event.target);
                if (key) {
                    localStorage.setItem(storageKey, key);
                }
            });
        });

        const savedKey = localStorage.getItem(storageKey);
        if (!savedKey) {
            return;
        }

        const savedTrigger = document.getElementById(savedKey)
            || document.querySelector(`[data-bs-target="${savedKey}"]`)
            || document.querySelector(`[href="${savedKey}"]`);

        if (savedTrigger) {
            new bootstrap.Tab(savedTrigger).show();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabPersistence);
    } else {
        initTabPersistence();
    }
})();
