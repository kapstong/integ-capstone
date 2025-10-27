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
     * Create password modal
     */
    function createPasswordModal() {
        const modalHTML = `
            <div id="privacyPasswordModal" class="modal fade" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-lock me-2"></i>Enter Admin Password
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Protected Information</strong><br>
                                Enter your admin password to view financial amounts.
                            </div>
                            <form id="privacyPasswordForm">
                                <div class="mb-3">
                                    <label for="privacyPassword" class="form-label">Your Password</label>
                                    <input type="password" class="form-control form-control-lg"
                                           id="privacyPassword" placeholder="Enter password" required autofocus>
                                    <div class="invalid-feedback" id="privacyPasswordError"></div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="privacyVerifyBtn">
                                <i class="fas fa-unlock me-2"></i>Show Amounts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        document.getElementById('privacyPasswordForm').addEventListener('submit', (e) => {
            e.preventDefault();
            verifyPasswordAndShow();
        });

        document.getElementById('privacyVerifyBtn').addEventListener('click', verifyPasswordAndShow);
    }

    /**
     * Verify password and show amounts
     */
    function verifyPasswordAndShow() {
        const passwordInput = document.getElementById('privacyPassword');
        const errorDiv = document.getElementById('privacyPasswordError');
        const verifyBtn = document.getElementById('privacyVerifyBtn');
        const password = passwordInput.value;

        if (!password) {
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = 'Password is required';
            return;
        }

        const originalBtnText = verifyBtn.innerHTML;
        verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
        verifyBtn.disabled = true;
        passwordInput.disabled = true;

        const apiPath = getApiPath('verify_password.php');

        fetch(apiPath, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'password=' + encodeURIComponent(password)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAmounts();

                const modal = bootstrap.Modal.getInstance(document.getElementById('privacyPasswordModal'));
                modal.hide();

                passwordInput.value = '';
                passwordInput.classList.remove('is-invalid');

            } else {
                passwordInput.classList.add('is-invalid');
                errorDiv.textContent = data.error || 'Incorrect password';
            }
        })
        .catch(error => {
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = 'Network error. Please try again.';
        })
        .finally(() => {
            verifyBtn.innerHTML = originalBtnText;
            verifyBtn.disabled = false;
            passwordInput.disabled = false;
            passwordInput.focus();
        });
    }

    /**
     * Check if privacy mode is already unlocked in session
     */
    function checkSessionStatus() {
        const apiPath = getApiPath('verify_password.php');

        fetch(apiPath, {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.unlocked) {
                // Password was already entered, show amounts immediately
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
            showPasswordModal();
        } else {
            hideAmounts();
        }
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
        button.title = 'Click to Show Amounts (Password Required)';

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
            eyeButton.title = 'Amounts Hidden - Click to Show (Password Required)';
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
