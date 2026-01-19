/**
 * Custom Alert Modal System
 * Replaces browser alert() with styled modal/card notifications
 * Syncs with ATIERA's blue and gold color palette
 */

(function() {
    // Create alert container if it doesn't exist
    function initializeAlertContainer() {
        if (!document.getElementById('alert-container')) {
            const container = document.createElement('div');
            container.id = 'alert-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 500px;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        return document.getElementById('alert-container');
    }

    /**
     * Show a custom alert modal
     * @param {string} message - The alert message
     * @param {string} type - Alert type: 'info', 'success', 'warning', 'danger'
     * @param {number} duration - Auto-close duration in ms (0 = no auto-close)
     */
    window.showAlert = function(message, type = 'info', duration = 5000) {
        const container = initializeAlertContainer();

        // Create the alert card
        const alertCard = document.createElement('div');
        alertCard.className = `custom-alert alert-${type}`;
        alertCard.style.cssText = `
            pointer-events: auto;
        `;

        // Map types to colors and icons
        const typeConfig = {
            success: {
                bgColor: 'var(--atiera-success)',
                lightBg: 'var(--atiera-success-light)',
                borderColor: 'var(--atiera-success)',
                icon: '✓'
            },
            danger: {
                bgColor: 'var(--atiera-danger)',
                lightBg: 'var(--atiera-danger-light)',
                borderColor: 'var(--atiera-danger)',
                icon: '⚠'
            },
            warning: {
                bgColor: 'var(--atiera-warning)',
                lightBg: 'var(--atiera-warning-light)',
                borderColor: 'var(--atiera-warning)',
                icon: '!'
            },
            info: {
                bgColor: 'var(--atiera-info)',
                lightBg: 'var(--atiera-info-light)',
                borderColor: 'var(--atiera-info)',
                icon: 'ℹ'
            }
        };

        const config = typeConfig[type] || typeConfig.info;

        alertCard.innerHTML = `
            <div style="
                background: ${config.lightBg};
                border-left: 4px solid ${config.bgColor};
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: flex-start;
                gap: 12px;
                animation: slideInRight 0.3s ease-out;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            ">
                <div style="
                    min-width: 32px;
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    background: ${config.bgColor};
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 18px;
                    flex-shrink: 0;
                ">
                    ${config.icon}
                </div>
                <div style="
                    flex: 1;
                    color: #1e293b;
                    font-size: 14px;
                    line-height: 1.5;
                    word-wrap: break-word;
                ">
                    ${escapeHtml(message)}
                </div>
                <button class="alert-close-btn" type="button" style="
                    background: none;
                    border: none;
                    color: #94a3b8;
                    font-size: 20px;
                    cursor: pointer;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    transition: color 0.2s ease;
                ">
                    ×
                </button>
            </div>
        `;

        // Add styles for animation if not already added
        if (!document.getElementById('alert-styles')) {
            const style = document.createElement('style');
            style.id = 'alert-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        opacity: 0;
                        transform: translateX(100px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }

                @keyframes slideOutRight {
                    from {
                        opacity: 1;
                        transform: translateX(0);
                    }
                    to {
                        opacity: 0;
                        transform: translateX(100px);
                    }
                }

                .alert-close-btn:hover {
                    color: #1e293b !important;
                }

                .custom-alert {
                    animation-duration: 0.3s;
                }

                .custom-alert.removing {
                    animation: slideOutRight 0.3s ease-out forwards;
                }
            `;
            document.head.appendChild(style);
        }

        // Remove alert
        function removeAlert() {
            alertCard.classList.add('removing');
            setTimeout(() => {
                alertCard.remove();
            }, 300);
        }

        // Close button handler
        const closeBtn = alertCard.querySelector('.alert-close-btn');
        closeBtn.addEventListener('click', removeAlert);
        closeBtn.addEventListener('mouseover', function(e) {
            e.target.style.color = '#1e293b';
        });
        closeBtn.addEventListener('mouseout', function(e) {
            e.target.style.color = '#94a3b8';
        });

        // Add to container
        container.appendChild(alertCard);

        // Auto-close if duration is specified
        if (duration > 0) {
            setTimeout(removeAlert, duration);
        }

        return alertCard;
    };

    /**
     * Show a confirmation dialog modal
     * @param {string} title - Dialog title
     * @param {string} message - Dialog message
     * @param {function} onConfirm - Callback when confirmed
     * @param {function} onCancel - Callback when cancelled
     */
    window.showConfirmDialog = function(title, message, onConfirm, onCancel) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 28, 73, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        `;

        // Create modal
        const modal = document.createElement('div');
        modal.style.cssText = `
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(15, 28, 73, 0.2);
            max-width: 400px;
            width: 90%;
            padding: 0;
            animation: slideUp 0.3s ease-out;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
        `;

        modal.innerHTML = `
            <div style="
                padding: 24px;
                border-bottom: 1px solid #e2e8f0;
            ">
                <h3 style="
                    margin: 0;
                    color: #0f1c49;
                    font-size: 18px;
                    font-weight: 600;
                ">${escapeHtml(title)}</h3>
            </div>
            <div style="
                padding: 24px;
                color: #1e293b;
                font-size: 14px;
                line-height: 1.6;
            ">
                ${escapeHtml(message)}
            </div>
            <div style="
                padding: 16px 24px;
                display: flex;
                gap: 12px;
                justify-content: flex-end;
                border-top: 1px solid #e2e8f0;
                background: #f8fafc;
                border-radius: 0 0 12px 12px;
            ">
                <button class="cancel-btn" type="button" style="
                    padding: 8px 16px;
                    border: 1px solid #cbd5e1;
                    background: white;
                    color: #1e293b;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                ">Cancel</button>
                <button class="confirm-btn" type="button" style="
                    padding: 8px 16px;
                    border: none;
                    background: #1b2f73;
                    color: white;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.2s ease;
                ">Confirm</button>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Add styles for confirmation dialog
        if (!document.getElementById('confirm-dialog-styles')) {
            const style = document.createElement('style');
            style.id = 'confirm-dialog-styles';
            style.textContent = `
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                    }
                    to {
                        opacity: 1;
                    }
                }

                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .cancel-btn:hover {
                    border-color: #94a3b8 !important;
                    background: #f1f5f9 !important;
                }

                .confirm-btn:hover {
                    background: #15265e !important;
                    box-shadow: 0 4px 12px rgba(27, 47, 115, 0.3) !important;
                }

                .confirm-btn:active {
                    background: #0f1c49 !important;
                }
            `;
            document.head.appendChild(style);
        }

        // Event handlers
        const confirmBtn = modal.querySelector('.confirm-btn');
        const cancelBtn = modal.querySelector('.cancel-btn');

        function closeDialog() {
            overlay.style.animation = 'fadeOut 0.2s ease-out forwards';
            setTimeout(() => {
                overlay.remove();
            }, 200);
        }

        confirmBtn.addEventListener('click', () => {
            closeDialog();
            if (onConfirm) onConfirm();
        });

        cancelBtn.addEventListener('click', () => {
            closeDialog();
            if (onCancel) onCancel();
        });

        // Close on overlay click
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeDialog();
                if (onCancel) onCancel();
            }
        });

        // Close on Escape key
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escapeHandler);
                closeDialog();
                if (onCancel) onCancel();
            }
        };
        document.addEventListener('keydown', escapeHandler);
    };

    /**
     * Replace all browser alert() calls
     */
    window.alert = function(message) {
        showAlert(message, 'info', 0);
    };

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
})();
