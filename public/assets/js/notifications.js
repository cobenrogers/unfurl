/**
 * Unfurl - Toast Notification System
 * Display success, error, info, and warning messages
 */

import { DOM } from './utils.js';

/**
 * Toast notification manager
 */
class NotificationManager {
  constructor() {
    this.container = null;
    this.notifications = [];
    this.initContainer();
  }

  /**
   * Initialize notification container
   */
  initContainer() {
    // Check if container already exists
    const existing = DOM.byId('toast-container');
    if (existing) {
      this.container = existing;
      return;
    }

    // Create container
    this.container = DOM.create(
      'div',
      {
        id: 'toast-container',
        class: 'toast-container'
      }
    );

    document.body.appendChild(this.container);

    // Add CSS if not already present
    this.injectStyles();
  }

  /**
   * Inject toast styles into document
   */
  injectStyles() {
    if (document.getElementById('toast-styles')) return;

    const style = DOM.create('style', { id: 'toast-styles' }, `
      .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 400px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", sans-serif;
      }

      .toast {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        padding: 12px 16px;
        border-radius: 4px;
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        animation: slideIn 0.3s ease-out;
      }

      @keyframes slideIn {
        from {
          transform: translateX(400px);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }

      @keyframes slideOut {
        from {
          transform: translateX(0);
          opacity: 1;
        }
        to {
          transform: translateX(400px);
          opacity: 0;
        }
      }

      .toast.removing {
        animation: slideOut 0.3s ease-in forwards;
      }

      .toast-icon {
        flex-shrink: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
      }

      .toast-message {
        flex: 1;
        font-size: 14px;
        line-height: 1.4;
        color: #333;
      }

      .toast-close {
        flex-shrink: 0;
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        font-size: 18px;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s;
      }

      .toast-close:hover {
        color: #333;
      }

      /* Toast types */
      .toast.success {
        background: #f0f9ff;
        border-left: 4px solid #10b981;
      }

      .toast.success .toast-icon {
        color: #10b981;
      }

      .toast.success .toast-message {
        color: #047857;
      }

      .toast.error {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
      }

      .toast.error .toast-icon {
        color: #ef4444;
      }

      .toast.error .toast-message {
        color: #991b1b;
      }

      .toast.info {
        background: #f0f4ff;
        border-left: 4px solid #3b82f6;
      }

      .toast.info .toast-icon {
        color: #3b82f6;
      }

      .toast.info .toast-message {
        color: #1e40af;
      }

      .toast.warning {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
      }

      .toast.warning .toast-icon {
        color: #f59e0b;
      }

      .toast.warning .toast-message {
        color: #92400e;
      }

      /* Mobile responsive */
      @media (max-width: 640px) {
        .toast-container {
          left: 12px;
          right: 12px;
          top: 12px;
          max-width: none;
        }

        .toast {
          margin-bottom: 10px;
          padding: 12px 14px;
        }
      }
    `);

    document.head.appendChild(style);
  }

  /**
   * Show notification
   * @param {string} message - Notification text
   * @param {string} type - Type: 'success', 'error', 'info', 'warning'
   * @param {number} duration - Auto-dismiss after ms (0 = no auto-dismiss)
   * @returns {HTMLElement}
   */
  show(message, type = 'info', duration = 5000) {
    // Create toast element
    const toast = DOM.create(
      'div',
      {
        class: `toast ${type}`
      }
    );

    // Add icon
    const icon = this.getIcon(type);
    const iconEl = DOM.create('div', { class: 'toast-icon' }, icon);
    toast.appendChild(iconEl);

    // Add message
    const messageEl = DOM.create('div', { class: 'toast-message' });
    messageEl.textContent = message;
    toast.appendChild(messageEl);

    // Add close button
    const closeBtn = DOM.create('button', { class: 'toast-close' }, '×');
    closeBtn.setAttribute('aria-label', 'Close notification');
    DOM.on(closeBtn, 'click', () => this.remove(toast));
    toast.appendChild(closeBtn);

    // Add to container
    this.container.appendChild(toast);
    this.notifications.push(toast);

    // Auto-dismiss
    if (duration > 0) {
      setTimeout(() => this.remove(toast), duration);
    }

    return toast;
  }

  /**
   * Show success notification
   * @param {string} message
   * @param {number} duration
   * @returns {HTMLElement}
   */
  success(message, duration = 5000) {
    return this.show(message, 'success', duration);
  }

  /**
   * Show error notification
   * @param {string} message
   * @param {number} duration
   * @returns {HTMLElement}
   */
  error(message, duration = 5000) {
    return this.show(message, 'error', duration);
  }

  /**
   * Show info notification
   * @param {string} message
   * @param {number} duration
   * @returns {HTMLElement}
   */
  info(message, duration = 5000) {
    return this.show(message, 'info', duration);
  }

  /**
   * Show warning notification
   * @param {string} message
   * @param {number} duration
   * @returns {HTMLElement}
   */
  warning(message, duration = 5000) {
    return this.show(message, 'warning', duration);
  }

  /**
   * Remove notification with animation
   * @param {HTMLElement} toast
   */
  remove(toast) {
    DOM.addClass(toast, 'removing');

    setTimeout(() => {
      DOM.remove(toast);
      const index = this.notifications.indexOf(toast);
      if (index > -1) {
        this.notifications.splice(index, 1);
      }
    }, 300); // Match animation duration
  }

  /**
   * Clear all notifications
   */
  clear() {
    this.notifications.forEach(notification => {
      DOM.remove(notification);
    });
    this.notifications = [];
  }

  /**
   * Get icon for notification type
   * @param {string} type
   * @returns {string}
   */
  getIcon(type) {
    const icons = {
      success: '✓',
      error: '✕',
      info: 'ℹ',
      warning: '⚠'
    };
    return icons[type] || 'ℹ';
  }
}

// Create singleton instance
const Notify = new NotificationManager();

export { Notify };
