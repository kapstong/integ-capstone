/**
 * PRIVACY MODE - Password-Protected Amount Visibility
 * - Hides all amounts with asterisks by default
 * - Requires admin password to view amounts
 * - Big red eye button to toggle
 */

(function() {
    'use strict';

    let isHidden = true;
    let eyeButton = null;
    const STORAGE_KEY = 'privacyModeVisible';

    const AMOUNT_REGEX = /(?:[₱$€£¥]\s*-?[\d,]+\.?\d*)|(?:ƒ,ñ\s*-?[\d,]+\.?\d*)|(?:PHP\s*-?[\d,]+\.?\d*)|(?:P\s*-?[\d,]+\.?\d*)|(?:\(\s*(?:[₱$€£¥P]|ƒ,ñ)?\s*-?[\d,]+\.?\d*\s*\))/g;
    const MASKED_CLASS = 'privacy-mask';
    const originalTextMap = new WeakMap();

    /**
     * Hide all amounts with asterisks
     */
    function hideAmounts(force = false) {
        // If user unlocked and no force flag, skip re-hiding newly loaded data
        if (!force && isHidden === false) {
            return;
        }

        ensureMaskStyles();

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) {
            const node = walker.currentNode;
            if (!node || !node.nodeValue || shouldSkipNode(node)) {
                continue;
            }

            maskTextNode(node);
        }

        isHidden = true;
        updateEyeButton();
        persistVisibility();
    }

    /**
     * Show all amounts (restore original)
     */
    function showAmounts() {
        let restoredCount = 0;

        const maskedNodes = document.querySelectorAll('.' + MASKED_CLASS);
        maskedNodes.forEach(span => {
            const original = originalTextMap.get(span);
            span.classList.remove(MASKED_CLASS);
            span.style.removeProperty('--privacy-mask-color');
            span.style.removeProperty('position');
            span.style.removeProperty('display');
            span.style.removeProperty('color');
            span.style.removeProperty('white-space');
            const textNode = document.createTextNode(original || span.textContent || '');
            span.replaceWith(textNode);
            originalTextMap.delete(span);
            restoredCount++;
        });

        isHidden = false;
        updateEyeButton();
        persistVisibility();
    }

    /**
     * Create verification code modal
     */
    function createPasswordModal() {
        const modalHTML = `
            <div id="privacyPasswordModal" class="modal fade" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-envelope me-2"></i>Email Verification
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Protected Information</strong><br>
                                A 6-digit verification code will be sent to your email address.
                            </div>

                            <div id="emailSendSection">
                                <p class="text-muted mb-3">
                                    Click the button below to receive your verification code via email.
                                </p>
                                <button type="button" class="btn btn-primary btn-lg w-100" id="sendCodeBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Send Verification Code
                                </button>
                            </div>

                            <div id="codeVerifySection" style="display: none;">
                                <div class="alert alert-success mb-3" id="emailSentAlert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Code sent to <strong id="userEmail"></strong>
                                    <div class="mt-2 small">
                                        Code expires in: <strong id="codeTimer">5:00</strong>
                                    </div>
                                </div>

                                <form id="privacyCodeForm">
                                    <div class="mb-3">
                                        <label for="privacyCode" class="form-label">Enter 6-Digit Code</label>
                                        <input type="text" class="form-control form-control-lg text-center"
                                               id="privacyCode" placeholder="000000" maxlength="6"
                                               pattern="[0-9]{6}" required autofocus
                                               style="font-size: 24px; letter-spacing: 8px;">
                                        <div class="invalid-feedback" id="privacyCodeError"></div>
                                    </div>
                                </form>

                                <button type="button" class="btn btn-link btn-sm text-muted w-100" id="resendCodeBtn">
                                    Didn't receive the code? Resend
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" id="privacyVerifyBtn" style="display: none;">
                                <i class="fas fa-unlock me-2"></i>Verify & Show Amounts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Event listeners
        document.getElementById('sendCodeBtn').addEventListener('click', sendVerificationCode);
        document.getElementById('resendCodeBtn').addEventListener('click', sendVerificationCode);
        document.getElementById('privacyCodeForm').addEventListener('submit', (e) => {
            e.preventDefault();
            verifyCodeAndShow();
        });
        document.getElementById('privacyVerifyBtn').addEventListener('click', verifyCodeAndShow);

        // Auto-verify when 6 digits entered
        document.getElementById('privacyCode').addEventListener('input', (e) => {
            const code = e.target.value;
            if (code.length === 6 && /^\d{6}$/.test(code)) {
                verifyCodeAndShow();
            }
        });
    }

    /**
     * Send verification code to email
     */
    let codeTimerInterval = null;

    function sendVerificationCode() {
        const sendBtn = document.getElementById('sendCodeBtn');
        const originalText = sendBtn.innerHTML;

        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
        sendBtn.disabled = true;

        const apiPath = getApiPath('privacy_code.php');

        fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=send_code'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide send section, show verify section
                document.getElementById('emailSendSection').style.display = 'none';
                document.getElementById('codeVerifySection').style.display = 'block';
                document.getElementById('privacyVerifyBtn').style.display = 'block';

                // Show masked email address
                const maskedEmail = data.masked_email || maskEmail(data.email || '');
                document.getElementById('userEmail').textContent = maskedEmail;

                // Start countdown timer
                startCodeTimer();

                // Focus on code input
                setTimeout(() => {
                    document.getElementById('privacyCode').focus();
                }, 100);

            } else {
                alert('Error: ' + (data.error || 'Failed to send code'));
                sendBtn.innerHTML = originalText;
                sendBtn.disabled = false;
            }
        })
        .catch(error => {
            alert('Network error. Please try again.');
            sendBtn.innerHTML = originalText;
            sendBtn.disabled = false;
        });
    }

    /**
     * Start countdown timer for code expiration
     */
    function startCodeTimer() {
        let secondsLeft = 300; // 5 minutes
        const timerEl = document.getElementById('codeTimer');

        if (codeTimerInterval) {
            clearInterval(codeTimerInterval);
        }

        codeTimerInterval = setInterval(() => {
            secondsLeft--;

            const minutes = Math.floor(secondsLeft / 60);
            const seconds = secondsLeft % 60;
            timerEl.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

            if (secondsLeft <= 0) {
                clearInterval(codeTimerInterval);
                timerEl.textContent = 'EXPIRED';
                timerEl.classList.add('text-danger');
            }
        }, 1000);
    }

    /**
     * Verify code and show amounts
     */
    function verifyCodeAndShow() {
        const codeInput = document.getElementById('privacyCode');
        const errorDiv = document.getElementById('privacyCodeError');
        const verifyBtn = document.getElementById('privacyVerifyBtn');
        const code = codeInput.value;

        if (!code || code.length !== 6) {
            codeInput.classList.add('is-invalid');
            errorDiv.textContent = 'Please enter a 6-digit code';
            return;
        }

        const originalBtnText = verifyBtn.innerHTML;
        verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
        verifyBtn.disabled = true;
        codeInput.disabled = true;

        const apiPath = getApiPath('privacy_code.php');

        fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=verify_code&code=' + encodeURIComponent(code)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear timer
                if (codeTimerInterval) {
                    clearInterval(codeTimerInterval);
                }

                showAmounts();

                const modal = bootstrap.Modal.getInstance(document.getElementById('privacyPasswordModal'));
                modal.hide();

                codeInput.value = '';
                codeInput.classList.remove('is-invalid');

            } else {
                codeInput.classList.add('is-invalid');
                errorDiv.textContent = data.error || 'Incorrect code';

                verifyBtn.innerHTML = originalBtnText;
                verifyBtn.disabled = false;
                codeInput.disabled = false;
                codeInput.focus();
            }
        })
        .catch(error => {
            codeInput.classList.add('is-invalid');
            errorDiv.textContent = 'Network error. Please try again.';

            verifyBtn.innerHTML = originalBtnText;
            verifyBtn.disabled = false;
            codeInput.disabled = false;
            codeInput.focus();
        });
    }

    /**
     * Check if privacy mode is already unlocked in session
     */
    function checkSessionStatus() {
        const apiPath = getApiPath('privacy_code.php');
        const storedVisibility = getStoredVisibility();

        fetch(apiPath + '?action=check_status', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unlocked) {
                if (storedVisibility === '0') {
                    hideAmounts(true);
                } else {
                    // Code was already verified, show amounts immediately
                    showAmounts();
                }
            } else {
                // Not unlocked, hide amounts and clear stored preference
                hideAmounts(true);
                if (storedVisibility === '1') {
                    setStoredVisibility('0');
                }
            }
        })
        .catch(error => {
            // On error, default to hiding amounts
            hideAmounts(true);
        });
    }

    /**
     * Get API path based on current location
     */
    function getApiPath(filename) {
        const currentPath = window.location.pathname;
        const parts = currentPath.split('/').filter(function(part) { return part; });
        const adminIndex = parts.indexOf('admin');
        let baseParts = [];

        if (adminIndex !== -1) {
            baseParts = parts.slice(0, adminIndex);
        } else if (parts.length > 0 && parts[parts.length - 1].indexOf('.') !== -1) {
            baseParts = parts.slice(0, -1);
        } else {
            baseParts = parts;
        }

        const basePath = '/' + (baseParts.length ? baseParts.join('/') + '/' : '');
        return basePath + 'api/' + filename;
    }

    /**
     * Show password modal
     */
    function showPasswordModal() {
        const modal = new bootstrap.Modal(document.getElementById('privacyPasswordModal'));
        modal.show();

        document.getElementById('privacyPasswordModal').addEventListener('shown.bs.modal', function() {
            document.getElementById('privacyCode').focus();
        });
    }

    /**
     * Toggle amounts visibility
     */
    function toggleAmounts() {
        if (isHidden) {
            // Check if already verified in this session
            checkIfUnlockedThenToggle();
        } else {
            hideAmounts(true);
        }
    }

    /**
     * Check if privacy mode is unlocked, then toggle or show modal
     */
    function checkIfUnlockedThenToggle() {
        const apiPath = getApiPath('privacy_code.php');

        fetch(apiPath + '?action=check_status', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unlocked) {
                // Already verified in this session, just show amounts
                showAmounts();
            } else {
                // Not verified, show modal to enter code
                showPasswordModal();
            }
        })
        .catch(error => {
            // On error, show modal
            showPasswordModal();
        });
    }

    /**
     * Create simple eye icon button in navbar
     */
    function createEyeButton() {
        // Wait a bit for DOM to be fully ready
        setTimeout(function() {
            const button = document.createElement('button');
            button.id = 'privacyEyeButton';
            button.className = 'btn btn-link me-3';
            button.style.cssText = `
                color: #64748b !important;
                padding: 0.5rem !important;
                border: none !important;
                background: none !important;
                transition: color 0.2s ease !important;
            `;
            button.innerHTML = `<i class="fas fa-eye fa-lg" id="privacyEyeIcon"></i>`;
            button.title = 'Toggle Privacy Mode - Show/Hide Amounts';

            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleAmounts();
            });

            button.addEventListener('mouseenter', function() {
                this.style.color = '#1e2936 !important';
            });

            button.addEventListener('mouseleave', function() {
                this.style.color = '#64748b !important';
            });

            // Find the top navbar container with notification bell and user dropdown
            // Look for the user dropdown first, then get its parent container
            const userDropdown = document.querySelector('#userDropdown');

            if (userDropdown && userDropdown.parentElement) {
                // Get the parent container (the d-flex div)
                const navbarContainer = userDropdown.parentElement.parentElement;
                // Insert button before the dropdown (between notification bell and user dropdown)
                navbarContainer.insertBefore(button, userDropdown.parentElement);
            }

            eyeButton = button;
            updateEyeButton();
        }, 300); // Wait 300ms for DOM to be ready
    }

    /**
     * Update eye button appearance
     */
    function updateEyeButton() {
        const icon = document.getElementById('privacyEyeIcon');
        if (!icon || !eyeButton) return;

        if (isHidden) {
            icon.className = 'fas fa-eye-slash';
            eyeButton.title = 'Amounts Hidden - Click to Show (Email Verification Required)';
        } else {
            icon.className = 'fas fa-eye';
            eyeButton.title = 'Amounts Visible - Click to Hide';
        }
    }

    /**
     * Mask email address for display
     */
    function maskEmail(email) {
        if (!email || email.indexOf('@') === -1) {
            return email;
        }

        const parts = email.split('@');
        const local = parts[0];
        const domain = parts[1];
        const localLen = local.length;

        let maskedLocal = '';
        if (localLen <= 2) {
            maskedLocal = '*'.repeat(localLen);
        } else if (localLen <= 4) {
            maskedLocal = local.slice(0, 1) + '*'.repeat(Math.max(0, localLen - 2)) + local.slice(-1);
        } else {
            maskedLocal = local.slice(0, 2) + '*'.repeat(localLen - 4) + local.slice(-2);
        }

        return maskedLocal + '@' + domain;
    }

    /**
     * Initialize privacy mode
     */
    function init() {
        createPasswordModal();
        createEyeButton();

        const storedVisibility = getStoredVisibility();
        if (storedVisibility === '0') {
            hideAmounts(true);
        }

        // Check if password was already entered in this session
        setTimeout(function() {
            checkSessionStatus();
        }, 200);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(checkSessionStatus, 500);
            });
        }

        const observer = new MutationObserver(function() {
            if (isHidden) {
                setTimeout(hideAmounts, 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        window.addEventListener('storage', function(event) {
            if (event.key !== STORAGE_KEY) {
                return;
            }
            if (event.newValue === '1') {
                showAmounts();
            } else if (event.newValue === '0') {
                hideAmounts(true);
            }
        });
    }

    window.PrivacyMode = {
        hide: hideAmounts,
        show: showAmounts,
        toggle: toggleAmounts,
        isHidden: function() { return isHidden; }
    };

    function persistVisibility() {
        setStoredVisibility(isHidden ? '0' : '1');
    }

    function setStoredVisibility(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (error) {
            // Ignore storage errors (private mode, disabled storage)
        }
    }

    function getStoredVisibility() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (error) {
            return null;
        }
    }

    function shouldSkipNode(node) {
        const parent = node.parentElement;
        if (!parent) return true;
        const tag = parent.tagName;
        if (!tag) return true;
        if (parent.classList.contains(MASKED_CLASS)) return true;
        const blockedTags = ['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT'];
        if (blockedTags.includes(tag)) return true;
        return false;
    }

    function maskTextNode(node) {
        const text = node.nodeValue;
        if (!text) return;
        AMOUNT_REGEX.lastIndex = 0;

        let match;
        let lastIndex = 0;
        let masked = false;
        const fragment = document.createDocumentFragment();

        while ((match = AMOUNT_REGEX.exec(text)) !== null) {
            masked = true;
            const preceding = text.slice(lastIndex, match.index);
            if (preceding) {
                fragment.appendChild(document.createTextNode(preceding));
            }

            const span = document.createElement('span');
            span.className = MASKED_CLASS;
            originalTextMap.set(span, match[0]);
            span.textContent = formatMaskedAmount(match[0]);
            const parent = node.parentElement;
            if (parent) {
                const color = window.getComputedStyle(parent).color;
                span.style.setProperty('--privacy-mask-color', color);
            }
            fragment.appendChild(span);

            lastIndex = match.index + match[0].length;
        }

        if (!masked) return;

        const trailing = text.slice(lastIndex);
        if (trailing) {
            fragment.appendChild(document.createTextNode(trailing));
        }

        node.parentNode.replaceChild(fragment, node);
        ensureMaskStyles();
    }

    function formatMaskedAmount(amount) {
        const leading = amount.match(/^\s*/)[0];
        const trailing = amount.match(/\s*$/)[0];
        let core = amount.trim();

        let prefix = '';
        let suffix = '';

        if (core.startsWith('(') && core.endsWith(')')) {
            prefix = '(';
            suffix = ')';
            core = core.slice(1, -1).trim();
        }

        let masked = '*********';
        if (/^PHP/i.test(core)) {
            masked = 'PHP *********';
        } else if (/^P\\b/.test(core)) {
            masked = 'P*********';
        } else if (core.startsWith('ƒ,ñ')) {
            masked = 'ƒ,ñ*********';
        } else {
            const symbol = core.charAt(0);
            if ('₱$€£¥'.includes(symbol)) {
                masked = symbol + '*********';
            }
        }

        return leading + prefix + masked + suffix + trailing;
    }

    function ensureMaskStyles() {
        if (document.getElementById('privacy-mask-styles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'privacy-mask-styles';
        style.textContent = `
            .${MASKED_CLASS} {
                white-space: pre;
                color: var(--privacy-mask-color, #1f2937);
            }
        `;
        document.head.appendChild(style);
    }

    init();

})();
