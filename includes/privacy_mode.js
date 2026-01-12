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
    let hiddenElements = []; // Store elements we've hidden

    /**
     * Hide all amounts with asterisks
     */
    function hideAmounts() {
        hiddenElements = [];
        let hiddenCount = 0;

        const allElements = document.querySelectorAll('*');

        allElements.forEach(el => {
            if (el.hasAttribute('data-privacy-hidden')) {
                return;
            }

            const textNodes = Array.from(el.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);

            textNodes.forEach(node => {
                const text = node.nodeValue;
                if (!text) return;

                // Enhanced regex to catch all amount patterns including:
                // - Currency symbols: ₱, $, €, £, ¥
                // - P prefix (without peso symbol)
                // - PHP prefix
                // - Negative amounts with minus sign
                // - Amounts in parentheses (accounting format for negatives)
                // - Plain numeric amounts (for edge cases)
                // - With or without decimal points and commas
                const hasAmount = /(?:[₱$€£¥]\s*-?[\d,]+\.?\d*)|(?:P\s*-?[\d,]+\.?\d*)|(?:PHP\s*-?[\d,]+\.?\d*)|(?:\(\s*[₱$€£¥P]?\s*[\d,]+\.?\d*\s*\))|(?:\b\d{1,3}(?:,\d{3})*(?:\.\d{2})?\b)/.test(text);

                if (hasAmount) {
                    const originalText = text;

                    const hiddenText = text
                        // Match ₱ with optional minus and numbers
                        .replace(/₱\s*-?[\d,]+\.?\d*/g, '₱*********')
                        // Match $ with optional minus and numbers
                        .replace(/\$\s*-?[\d,]+\.?\d*/g, '$*********')
                        // Match € with optional minus and numbers
                        .replace(/€\s*-?[\d,]+\.?\d*/g, '€*********')
                        // Match £ with optional minus and numbers
                        .replace(/£\s*-?[\d,]+\.?\d*/g, '£*********')
                        // Match ¥ with optional minus and numbers
                        .replace(/¥\s*-?[\d,]+\.?\d*/g, '¥*********')
                        // Match PHP with optional minus and numbers
                        .replace(/PHP\s*-?[\d,]+\.?\d*/g, 'PHP *********')
                        // Match P (without peso symbol) with optional minus and numbers - CRITICAL FIX
                        .replace(/P\s*-?[\d,]+\.?\d*/g, 'P*********')
                        // Match amounts in parentheses (accounting format)
                        .replace(/\(\s*([₱$€£¥P]?)\s*[\d,]+\.?\d*\s*\)/g, '($1********)')
                        // Match plain numeric amounts (fallback for edge cases)
                        .replace(/\b\d{1,3}(?:,\d{3})*(?:\.\d{2})?\b/g, '*********');

                    if (hiddenText !== originalText) {
                        hiddenElements.push({
                            node: node,
                            element: el,
                            original: originalText
                        });

                        el.setAttribute('data-privacy-hidden', 'true');
                        el.setAttribute('data-privacy-original', originalText);
                        node.nodeValue = hiddenText;
                        hiddenCount++;
                    }
                }
            });
        });

        isHidden = true;
        updateEyeButton();
    }

    /**
     * Show all amounts (restore original)
     */
    function showAmounts() {
        let restoredCount = 0;

        // First, restore elements from the hiddenElements array
        hiddenElements.forEach(item => {
            if (item.node && item.node.nodeValue) {
                item.node.nodeValue = item.original;
                item.element.removeAttribute('data-privacy-hidden');
                item.element.removeAttribute('data-privacy-original');
                restoredCount++;
            }
        });

        // Then, scan the entire DOM for any remaining elements with data-privacy-hidden
        // This handles dynamically loaded content that wasn't in the original hiddenElements array
        const remainingHiddenElements = document.querySelectorAll('[data-privacy-hidden]');
        remainingHiddenElements.forEach(el => {
            const originalText = el.getAttribute('data-privacy-original');
            if (originalText) {
                // Find text nodes within this element and restore them
                const textNodes = Array.from(el.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);
                textNodes.forEach(node => {
                    // Replace any asterisk patterns with the original text
                        // This is a fallback since we don't have the exact original for dynamically loaded content
                        // Detect any run of 3 or more asterisks (covers variants like 8 or 9 stars)
                        if (node.nodeValue && /\*{3,}/.test(node.nodeValue)) {
                            // Try to restore from data-privacy-original if available
                            const originalNodeText = el.getAttribute('data-privacy-original');
                            if (originalNodeText && /\*{3,}/.test(node.nodeValue)) {
                                node.nodeValue = originalNodeText;
                            }
                        }
                });
                el.removeAttribute('data-privacy-hidden');
                el.removeAttribute('data-privacy-original');
                restoredCount++;
            }
        });

        isHidden = false;
        updateEyeButton();
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

                // Show email address
                document.getElementById('userEmail').textContent = data.email;

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

        fetch(apiPath + '?action=check_status', {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unlocked) {
                // Code was already verified, show amounts immediately
                isHidden = false;
                updateEyeButton();
                // Since amounts were hidden by default, we need to show them now
                showAmounts();
            } else {
                // Not unlocked, keep amounts hidden (already hidden by default)
                // hideAmounts() was already called during init
            }
        })
        .catch(error => {
            // On error, keep amounts hidden (already hidden by default)
            // hideAmounts() was already called during init
        });
    }

    /**
     * Get API path based on current location
     */
    function getApiPath(filename) {
        const currentPath = window.location.pathname;
        if (currentPath.includes('/admin/')) {
            return '../api/' + filename;
        } else {
            return 'api/' + filename;
        }
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
            hideAmounts();
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
     * Initialize privacy mode
     */
    function init() {
        createPasswordModal();
        createEyeButton();

        // Hide amounts by default immediately
        setTimeout(function() {
            hideAmounts();

            // Then check if password was already entered in this session
            setTimeout(function() {
                checkSessionStatus();
            }, 100);
        }, 100);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    hideAmounts();
                    setTimeout(checkSessionStatus, 100);
                }, 100);
            });
        }

        const observer = new MutationObserver(function(mutations) {
            // Check if any new nodes were added
            let hasNewNodes = false;
            mutations.forEach(mutation => {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    hasNewNodes = true;
                }
            });

            if (hasNewNodes) {
                if (isHidden) {
                    // Hide any new amounts that were added
                    setTimeout(hideAmounts, 100);
                } else {
                    // If privacy mode is disabled, ensure any newly loaded content is visible
                    // This handles cases where content is loaded via AJAX after privacy mode was disabled
                    setTimeout(function() {
                        const hiddenElements = document.querySelectorAll('[data-privacy-hidden]');
                        hiddenElements.forEach(el => {
                            const originalText = el.getAttribute('data-privacy-original');
                            if (originalText) {
                                const textNodes = Array.from(el.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);
                                textNodes.forEach(node => {
                                    // Restore any masked text nodes that contain 3+ asterisks
                                    if (node.nodeValue && /\*{3,}/.test(node.nodeValue)) {
                                        node.nodeValue = originalText;
                                    }
                                });
                                el.removeAttribute('data-privacy-hidden');
                                el.removeAttribute('data-privacy-original');
                            }
                        });
                    }, 100);
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    window.PrivacyMode = {
        hide: hideAmounts,
        show: showAmounts,
        toggle: toggleAmounts,
        isHidden: function() { return isHidden; }
    };

    init();

})();
