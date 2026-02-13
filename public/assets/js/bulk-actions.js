/**
 * Unfurl - Bulk Actions Manager
 * Handle checkbox selection, "select all", and bulk action confirmation
 */

import { DOM } from './utils.js';
import { Notify } from './notifications.js';

/**
 * Bulk actions manager for data tables
 */
class BulkActions {
  constructor(options = {}) {
    this.tableSelector = options.tableSelector || 'table[data-bulk]';
    this.selectAllSelector = options.selectAllSelector || 'input[data-select-all]';
    this.itemCheckboxSelector = options.itemCheckboxSelector || 'input[data-item]';
    this.bulkActionButtons = options.bulkActionButtons || '[data-bulk-action]';
    this.bulkActionContainers = options.bulkActionContainers || '.bulk-actions';
    this.confirmAction = options.confirmAction !== false;
    this.onSelectionChange = options.onSelectionChange;
    this.onBulkAction = options.onBulkAction;

    this.selectedIds = new Set();
    this.init();
  }

  /**
   * Initialize bulk actions
   */
  init() {
    const table = DOM.select(this.tableSelector);
    if (!table) {
      console.warn(`Bulk actions table not found: ${this.tableSelector}`);
      return;
    }

    this.table = table;
    this.setupEventListeners();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Select All checkbox
    const selectAllCheckbox = DOM.select(this.selectAllSelector, this.table);
    if (selectAllCheckbox) {
      DOM.on(selectAllCheckbox, 'change', () => this.handleSelectAll(selectAllCheckbox));
    }

    // Individual item checkboxes
    DOM.on(this.table, 'change', this.itemCheckboxSelector, (e) => {
      this.handleItemSelect(e.target);
    });

    // Bulk action buttons
    const buttons = DOM.selectAll(this.bulkActionButtons, this.table);
    buttons.forEach(button => {
      DOM.on(button, 'click', (e) => this.handleBulkAction(e));
    });
  }

  /**
   * Handle select all checkbox
   * @param {HTMLInputElement} checkbox
   */
  handleSelectAll(checkbox) {
    const itemCheckboxes = DOM.selectAll(this.itemCheckboxSelector, this.table);

    itemCheckboxes.forEach(cb => {
      cb.checked = checkbox.checked;
      const id = DOM.attr(cb, 'data-item');
      if (id) {
        if (checkbox.checked) {
          this.selectedIds.add(id);
        } else {
          this.selectedIds.delete(id);
        }
      }
    });

    this.updateUI();
    this.triggerSelectionChange();
  }

  /**
   * Handle individual item selection
   * @param {HTMLInputElement} checkbox
   */
  handleItemSelect(checkbox) {
    const id = DOM.attr(checkbox, 'data-item');
    if (!id) return;

    if (checkbox.checked) {
      this.selectedIds.add(id);
    } else {
      this.selectedIds.delete(id);
      // Uncheck "select all" if item is unchecked
      const selectAllCheckbox = DOM.select(this.selectAllSelector, this.table);
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
      }
    }

    this.updateUI();
    this.triggerSelectionChange();
  }

  /**
   * Handle bulk action button click
   * @param {Event} e
   */
  handleBulkAction(e) {
    e.preventDefault();

    const button = e.target.closest(this.bulkActionButtons);
    if (!button) return;

    if (this.selectedIds.size === 0) {
      Notify.warning('Please select at least one item');
      return;
    }

    const action = DOM.attr(button, 'data-bulk-action');
    const actionLabel = DOM.text(button) || action;

    if (this.confirmAction) {
      this.showConfirmation(
        `Confirm ${actionLabel}`,
        `Apply "${actionLabel}" to ${this.selectedIds.size} item(s)?`,
        () => this.executeBulkAction(action)
      );
    } else {
      this.executeBulkAction(action);
    }
  }

  /**
   * Execute bulk action
   * @param {string} action - Action identifier
   */
  executeBulkAction(action) {
    const ids = Array.from(this.selectedIds);

    if (this.onBulkAction) {
      this.onBulkAction({
        action,
        ids,
        count: ids.length
      });
    } else {
      console.warn(`No handler for bulk action: ${action}`);
    }
  }

  /**
   * Trigger selection change callback
   */
  triggerSelectionChange() {
    if (this.onSelectionChange) {
      this.onSelectionChange({
        selectedIds: Array.from(this.selectedIds),
        count: this.selectedIds.size
      });
    }
  }

  /**
   * Update UI (enable/disable bulk action buttons)
   */
  updateUI() {
    const hasSelection = this.selectedIds.size > 0;
    const buttons = DOM.selectAll(this.bulkActionButtons, this.table);

    buttons.forEach(button => {
      if (hasSelection) {
        button.disabled = false;
        DOM.removeClass(button, 'disabled');
      } else {
        button.disabled = true;
        DOM.addClass(button, 'disabled');
      }
    });

    // Update bulk action containers visibility
    const containers = DOM.selectAll(this.bulkActionContainers, this.table);
    containers.forEach(container => {
      if (hasSelection) {
        container.style.display = 'block';
      } else {
        container.style.display = 'none';
      }
    });

    // Update selection counter if exists
    const counter = DOM.select('[data-selection-count]', this.table);
    if (counter) {
      counter.textContent = this.selectedIds.size;
    }
  }

  /**
   * Show confirmation dialog
   * @param {string} title
   * @param {string} message
   * @param {Function} onConfirm
   * @param {Function} onCancel
   */
  showConfirmation(title, message, onConfirm, onCancel) {
    const dialog = this.createConfirmDialog(title, message, onConfirm, onCancel);
    document.body.appendChild(dialog);
    dialog.showModal();
  }

  /**
   * Create confirmation dialog element
   * @param {string} title
   * @param {string} message
   * @param {Function} onConfirm
   * @param {Function} onCancel
   * @returns {HTMLDialogElement}
   */
  createConfirmDialog(title, message, onConfirm, onCancel) {
    const dialog = DOM.create('dialog', { class: 'confirm-dialog' });

    const content = DOM.create('div', { class: 'confirm-dialog-content' });

    const titleEl = DOM.create('h2', { class: 'confirm-dialog-title' }, title);
    content.appendChild(titleEl);

    const messageEl = DOM.create('p', { class: 'confirm-dialog-message' }, message);
    content.appendChild(messageEl);

    const actions = DOM.create('div', { class: 'confirm-dialog-actions' });

    const cancelBtn = DOM.create('button', {
      class: 'btn btn-secondary',
      type: 'button'
    }, 'Cancel');
    DOM.on(cancelBtn, 'click', () => {
      dialog.close();
      DOM.remove(dialog);
      if (onCancel) onCancel();
    });
    actions.appendChild(cancelBtn);

    const confirmBtn = DOM.create('button', {
      class: 'btn btn-danger',
      type: 'button'
    }, 'Confirm');
    DOM.on(confirmBtn, 'click', () => {
      dialog.close();
      DOM.remove(dialog);
      onConfirm();
    });
    actions.appendChild(confirmBtn);

    content.appendChild(actions);
    dialog.appendChild(content);

    // Inject dialog styles
    this.injectDialogStyles();

    return dialog;
  }

  /**
   * Inject confirmation dialog CSS
   */
  injectDialogStyles() {
    if (document.getElementById('confirm-dialog-styles')) return;

    const style = DOM.create('style', { id: 'confirm-dialog-styles' }, `
      .confirm-dialog {
        border: none;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 400px;
        padding: 0;
      }

      .confirm-dialog::backdrop {
        background: rgba(0, 0, 0, 0.5);
      }

      .confirm-dialog-content {
        padding: 24px;
      }

      .confirm-dialog-title {
        margin: 0 0 12px 0;
        font-size: 18px;
        font-weight: 600;
        color: #333;
      }

      .confirm-dialog-message {
        margin: 0 0 24px 0;
        font-size: 14px;
        color: #666;
        line-height: 1.5;
      }

      .confirm-dialog-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
      }

      .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 0.2s;
      }

      .btn-secondary {
        background: #f0f0f0;
        color: #333;
      }

      .btn-secondary:hover {
        background: #e0e0e0;
      }

      .btn-danger {
        background: #ef4444;
        color: white;
      }

      .btn-danger:hover {
        background: #dc2626;
      }

      .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
    `);

    document.head.appendChild(style);
  }

  /**
   * Get selected IDs
   * @returns {Array}
   */
  getSelectedIds() {
    return Array.from(this.selectedIds);
  }

  /**
   * Get selection count
   * @returns {number}
   */
  getCount() {
    return this.selectedIds.size;
  }

  /**
   * Clear selection
   */
  clear() {
    this.selectedIds.clear();
    DOM.selectAll(this.itemCheckboxSelector, this.table).forEach(cb => {
      cb.checked = false;
    });
    const selectAllCheckbox = DOM.select(this.selectAllSelector, this.table);
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = false;
    }
    this.updateUI();
    this.triggerSelectionChange();
  }

  /**
   * Add item to selection
   * @param {string} id
   */
  select(id) {
    this.selectedIds.add(id);
    const checkbox = DOM.select(`${this.itemCheckboxSelector}[data-item="${id}"]`, this.table);
    if (checkbox) {
      checkbox.checked = true;
    }
    this.updateUI();
    this.triggerSelectionChange();
  }

  /**
   * Remove item from selection
   * @param {string} id
   */
  deselect(id) {
    this.selectedIds.delete(id);
    const checkbox = DOM.select(`${this.itemCheckboxSelector}[data-item="${id}"]`, this.table);
    if (checkbox) {
      checkbox.checked = false;
    }
    this.updateUI();
    this.triggerSelectionChange();
  }

  /**
   * Toggle item selection
   * @param {string} id
   */
  toggle(id) {
    if (this.selectedIds.has(id)) {
      this.deselect(id);
    } else {
      this.select(id);
    }
  }

  /**
   * Check if item is selected
   * @param {string} id
   * @returns {boolean}
   */
  isSelected(id) {
    return this.selectedIds.has(id);
  }

  /**
   * Select all items
   */
  selectAll() {
    const selectAllCheckbox = DOM.select(this.selectAllSelector, this.table);
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = true;
      this.handleSelectAll(selectAllCheckbox);
    }
  }

  /**
   * Deselect all items
   */
  deselectAll() {
    this.clear();
  }
}

export { BulkActions };
