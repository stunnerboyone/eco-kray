/**
 * Eco-Kray Unified Notification System
 * Beautiful, consistent notifications for all site messages
 */

(function() {
    'use strict';

    // Create notification container if it doesn't exist
    function ensureContainer() {
        let container = document.querySelector('.ekokray-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'ekokray-notification-container';
            document.body.appendChild(container);
        }
        return container;
    }

    // Get icon based on notification type
    function getIcon(type) {
        const icons = {
            success: '<i class="fa fa-check-circle"></i>',
            error: '<i class="fa fa-exclamation-circle"></i>',
            warning: '<i class="fa fa-exclamation-triangle"></i>',
            info: '<i class="fa fa-info-circle"></i>'
        };
        return icons[type] || icons.info;
    }

    // Show notification
    window.showNotification = function(message, type = 'success', options = {}) {
        // Default options
        const defaults = {
            duration: 5000,
            closable: true,
            showProgress: false
        };

        const settings = Object.assign({}, defaults, options);
        const container = ensureContainer();

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `ekokray-notification ${type}`;

        // Build notification HTML
        let html = `
            <div class="ekokray-notification-icon">
                ${getIcon(type)}
            </div>
            <div class="ekokray-notification-message">
                ${message}
            </div>
        `;

        if (settings.closable) {
            html += `
                <button class="ekokray-notification-close" type="button" aria-label="Close">
                    ×
                </button>
            `;
        }

        if (settings.showProgress) {
            html += '<div class="ekokray-notification-progress"></div>';
        }

        notification.innerHTML = html;
        container.appendChild(notification);

        // Add close button functionality
        if (settings.closable) {
            const closeBtn = notification.querySelector('.ekokray-notification-close');
            closeBtn.addEventListener('click', function() {
                removeNotification(notification);
            });
        }

        // Auto-remove after duration
        if (settings.duration > 0) {
            setTimeout(function() {
                removeNotification(notification);
            }, settings.duration);
        }

        return notification;
    };

    // Remove notification with animation
    function removeNotification(notification) {
        if (!notification || !notification.parentNode) return;

        notification.classList.add('removing');
        setTimeout(function() {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Convenience methods
    window.showSuccess = function(message, options) {
        return showNotification(message, 'success', options);
    };

    window.showError = function(message, options) {
        return showNotification(message, 'error', options);
    };

    window.showWarning = function(message, options) {
        return showNotification(message, 'warning', options);
    };

    window.showInfo = function(message, options) {
        return showNotification(message, 'info', options);
    };

})();
