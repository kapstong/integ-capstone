/**
 * PRIVACY MODE - Hide amounts with asterisks + Eye button
 * AGGRESSIVE VERSION - LOGS EVERYTHING!
 */

console.log('🚀 PRIVACY MODE SCRIPT LOADED!!!');

(function() {
    'use strict';

    let isHidden = true;
    let eyeButton = null;

    /**
     * Hide all amounts - SUPER AGGRESSIVE
     */
    function hideAmounts() {
        console.log('🔒🔒🔒 HIDING AMOUNTS - STARTING NOW!');

        let hiddenCount = 0;

        // Method 1: Find ALL elements containing peso symbol or numbers
        const allElements = document.querySelectorAll('*');

        console.log(`Found ${allElements.length} elements to scan`);

        allElements.forEach(el => {
            // Skip if already processed
            if (el.hasAttribute('data-privacy-processed')) {
                return;
            }

            // Get direct text content only (not children)
            const nodes = Array.from(el.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);

            nodes.forEach(node => {
                const text = node.nodeValue;

                if (!text) return;

                // Check for ANY amount pattern - VERY AGGRESSIVE
                const hasAmount =
                    /₱/.test(text) || // Has peso sign
                    /\$/.test(text) || // Has dollar
                    /PHP/.test(text) || // Has PHP
                    /\d+\.\d{2}/.test(text); // Has decimal number

                if (hasAmount) {
                    console.log(`FOUND AMOUNT: "${text}"`);

                    const originalText = text;

                    // Replace ALL amounts with asterisks - MULTIPLE PATTERNS
                    let hiddenText = text
                        .replace(/₱\s*[\d,]+\.?\d*/g, '₱ ********')  // ₱0.00 or ₱123,456.78
                        .replace(/\$\s*[\d,]+\.?\d*/g, '$ ********')  // $123.45
                        .replace(/PHP\s*[\d,]+\.?\d*/g, 'PHP ********')  // PHP 1000
                        .replace(/€\s*[\d,]+\.?\d*/g, '€ ********')  // €100
                        .replace(/£\s*[\d,]+\.?\d*/g, '£ ********')  // £100
                        .replace(/¥\s*[\d,]+\.?\d*/g, '¥ ********');  // ¥100

                    if (hiddenText !== originalText) {
                        el.setAttribute('data-privacy-original', originalText);
                        el.setAttribute('data-privacy-processed', 'true');
                        node.nodeValue = hiddenText;
                        hiddenCount++;
                        console.log(`HIDDEN: "${originalText}" → "${hiddenText}"`);
                    }
                }
            });
        });

        isHidden = true;
        updateEyeButton();

        console.log(`✅✅✅ HIDDEN ${hiddenCount} AMOUNTS!`);

        if (hiddenCount === 0) {
            console.error('❌❌❌ NO AMOUNTS FOUND! CHECK IF PAGE HAS AMOUNTS!');
        }
    }

    /**
     * Show all amounts
     */
    function showAmounts() {
        console.log('👁️👁️👁️ SHOWING AMOUNTS!');

        let shownCount = 0;
        const elements = document.querySelectorAll('[data-privacy-processed]');

        console.log(`Found ${elements.length} hidden elements`);

        elements.forEach(el => {
            const original = el.getAttribute('data-privacy-original');
            if (original) {
                const nodes = Array.from(el.childNodes).filter(n => n.nodeType === Node.TEXT_NODE);

                nodes.forEach(node => {
                    if (node.nodeValue.includes('********')) {
                        console.log(`RESTORING: "${node.nodeValue}" → "${original}"`);
                        node.nodeValue = original;
                        shownCount++;
                    }
                });
            }
        });

        isHidden = false;
        updateEyeButton();

        console.log(`✅✅✅ SHOWN ${shownCount} AMOUNTS!`);
    }

    /**
     * Toggle
     */
    function toggleAmounts() {
        console.log('🔄 TOGGLING AMOUNTS!');

        if (isHidden) {
            showAmounts();
        } else {
            hideAmounts();
        }
    }

    /**
     * Create BIG VISIBLE eye button
     */
    function createEyeButton() {
        console.log('👁️ CREATING EYE BUTTON...');

        // Create FLOATING button - ALWAYS visible
        const button = document.createElement('button');
        button.id = 'privacyEyeButton';
        button.style.cssText = `
            position: fixed !important;
            top: 80px !important;
            right: 20px !important;
            width: 70px !important;
            height: 70px !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important;
            color: white !important;
            border: 4px solid white !important;
            font-size: 2rem !important;
            cursor: pointer !important;
            box-shadow: 0 8px 30px rgba(220, 38, 38, 0.6) !important;
            z-index: 99999 !important;
            transition: all 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        `;
        button.innerHTML = '<i class="fas fa-eye-slash" id="privacyEyeIcon"></i>';
        button.title = 'Click to Show/Hide Amounts';

        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('👁️ EYE BUTTON CLICKED!');
            toggleAmounts();
        });

        button.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.2) rotate(10deg)';
        });

        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) rotate(0deg)';
        });

        document.body.appendChild(button);
        eyeButton = button;

        console.log('✅ EYE BUTTON CREATED! Look for RED CIRCLE in top-right!');
    }

    /**
     * Update eye icon
     */
    function updateEyeButton() {
        const icon = document.getElementById('privacyEyeIcon');
        if (!icon) return;

        if (isHidden) {
            icon.className = 'fas fa-eye-slash';
            if (eyeButton) {
                eyeButton.title = 'Amounts Hidden - Click to Show';
                eyeButton.style.background = 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)';
            }
        } else {
            icon.className = 'fas fa-eye';
            if (eyeButton) {
                eyeButton.title = 'Amounts Visible - Click to Hide';
                eyeButton.style.background = 'linear-gradient(135deg, #16a34a 0%, #15803d 100%)';
            }
        }
    }

    /**
     * Initialize - IMMEDIATE!
     */
    function init() {
        console.log('🚀🚀🚀 PRIVACY MODE INITIALIZING NOW!');

        // Run immediately AND after page loads
        setTimeout(function() {
            console.log('🔄 Running hide amounts...');
            hideAmounts();
            createEyeButton();
        }, 100);

        // Run again after DOM fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                console.log('📄 DOM LOADED - Running again...');
                setTimeout(function() {
                    hideAmounts();
                    if (!eyeButton) createEyeButton();
                }, 500);
            });
        }

        // Watch for new content
        const observer = new MutationObserver(function(mutations) {
            if (isHidden) {
                console.log('🔄 Content changed - re-hiding amounts...');
                setTimeout(hideAmounts, 50);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('✅ PRIVACY MODE INITIALIZED!');
    }

    // Expose globally
    window.PrivacyMode = {
        hide: hideAmounts,
        show: showAmounts,
        toggle: toggleAmounts,
        isHidden: function() { return isHidden; }
    };

    // START IMMEDIATELY
    init();

})();

console.log('✅ PRIVACY MODE SCRIPT READY!');
