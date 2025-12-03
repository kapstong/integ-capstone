<?php
/**
 * ATIERA Financial Management System - Loading Screen Component
 * Premium branded loading screen matching system color palette
 */
?>
<style>
/* ATIERA Loading Screen - Matches System Color Palette */
.loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #F1F7EE;
    z-index: 10000;
    display: none;
    opacity: 0;
    transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(8px);
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
    padding: 3rem 2.5rem;
    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 20px;
    box-shadow:
        0 25px 50px rgba(30, 41, 54, 0.15),
        0 15px 35px rgba(30, 41, 54, 0.1),
        0 0 0 1px rgba(30, 41, 54, 0.05);
    border: 1px solid rgba(30, 41, 54, 0.08);
    max-width: 420px;
    width: 90%;
}

.loading-header {
    margin-bottom: 2rem;
}

.loading-logo {
    width: 140px;
    height: auto;
    margin-bottom: 1.5rem;
    filter: drop-shadow(0 4px 12px rgba(30, 41, 54, 0.15));
}

.loading-title {
    color: #1e2936;
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.025em;
    line-height: 1.2;
    text-shadow: 0 1px 2px rgba(30, 41, 54, 0.1);
}

.loading-subtitle {
    color: #6c757d;
    font-size: 0.95rem;
    font-weight: 500;
    margin: 0;
    opacity: 0.8;
}

.loading-animation {
    margin: 2rem 0;
}

.loading-spinner {
    width: 64px;
    height: 64px;
    margin: 0 auto 1.5rem auto;
    position: relative;
}

.loading-spinner::before,
.loading-spinner::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

.loading-spinner::before {
    width: 100%;
    height: 100%;
    border: 3px solid rgba(30, 41, 54, 0.1);
    border-top: 3px solid #1e2936;
    animation: spin 1.5s linear infinite;
}

.loading-spinner::after {
    width: 80%;
    height: 80%;
    background: linear-gradient(135deg, #1e2936 0%, #2342a6 100%);
    top: 10%;
    left: 10%;
    opacity: 0.15;
    filter: blur(8px);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 0.8;
    }
    50% {
        transform: scale(1.05);
        opacity: 1;
    }
}

.loading-progress-container {
    position: relative;
    margin-top: 1rem;
}

.loading-progress-bg {
    width: 100%;
    height: 3px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
    position: relative;
}

.loading-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #1e2936 0%, #2342a6 50%, #1e2936 100%);
    border-radius: 2px;
    width: 0%;
    animation: progress 2.5s ease-in-out infinite;
    position: relative;
}

.loading-progress-fill::after {
    content: '';
    position: absolute;
    right: 0;
    top: 0;
    width: 20px;
    height: 100%;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 100%);
    animation: shimmer 2.5s ease-in-out infinite;
}

@keyframes progress {
    0% { width: 0%; }
    50% { width: 85%; }
    100% { width: 100%; }
}

@keyframes shimmer {
    0% { transform: translateX(-20px); }
    100% { transform: translateX(40px); }
}

.loading-accent {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #1e2936 0%, #d4af37 50%, #1e2936 100%);
    border-radius: 20px 20px 0 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .loading-screen-content {
        padding: 2.5rem 2rem;
        margin: 1rem;
        width: calc(100% - 2rem);
    }

    .loading-title {
        font-size: 1.5rem;
    }

    .loading-subtitle {
        font-size: 0.9rem;
    }

    .loading-spinner {
        width: 56px;
        height: 56px;
    }

    .loading-logo {
        width: 120px;
    }
}

@media (max-width: 480px) {
    .loading-screen-content {
        padding: 2rem 1.5rem;
    }

    .loading-title {
        font-size: 1.4rem;
    }

    .loading-logo {
        width: 100px;
    }
}
</style>

<!-- Loading Screen HTML -->
<div id="loading-screen" class="loading-screen">
    <div class="loading-screen-content">
        <div class="loading-accent"></div>
        <div class="loading-header">
            <img src="../logo.png" alt="ATIERA" class="loading-logo" onerror="this.src='../logo2.png'">
            <h2 class="loading-title">Loading ATIERA</h2>
            <p class="loading-subtitle">Securing your financial data...</p>
        </div>

        <div class="loading-animation">
            <div class="loading-spinner"></div>
            <div class="loading-progress-container">
                <div class="loading-progress-bg">
                    <div class="loading-progress-fill"></div>
                </div>
            </div>
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
