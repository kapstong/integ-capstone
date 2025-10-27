/**
 * SIMPLE Privacy Mode - Hide amounts with asterisks
 * NO DATABASE, NO API, NO SETUP - JUST WORKS!
 */

(function() {
    'use strict';

    let isHidden = true; // Start with amounts hidden
    let eyeButton = null;

    /**
     * Hide all amounts - replace with asterisks
     */
    function hideAmounts() {
        console.log('🔒 Hiding all amounts with asterisks...');

        let hiddenCount = 0;

        // Find ALL text in the document
        const walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );

        const nodesToProcess = [];
        let node;

        while (node = walker.nextNode()) {
            const text = node.nodeValue;

            // Check if text contains currency amounts
            if (text && (
                text.match(/[₱$€£¥]\s*[\d,]+(\.\d{1,2})?/) ||
                text.match(/[\d,]+(\.\d{1,2})?\s*[₱$€£¥]/) ||
                text.match(/PHP\s*[\d,]+(\.\d{1,2})?/)
            )) {
                nodesToProcess.push(node);
            }
        }

        // Process nodes
        nodesToProcess.forEach(node => {
            const parent = node.parentElement;

            if (!parent || parent.hasAttribute('data-privacy-processed')) {
                return;
            }

            const originalText = node.nodeValue;

            // Replace amounts with asterisks
            const hiddenText = originalText.replace(
                /([₱$€£¥])\s*[\d,]+(\.\d{1,2})?/g,
                '$1 ********'
            ).replace(
                /[\d,]+(\.\d{1,2})?\s*([₱$€£¥])/g,
                '******** $2'
            ).replace(
                /PHP\s*[\d,]+(\.\d{1,2})?/g,
                'PHP ********'
            );

            if (hiddenText !== originalText) {
                parent.setAttribute('data-privacy-original', originalText);
                parent.setAttribute('data-privacy-processed', 'true');
                node.nodeValue = hiddenText;
                hiddenCount++;
            }
        });

        isHidden = true;
        updateEyeButton();
        console.log(`🔒 Hidden ${hiddenCount} amounts`);
    }

    /**
     * Show all amounts - restore original values
     */
    function showAmounts() {
        console.log('👁️ Showing all amounts...');

        let shownCount = 0;
        const elements = document.querySelectorAll('[data-privacy-processed]');

        elements.forEach(el => {
            const original = el.getAttribute('data-privacy-original');
            if (original) {
                // Find the text node and restore it
                const walker = document.createTreeWalker(
                    el,
                    NodeFilter.SHOW_TEXT,
                    null,
                    false
                );

                let textNode;
                while (textNode = walker.nextNode()) {
                    if (textNode.nodeValue.includes('********')) {
                        textNode.nodeValue = original;
                        shownCount++;
                        break;
                    }
                }
            }
        });

        isHidden = false;
        updateEyeButton();
        console.log(`👁️ Shown ${shownCount} amounts`);
    }

    /**
     * Toggle between hide and show
     */
    function toggleAmounts() {
        if (isHidden) {
            showAmounts();
        } else {
            hideAmounts();
        }
    }

    /**
     * Create eye button in navbar
     */
    function createEyeButton() {
        console.log('👁️ Creating eye button...');

        // Find navbar
        const navbar = document.querySelector('.navbar-nav');
        if (!navbar) {
            console.log('⚠️ Navbar not found, creating floating button');
            createFloatingButton();
            return;
        }

        // Create button
        const li = document.createElement('li');
        li.className = 'nav-item';
        li.innerHTML = `
            <a class="nav-link" href="javascript:void(0)" id="privacyEyeButton"
               style="font-size: 1.5rem; padding: 0.5rem 1rem;"
               title="Toggle Amount Visibility">
                <i class="fas fa-eye-slash" id="privacyEyeIcon"></i>
            </a>
        `;

        navbar.appendChild(li);
        eyeButton = document.getElementById('privacyEyeButton');

        eyeButton.addEventListener('click', toggleAmounts);

        console.log('✅ Eye button created in navbar');
    }

    /**
     * Create floating eye button (fallback)
     */
    function createFloatingButton() {
        const button = document.createElement('button');
        button.id = 'privacyEyeButton';
        button.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1b2f73 0%, #2342a6 100%);
            color: white;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 9999;
            transition: all 0.3s ease;
        `;
        button.innerHTML = '<i class="fas fa-eye-slash" id="privacyEyeIcon"></i>';
        button.title = 'Toggle Amount Visibility';

        button.addEventListener('click', toggleAmounts);
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });

        document.body.appendChild(button);
        eyeButton = button;

        console.log('✅ Floating eye button created');
    }

    /**
     * Update eye button icon
     */
    function updateEyeButton() {
        const icon = document.getElementById('privacyEyeIcon');
        if (!icon) return;

        if (isHidden) {
            icon.className = 'fas fa-eye-slash';
            if (eyeButton) eyeButton.title = 'Show Amounts';
        } else {
            icon.className = 'fas fa-eye';
            if (eyeButton) eyeButton.title = 'Hide Amounts';
        }
    }

    /**
     * Initialize privacy mode
     */
    function init() {
        console.log('🚀 Privacy Mode: Initializing...');

        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function() {
                    hideAmounts();
                    createEyeButton();
                    console.log('✅ Privacy Mode: ACTIVE - Amounts hidden by default');
                }, 500);
            });
        } else {
            setTimeout(function() {
                hideAmounts();
                createEyeButton();
                console.log('✅ Privacy Mode: ACTIVE - Amounts hidden by default');
            }, 500);
        }

        // Re-hide amounts when navigating or AJAX updates
        const observer = new MutationObserver(function(mutations) {
            if (isHidden) {
                // Re-scan for new amounts
                setTimeout(hideAmounts, 100);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Expose API
    window.PrivacyMode = {
        hide: hideAmounts,
        show: showAmounts,
        toggle: toggleAmounts,
        isHidden: function() { return isHidden; }
    };

    // Auto-init
    init();

})();
