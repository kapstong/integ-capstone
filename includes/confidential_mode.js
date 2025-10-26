/**
 * ATIERA Confidential Mode System
 * Automatically blurs/hides sensitive financial data and provides unlock functionality
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiUrl: '/api/confidential_mode.php',
        checkInterval: 60000, // Check status every 60 seconds
        autoLockCheckInterval: 30000, // Check for auto-lock every 30 seconds
        selectors: {
            // CSS selectors for elements containing amounts to blur
            amounts: [
                '.amount',
                '.account-amount',
                '.stat-number',
                '.total-row .account-amount',
                '[data-confidential="true"]',
                'td:contains("₱")',
                'span:contains("₱")',
                '.currency-amount'
            ]
        }
    };

    let confidentialMode = {
        enabled: false,
        isUnlocked: false,
        blurStyle: 'blur',
        statusCheckTimer: null,
        autoLockTimer: null,
        lastActivity: Date.now()
    };

    /**
     * Initialize confidential mode
     */
    function init() {
        checkStatus();
        setupActivityTracking();
        createUnlockModal();
        createLockButton();

        // Periodic status check
        confidentialMode.statusCheckTimer = setInterval(checkStatus, CONFIG.checkInterval);

        // Auto-lock check
        confidentialMode.autoLockTimer = setInterval(checkAutoLock, CONFIG.autoLockCheckInterval);

        console.log('✓ Confidential Mode initialized');
    }

    /**
     * Check confidential mode status
     */
    function checkStatus() {
        fetch(CONFIG.apiUrl + '?action=check_status', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                confidentialMode.enabled = data.enabled;
                confidentialMode.isUnlocked = data.is_unlocked;
                confidentialMode.blurStyle = data.blur_style;

                if (confidentialMode.enabled) {
                    if (confidentialMode.isUnlocked) {
                        showConfidentialData();
                        updateLockButton('lock');
                    } else {
                        hideConfidentialData();
                        updateLockButton('unlock');
                    }
                } else {
                    showConfidentialData();
                    hideLockButton();
                }
            }
        })
        .catch(error => {
            console.error('Error checking confidential mode status:', error);
        });
    }

    /**
     * Hide confidential data (blur amounts)
     */
    function hideConfidentialData() {
        // Find all elements with currency symbols or marked as confidential
        const elements = document.querySelectorAll([
            '[data-confidential="true"]',
            '.amount',
            '.account-amount',
            '.stat-number',
            '.currency-amount',
            'td, span, div'
        ].join(','));

        elements.forEach(el => {
            const text = el.textContent || el.innerText;

            // Check if element contains currency amount
            if (text.match(/[₱$€£¥]\s*[\d,]+(\.\d{2})?/) ||
                text.match(/[\d,]+(\.\d{2})?\s*[₱$€£¥]/)) {

                if (!el.hasAttribute('data-confidential-original')) {
                    // Store original content
                    el.setAttribute('data-confidential-original', text);
                    el.setAttribute('data-confidential', 'true');

                    // Apply blur style
                    applyBlurStyle(el);
                }
            }
        });
    }

    /**
     * Apply blur style to element
     */
    function applyBlurStyle(element) {
        const originalText = element.getAttribute('data-confidential-original');

        switch (confidentialMode.blurStyle) {
            case 'blur':
                element.style.filter = 'blur(8px)';
                element.style.userSelect = 'none';
                element.style.cursor = 'not-allowed';
                break;

            case 'asterisk':
                // Replace with asterisks
                const match = originalText.match(/([₱$€£¥])\s*([\d,]+(?:\.\d{2})?)/);
                if (match) {
                    const currency = match[1];
                    const asterisks = '*'.repeat(8);
                    element.textContent = currency + ' ' + asterisks;
                } else {
                    element.textContent = '********';
                }
                element.style.cursor = 'not-allowed';
                break;

            case 'redacted':
                // Black box style
                element.style.backgroundColor = '#000';
                element.style.color = '#000';
                element.style.borderRadius = '3px';
                element.style.userSelect = 'none';
                element.style.cursor = 'not-allowed';
                break;

            default:
                element.style.filter = 'blur(8px)';
        }

        element.classList.add('confidential-hidden');
    }

    /**
     * Show confidential data (restore original)
     */
    function showConfidentialData() {
        const elements = document.querySelectorAll('[data-confidential="true"]');

        elements.forEach(el => {
            const original = el.getAttribute('data-confidential-original');
            if (original) {
                el.textContent = original;
                el.style.filter = '';
                el.style.backgroundColor = '';
                el.style.color = '';
                el.style.userSelect = '';
                el.style.cursor = '';
                el.classList.remove('confidential-hidden');
            }
        });
    }

    /**
     * Create unlock modal
     */
    function createUnlockModal() {
        const modalHTML = `
            <div id="confidentialUnlockModal" class="modal fade" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-lock me-2"></i>Unlock Confidential Data
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Confidential Mode is Active</strong><br>
                                Financial amounts are hidden for security. Enter your admin password to view.
                            </div>
                            <form id="unlockForm">
                                <div class="mb-3">
                                    <label for="adminPassword" class="form-label">Admin Password</label>
                                    <input type="password" class="form-control form-control-lg" id="adminPassword"
                                           placeholder="Enter your password" required autofocus>
                                    <div class="invalid-feedback" id="unlockError"></div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="ConfidentialMode.unlock()">
                                <i class="fas fa-unlock me-2"></i>Unlock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Handle form submission
        document.getElementById('unlockForm').addEventListener('submit', (e) => {
            e.preventDefault();
            unlock();
        });
    }

    /**
     * Create lock/unlock button in navbar
     */
    function createLockButton() {
        const navbar = document.querySelector('.navbar-nav');
        if (!navbar) return;

        const buttonHTML = `
            <li class="nav-item" id="confidentialLockButton" style="display: none;">
                <a class="nav-link" href="javascript:void(0)" onclick="ConfidentialMode.toggleLock()"
                   title="Lock/Unlock Confidential Data">
                    <i class="fas fa-lock" id="lockIcon"></i>
                    <span class="d-none d-md-inline ms-1" id="lockText">Unlock</span>
                </a>
            </li>
        `;

        navbar.insertAdjacentHTML('beforeend', buttonHTML);
    }

    /**
     * Update lock button state
     */
    function updateLockButton(state) {
        const button = document.getElementById('confidentialLockButton');
        const icon = document.getElementById('lockIcon');
        const text = document.getElementById('lockText');

        if (!button || !icon || !text) return;

        button.style.display = 'block';

        if (state === 'lock') {
            icon.className = 'fas fa-lock-open';
            text.textContent = 'Lock';
            button.classList.add('text-success');
            button.classList.remove('text-warning');
        } else {
            icon.className = 'fas fa-lock';
            text.textContent = 'Unlock';
            button.classList.add('text-warning');
            button.classList.remove('text-success');
        }
    }

    /**
     * Hide lock button
     */
    function hideLockButton() {
        const button = document.getElementById('confidentialLockButton');
        if (button) button.style.display = 'none';
    }

    /**
     * Toggle lock/unlock
     */
    function toggleLock() {
        if (confidentialMode.isUnlocked) {
            lock();
        } else {
            showUnlockModal();
        }
    }

    /**
     * Show unlock modal
     */
    function showUnlockModal() {
        const modal = new bootstrap.Modal(document.getElementById('confidentialUnlockModal'));
        modal.show();

        // Focus password field
        setTimeout(() => {
            document.getElementById('adminPassword').focus();
        }, 500);
    }

    /**
     * Unlock confidential data
     */
    function unlock() {
        const password = document.getElementById('adminPassword').value;
        const errorDiv = document.getElementById('unlockError');
        const passwordInput = document.getElementById('adminPassword');

        if (!password) {
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = 'Password is required';
            return;
        }

        // Show loading
        const unlockBtn = document.querySelector('#confidentialUnlockModal .btn-primary');
        const originalText = unlockBtn.innerHTML;
        unlockBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Unlocking...';
        unlockBtn.disabled = true;

        fetch(CONFIG.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=unlock&password=' + encodeURIComponent(password),
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                confidentialMode.isUnlocked = true;
                showConfidentialData();
                updateLockButton('lock');

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('confidentialUnlockModal')).hide();

                // Clear password
                passwordInput.value = '';
                passwordInput.classList.remove('is-invalid');

                // Show success message
                showToast('Success', 'Confidential data unlocked', 'success');

            } else {
                passwordInput.classList.add('is-invalid');
                errorDiv.textContent = data.error || 'Unlock failed';
            }
        })
        .catch(error => {
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = 'Network error. Please try again.';
            console.error('Unlock error:', error);
        })
        .finally(() => {
            unlockBtn.innerHTML = originalText;
            unlockBtn.disabled = false;
        });
    }

    /**
     * Lock confidential data
     */
    function lock() {
        fetch(CONFIG.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=lock',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                confidentialMode.isUnlocked = false;
                hideConfidentialData();
                updateLockButton('unlock');
                showToast('Locked', 'Confidential data has been locked', 'info');
            }
        })
        .catch(error => {
            console.error('Lock error:', error);
        });
    }

    /**
     * Setup activity tracking for auto-lock
     */
    function setupActivityTracking() {
        const events = ['mousedown', 'keydown', 'scroll', 'touchstart'];

        events.forEach(event => {
            document.addEventListener(event, () => {
                confidentialMode.lastActivity = Date.now();
            }, true);
        });
    }

    /**
     * Check for auto-lock
     */
    function checkAutoLock() {
        if (!confidentialMode.enabled || !confidentialMode.isUnlocked) return;

        // Get auto-lock timeout from server-side setting
        fetch(CONFIG.apiUrl + '?action=get_settings')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.settings.auto_lock > 0) {
                const autoLockMs = data.settings.auto_lock * 1000;
                const timeSinceActivity = Date.now() - confidentialMode.lastActivity;

                if (timeSinceActivity >= autoLockMs) {
                    lock();
                }
            }
        });
    }

    /**
     * Show toast notification
     */
    function showToast(title, message, type = 'info') {
        // Create toast if doesn't exist
        if (!document.getElementById('confidentialToast')) {
            const toastHTML = `
                <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999">
                    <div id="confidentialToast" class="toast" role="alert">
                        <div class="toast-header bg-${type} text-white">
                            <strong class="me-auto">${title}</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', toastHTML);
        }

        const toast = new bootstrap.Toast(document.getElementById('confidentialToast'));
        toast.show();
    }

    /**
     * Public API
     */
    window.ConfidentialMode = {
        init: init,
        checkStatus: checkStatus,
        unlock: unlock,
        lock: lock,
        toggleLock: toggleLock,
        showUnlockModal: showUnlockModal
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
