/**
 * Unfurl - Form Utilities
 * Form validation, serialization, and CSRF token injection
 */

import { DOM, StringUtils } from './utils.js';
import { Notify } from './notifications.js';

/**
 * Form validation and utilities
 */
class FormValidator {
  constructor() {
    this.rules = {};
    this.messages = {};
    this.setupDefaultMessages();
  }

  /**
   * Setup default validation messages
   */
  setupDefaultMessages() {
    this.messages = {
      required: '{field} is required',
      email: '{field} must be a valid email',
      url: '{field} must be a valid URL',
      min: '{field} must be at least {param} characters',
      max: '{field} must be at most {param} characters',
      match: '{field} must match {param}',
      number: '{field} must be a number',
      phone: '{field} must be a valid phone number'
    };
  }

  /**
   * Validate form
   * @param {HTMLFormElement} form
   * @param {Object} rules - Validation rules {fieldName: ['rule1', 'rule2']}
   * @returns {Object} Errors object {fieldName: [errors]}
   */
  validate(form, rules = {}) {
    const errors = {};

    Object.entries(rules).forEach(([fieldName, fieldRules]) => {
      const field = form.elements[fieldName];
      if (!field) return;

      const fieldErrors = this.validateField(field, fieldRules);
      if (fieldErrors.length > 0) {
        errors[fieldName] = fieldErrors;
      }
    });

    return errors;
  }

  /**
   * Validate single field
   * @param {HTMLInputElement} field
   * @param {Array} rules
   * @returns {Array} Error messages
   */
  validateField(field, rules = []) {
    const errors = [];
    const value = field.value.trim();
    const fieldLabel = field.getAttribute('data-label') || field.name;

    rules.forEach(rule => {
      const [ruleName, ...params] = rule.split(':');

      switch (ruleName) {
        case 'required':
          if (!value) {
            errors.push(this.formatMessage('required', fieldLabel));
          }
          break;

        case 'email':
          if (value && !this.isValidEmail(value)) {
            errors.push(this.formatMessage('email', fieldLabel));
          }
          break;

        case 'url':
          if (value && !this.isValidUrl(value)) {
            errors.push(this.formatMessage('url', fieldLabel));
          }
          break;

        case 'min':
          if (value && value.length < parseInt(params[0])) {
            errors.push(this.formatMessage('min', fieldLabel, params[0]));
          }
          break;

        case 'max':
          if (value && value.length > parseInt(params[0])) {
            errors.push(this.formatMessage('max', fieldLabel, params[0]));
          }
          break;

        case 'match':
          const matchField = document.querySelector(`[name="${params[0]}"]`);
          if (matchField && value !== matchField.value) {
            const matchLabel = params[0];
            errors.push(this.formatMessage('match', fieldLabel, matchLabel));
          }
          break;

        case 'number':
          if (value && isNaN(value)) {
            errors.push(this.formatMessage('number', fieldLabel));
          }
          break;

        case 'phone':
          if (value && !this.isValidPhone(value)) {
            errors.push(this.formatMessage('phone', fieldLabel));
          }
          break;

        case 'pattern':
          if (value && !new RegExp(params[0]).test(value)) {
            errors.push(`${fieldLabel} format is invalid`);
          }
          break;
      }
    });

    return errors;
  }

  /**
   * Validate email format
   * @param {string} email
   * @returns {boolean}
   */
  isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }

  /**
   * Validate URL format
   * @param {string} url
   * @returns {boolean}
   */
  isValidUrl(url) {
    try {
      new URL(url);
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Validate phone number (basic)
   * @param {string} phone
   * @returns {boolean}
   */
  isValidPhone(phone) {
    // Match: +1-234-567-8900, 234-567-8900, (234) 567-8900, etc.
    const re = /^[\d\s\-\+\(\)]+$/;
    return re.test(phone) && phone.replace(/\D/g, '').length >= 10;
  }

  /**
   * Format validation message
   * @param {string} messageKey
   * @param {string} field
   * @param {string|number} param
   * @returns {string}
   */
  formatMessage(messageKey, field, param = null) {
    let message = this.messages[messageKey] || 'Invalid input';
    message = message.replace('{field}', field);
    if (param) {
      message = message.replace('{param}', param);
    }
    return message;
  }

  /**
   * Display validation errors on form
   * @param {HTMLFormElement} form
   * @param {Object} errors - Errors object from validate()
   */
  displayErrors(form, errors) {
    // Clear previous error states
    DOM.selectAll('.form-group', form).forEach(group => {
      DOM.removeClass(group, 'error');
      const errorDiv = DOM.select('.form-error', group);
      if (errorDiv) DOM.remove(errorDiv);
    });

    // Display new errors
    Object.entries(errors).forEach(([fieldName, fieldErrors]) => {
      const field = form.elements[fieldName];
      if (!field) return;

      const group = DOM.closest(field, '.form-group');
      if (!group) return;

      DOM.addClass(group, 'error');

      const errorDiv = DOM.create('div', { class: 'form-error' });
      fieldErrors.forEach(error => {
        const errorLine = DOM.create('div');
        errorLine.textContent = error;
        errorDiv.appendChild(errorLine);
      });

      group.appendChild(errorDiv);
    });
  }

  /**
   * Clear all validation errors from form
   * @param {HTMLFormElement} form
   */
  clearErrors(form) {
    DOM.selectAll('.form-group', form).forEach(group => {
      DOM.removeClass(group, 'error');
      const errorDiv = DOM.select('.form-error', group);
      if (errorDiv) DOM.remove(errorDiv);
    });
  }
}

/**
 * Form serialization utilities
 */
class FormSerializer {
  /**
   * Serialize form to object
   * @param {HTMLFormElement} form
   * @returns {Object}
   */
  static toObject(form) {
    const data = {};
    const formData = new FormData(form);

    formData.forEach((value, name) => {
      if (data[name]) {
        // Handle multiple values (checkboxes, multi-select)
        if (Array.isArray(data[name])) {
          data[name].push(value);
        } else {
          data[name] = [data[name], value];
        }
      } else {
        data[name] = value;
      }
    });

    return data;
  }

  /**
   * Serialize form to FormData
   * @param {HTMLFormElement} form
   * @returns {FormData}
   */
  static toFormData(form) {
    return new FormData(form);
  }

  /**
   * Serialize form to URL encoded string
   * @param {HTMLFormElement} form
   * @returns {string}
   */
  static toUrlEncoded(form) {
    return new URLSearchParams(new FormData(form)).toString();
  }

  /**
   * Populate form from object
   * @param {HTMLFormElement} form
   * @param {Object} data
   */
  static fromObject(form, data) {
    Object.entries(data).forEach(([name, value]) => {
      const field = form.elements[name];
      if (!field) return;

      if (field.type === 'checkbox') {
        field.checked = value;
      } else if (field.type === 'radio') {
        const radioField = form.querySelector(`input[name="${name}"][value="${value}"]`);
        if (radioField) radioField.checked = true;
      } else if (field.type === 'select-multiple') {
        Array.from(field.options).forEach(option => {
          option.selected = Array.isArray(value) && value.includes(option.value);
        });
      } else {
        field.value = value;
      }
    });
  }

  /**
   * Clear form
   * @param {HTMLFormElement} form
   */
  static clear(form) {
    form.reset();
  }
}

/**
 * CSRF token utilities
 */
class CSRFToken {
  /**
   * Get CSRF token from page
   * @returns {string|null}
   */
  static getToken() {
    // Check meta tag first
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');

    // Check hidden input
    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;

    return null;
  }

  /**
   * Add CSRF token to form
   * @param {HTMLFormElement} form
   */
  static addToForm(form) {
    const token = this.getToken();
    if (!token) return;

    // Check if token field already exists
    if (form.elements['csrf_token']) return;

    // Add hidden input
    const input = DOM.create('input', {
      type: 'hidden',
      name: 'csrf_token',
      value: token
    });
    form.appendChild(input);
  }

  /**
   * Add CSRF token to headers object
   * @returns {Object}
   */
  static getHeaders() {
    const token = this.getToken();
    return token ? { 'X-CSRF-Token': token } : {};
  }

  /**
   * Refresh CSRF token from server
   * @returns {Promise<string>}
   */
  static async refresh() {
    try {
      const response = await fetch('/api/csrf-token', {
        method: 'GET',
        credentials: 'same-origin'
      });
      const data = await response.json();
      const token = data.token || data.csrf_token;

      if (token) {
        // Update meta tag
        let meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) {
          meta = DOM.create('meta', { name: 'csrf-token' });
          document.head.appendChild(meta);
        }
        meta.setAttribute('content', token);

        // Update hidden inputs
        document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
          input.value = token;
        });
      }

      return token;
    } catch (error) {
      console.error('Failed to refresh CSRF token:', error);
      return null;
    }
  }
}

export { FormValidator, FormSerializer, CSRFToken };
