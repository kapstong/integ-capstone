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

                const hasAmount = /[₱$€£¥]\s*[\d,]+\.?\d*/.test(text);

                if (hasAmount) {
                    const originalText = text;

                    const hiddenText = text
                        .replace(/₱\s*[\d,]+\.?\d*/g, '₱********')
                        .replace(/\$\s*[\d,]+\.?\d*/g, '$********')
                        .replace(/€\s*[\d,]+\.?\d*/g, '€********')
                        .replace(/£\s*[\d,]+\.?\d*/g, '£********')
                        .replace(/¥\s*[\d,]+\.?\d*/g, '¥********')
                        .replace(/PHP\s*[\d,]+\.?\d*/g, 'PHP ********');

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

        hiddenElements.forEach(item => {
            if (item.node && item.node.nodeValue) {
                item.node.nodeValue = item.original;
                item.element.removeAttribute('data-privacy-hidden');
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
            } else {
                // Not unlocked, hide amounts
                hideAmounts();
            }
        })
        .catch(error => {
            // On error, default to hiding amounts
            hideAmounts();
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
            document.getElementById('privacyPassword').focus();
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
     * Create BIG RED eye button
     */
    function createEyeButton() {
        const button = document.createElement('button');
        button.id = 'privacyEyeButton';
        button.style.cssText = `
            position: fixed !important;
            top: 100px !important;
            right: 20px !important;
            width: 80px !important;
            height: 80px !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important;
            color: white !important;
            border: 5px solid white !important;
            font-size: 2.5rem !important;
            cursor: pointer !important;
            box-shadow: 0 10px 40px rgba(220, 38, 38, 0.7) !important;
            z-index: 999999 !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        `;
        button.innerHTML = '<i class="fas fa-eye-slash" id="privacyEyeIcon"></i>';
        button.title = 'Click to Show Amounts (Email Verification Required)';

        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleAmounts();
        });

        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.15) rotate(5deg)';
            this.style.boxShadow = '0 15px 50px rgba(220, 38, 38, 0.9)';
        });

        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) rotate(0deg)';
            this.style.boxShadow = '0 10px 40px rgba(220, 38, 38, 0.7)';
        });

        document.body.appendChild(button);
        eyeButton = button;
    }

    /**
     * Update eye button appearance
     */
    function updateEyeButton() {
        const icon = document.getElementById('privacyEyeIcon');
        if (!icon || !eyeButton) return;

        if (isHidden) {
            icon.className = 'fas fa-eye-slash';
            eyeButton.style.background = 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)';
            eyeButton.title = 'Amounts Hidden - Click to Show (Email Verification Required)';
        } else {
            icon.className = 'fas fa-eye';
            eyeButton.style.background = 'linear-gradient(135deg, #16a34a 0%, #15803d 100%)';
            eyeButton.title = 'Amounts Visible - Click to Hide';
        }
    }

    /**
     * Initialize privacy mode
     */
    function init() {
        createPasswordModal();
        createEyeButton();

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
    }

    window.PrivacyMode = {
        hide: hideAmounts,
        show: showAmounts,
        toggle: toggleAmounts,
        isHidden: function() { return isHidden; }
    };

    init();

})();
