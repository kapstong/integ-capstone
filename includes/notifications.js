/**
 * ATIERA Financial Management System - Notification System
 * Handles real-time notifications for login/logout and other events
 */

(function() {
    'use strict';

    const NOTIFICATION_CHECK_INTERVAL = 30000; // Check every 30 seconds
    const MAX_NOTIFICATIONS_DISPLAY = 5;
    const AUTO_HIDE_DURATION = 5000; // Auto-hide toast after 5 seconds

    let notificationInterval = null;
    let unreadCount = 0;

    /**
     * Initialize notification system
     */
    function init() {
        createNotificationBell();
        createNotificationPanel();
        createToastContainer();
        loadNotifications();
        startPolling();
    }

    /**
     * Create notification bell icon in navbar
     */
    function createNotificationBell() {
        // Find user dropdown or navbar element
        const navbar = document.querySelector('.navbar .container-fluid');
        if (!navbar) return;

        const bellHTML = `
            <div class="dropdown me-3" id="notification-bell-container">
                <button class="btn btn-link position-relative p-2" type="button" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="fas fa-bell fa-lg" style="color: #64748b;"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-badge" style="display: none; font-size: 0.7rem;">
                        0
                    </span>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" id="notification-dropdown" style="min-width: 350px; max-width: 400px;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center bg-light">
                        <h6 class="mb-0">Notifications</h6>
                        <button class="btn btn-sm btn-link text-primary p-0" onclick="window.NotificationSystem.markAllAsRead()" title="Mark all as read">
                            <i class="fas fa-check-double"></i>
                        </button>
                    </div>
                    <div class="dropdown-divider m-0"></div>
                    <div id="notification-list" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center p-4 text-muted">
                            <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                            <p>Loading notifications...</p>
                        </div>
                    </div>
                    <div class="dropdown-divider m-0"></div>
                    <div class="dropdown-footer text-center">
                        <a href="notifications.php" class="btn btn-sm btn-link">View All Notifications</a>
                    </div>
                </div>
            </div>
        `;

        // Insert before user dropdown
        const userDropdown = navbar.querySelector('.dropdown');
        if (userDropdown) {
            userDropdown.insertAdjacentHTML('beforebegin', bellHTML);
        }
    }

    /**
     * Create notification panel (optional full-screen panel)
     */
    function createNotificationPanel() {
        // Future enhancement: create a slide-in panel for notifications
    }

    /**
     * Create toast container for popup notifications
     */
    function createToastContainer() {
        const toastHTML = `
            <div id="toast-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;">
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', toastHTML);
    }

    /**
     * Load notifications from server
     */
    function loadNotifications() {
        fetch(getBaseURL() + '/api/notifications.php?action=list&limit=' + MAX_NOTIFICATIONS_DISPLAY)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationList(data.notifications);
                    updateNotificationBadge(data.unread_count);
                    unreadCount = data.unread_count;

                    // Show toast for new login notifications
                    data.notifications.forEach(notification => {
                        if (!notification.is_read && (notification.type === 'login' || notification.type === 'logout')) {
                            // Only show if less than 2 minutes old
                            const createdAt = new Date(notification.created_at);
                            const now = new Date();
                            const diffMinutes = (now - createdAt) / 1000 / 60;

                            if (diffMinutes < 2) {
                                showToast(notification);
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
    }

    /**
     * Update notification list in dropdown
     */
    function updateNotificationList(notifications) {
        const listContainer = document.getElementById('notification-list');
        if (!listContainer) return;

        if (notifications.length === 0) {
            listContainer.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }

        let html = '';
        notifications.forEach(notification => {
            const icon = getNotificationIcon(notification.type);
            const iconColor = getNotificationColor(notification.type);
            const isUnread = !notification.is_read;
            const timeAgo = formatTimeAgo(notification.created_at);

            html += `
                <div class="dropdown-item notification-item ${isUnread ? 'bg-light' : ''}"
                     data-id="${notification.id}"
                     onclick="window.NotificationSystem.markAsRead(${notification.id})"
                     style="cursor: pointer; border-left: 3px solid ${isUnread ? iconColor : 'transparent'}; padding: 12px 16px;">
                    <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                            <i class="fas ${icon} fa-lg" style="color: ${iconColor};"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="font-size: 0.9rem; font-weight: ${isUnread ? '600' : '500'};">
                                ${escapeHtml(notification.title)}
                            </h6>
                            <p class="mb-1 text-muted" style="font-size: 0.85rem;">
                                ${escapeHtml(notification.message)}
                            </p>
                            <small class="text-muted" style="font-size: 0.75rem;">
                                <i class="fas fa-clock"></i> ${timeAgo}
                            </small>
                        </div>
                    </div>
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    /**
     * Update notification badge
     */
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';

            // Animate badge
            badge.style.animation = 'none';
            setTimeout(() => {
                badge.style.animation = 'pulse 1s ease-in-out';
            }, 10);
        } else {
            badge.style.display = 'none';
        }
    }

    /**
     * Show toast notification
     */
    function showToast(notification) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const icon = getNotificationIcon(notification.type);
        const iconColor = getNotificationColor(notification.type);
        const toastId = 'toast-' + notification.id;

        const toastHTML = `
            <div id="${toastId}" class="toast show mb-2" role="alert" style="box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-left: 4px solid ${iconColor};">
                <div class="toast-header">
                    <i class="fas ${icon} me-2" style="color: ${iconColor};"></i>
                    <strong class="me-auto">${escapeHtml(notification.title)}</strong>
                    <small class="text-muted">just now</small>
                    <button type="button" class="btn-close" onclick="document.getElementById('${toastId}').remove()"></button>
                </div>
                <div class="toast-body">
                    ${escapeHtml(notification.message)}
                </div>
            </div>
        `;

        container.insertAdjacentHTML('afterbegin', toastHTML);

        // Auto-hide after duration
        setTimeout(() => {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('fade-out');
                setTimeout(() => toast.remove(), 300);
            }
        }, AUTO_HIDE_DURATION);
    }

    /**
     * Mark notification as read
     */
    function markAsRead(notificationId) {
        fetch(getBaseURL() + '/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_read',
                notification_id: notificationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }

    /**
     * Mark all notifications as read
     */
    function markAllAsRead() {
        fetch(getBaseURL() + '/api/notifications.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'mark_all_read'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        })
        .catch(error => console.error('Error marking all notifications as read:', error));
    }

    /**
     * Start polling for new notifications
     */
    function startPolling() {
        notificationInterval = setInterval(loadNotifications, NOTIFICATION_CHECK_INTERVAL);
    }

    /**
     * Stop polling
     */
    function stopPolling() {
        if (notificationInterval) {
            clearInterval(notificationInterval);
            notificationInterval = null;
        }
    }

    /**
     * Get icon for notification type
     */
    function getNotificationIcon(type) {
        const icons = {
            'login': 'fa-sign-in-alt',
            'logout': 'fa-sign-out-alt',
            'info': 'fa-info-circle',
            'warning': 'fa-exclamation-triangle',
            'error': 'fa-times-circle',
            'success': 'fa-check-circle'
        };
        return icons[type] || 'fa-bell';
    }

    /**
     * Get color for notification type
     */
    function getNotificationColor(type) {
        const colors = {
            'login': '#10b981',
            'logout': '#f59e0b',
            'info': '#3b82f6',
            'warning': '#f59e0b',
            'error': '#ef4444',
            'success': '#10b981'
        };
        return colors[type] || '#6b7280';
    }

    /**
     * Format time ago
     */
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        return date.toLocaleDateString();
    }

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

    /**
     * Get base URL
     */
    function getBaseURL() {
        const path = window.location.pathname;
        const parts = path.split('/').filter(p => p);
        const index = parts.indexOf('integ-capstone');
        if (index !== -1) {
            return '/' + parts.slice(0, index + 1).join('/');
        }
        return '/' + parts.join('/');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', stopPolling);

    // Add pulse animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .fade-out {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
    `;
    document.head.appendChild(style);

    // Expose API
    window.NotificationSystem = {
        markAsRead: markAsRead,
        markAllAsRead: markAllAsRead,
        reload: loadNotifications,
        showToast: showToast
    };
})();
