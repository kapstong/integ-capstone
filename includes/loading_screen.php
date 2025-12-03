<?php
/**
 * ATIERA Financial Management System - Loading Screen Component
 * Shows a branded loading screen during page navigation
 */
?>
<style>
/* Loading Screen Styles */
.loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    z-index: 10000;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
    backdrop-filter: blur(2px);
}

.loading-screen.show {
    display: block;
    opacity: 1;
}

.loading-screen-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    max-width: 400px;
    padding: 3rem;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(30, 41, 54, 0.15);
    border: 1px solid rgba(30, 41, 54, 0.1);
}

.loading-logo {
    width: 120px;
    height: auto;
    margin-bottom: 1.5rem;
    opacity: 0.9;
}

.loading-text {
    color: #1e2936;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    letter-spacing: -0.02em;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(30, 41, 54, 0.1);
    border-left: 4px solid #1e2936;
    border-radius: 50%;
    animation: spin 1.2s linear infinite;
    margin: 0 auto;
    margin-bottom: 1.5rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-progress {
    width: 100%;
    height: 4px;
    background: rgba(30, 41, 54, 0.1);
    border-radius: 2px;
    overflow: hidden;
    margin-top: 1rem;
}

.loading-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #1e2936 0%, #2c3e50 100%);
    border-radius: 2px;
    width: 0%;
    animation: progress 2s ease-in-out infinite;
}

@keyframes progress {
    0% { width: 0%; }
    50% { width: 70%; }
    100% { width: 100%; }
}

.loading-subtitle {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 500;
    margin-top: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .loading-screen-content {
        padding: 2rem;
        max-width: 90%;
    }

    .loading-text {
        font-size: 1.3rem;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
    }
}
</style>

<!-- Loading Screen HTML -->
<div id="loading-screen" class="loading-screen">
    <div class="loading-screen-content">
        <img src="../logo.png" alt="ATIERA" class="loading-logo" onerror="this.src='../logo2.png'">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading ATIERA</div>
        <div class="loading-subtitle">Securing your financial data...</div>
        <div class="loading-progress">
            <div class="loading-progress-bar"></div>
        </div>
    </div>
</div>

<script>
/**
 * Loading Screen Controller
 */
(function() {
    'use strict';

    const loadingScreen = document.getElementById('loading-screen');
    let hideTimeout = null;

    /**
     * Show loading screen
     */
    function showLoading() {
        if (hideTimeout) {
            clearTimeout(hideTimeout);
        }
        loadingScreen.classList.add('show');

        // Auto-hide after 10 seconds as fallback
        setTimeout(() => {
            hideLoading();
        }, 10000);
    }

    /**
     * Hide loading screen
     */
    function hideLoading() {
        loadingScreen.classList.remove('show');
        hideTimeout = setTimeout(() => {
            loadingScreen.style.display = 'none';
        }, 300);
    }

    /**
     * Intercept all link clicks for smooth navigation
     */
    function interceptLinks() {
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a[href]');
            if (link &&
                link.href &&
                link.host === window.location.host && // Same domain
                !link.hasAttribute('download') && // Not download links
                !link.hasAttribute('target') && // Not external links
                !link.href.includes('#') && // Not anchor links
                !link.classList.contains('dropdown-item') && // Not dropdown items (handled differently)
                !link.classList.contains('no-loading')) { // Allow opting out

                // Show loading screen
                showLoading();
            }
        });
    }

    /**
     * Show loading during form submissions
     */
    function interceptForms() {
        document.addEventListener('submit', function(e) {
            if (!e.target.classList.contains('no-loading')) {
                showLoading();
            }
        });
    }

    /**
     * Hide loading on page load
     */
    function handlePageLoad() {
        // Hide loading screen when page is fully loaded
        window.addEventListener('load', function() {
            setTimeout(() => {
                hideLoading();
            }, 500); // Small delay for smooth transition
        });

        // Also hide on DOM ready as fallback
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                hideLoading();
            }, 100);
        });
    }

    /**
     * Hide loading when navigating away (page unload)
     */
    function handleNavigation() {
        window.addEventListener('beforeunload', function() {
            showLoading();
        });
    }

    /**
     * Public API
     */
    window.LoadingScreen = {
        show: showLoading,
        hide: hideLoading
    };

    // Initialize
    function init() {
        interceptLinks();
        interceptForms();
        handlePageLoad();
        handleNavigation();
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
</script>
