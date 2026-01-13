/**
 * Global Notification System
 * Handles toast notifications across the application.
 */

// Create container if it doesn't exist
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('toast-container')) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
});

/**
 * Show a toast notification
 * @param {string} title - Title of the notification
 * @param {string} message - Body text
 * @param {string} type - 'success', 'error', 'warning', 'info'
 * @param {number} duration - Time in ms before auto-dismiss (default 5000)
 */
function showToast(title, message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return; // Should allow DOMContentLoaded to fire first

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Icon selection
    let iconClass = 'bi-info-circle-fill';
    if (type === 'success') iconClass = 'bi-check-circle-fill';
    if (type === 'error') iconClass = 'bi-x-circle-fill';
    if (type === 'warning') iconClass = 'bi-exclamation-triangle-fill';

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="bi ${iconClass}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>
    `;

    // Add to container
    container.appendChild(toast);

    // Animate in
    // (CSS Handle animation with @keyframes slideInRight)

    // Auto dismiss
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s forwards';
        toast.addEventListener('animationend', () => {
            if(toast.parentElement) toast.remove();
        });
    }, duration);
}

// Expose to window
window.showToast = showToast;
