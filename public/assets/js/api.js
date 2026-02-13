/**
 * Unfurl - API Fetch Wrapper
 * Handles CSRF tokens, JSON serialization, error handling
 */

import { Notify } from './notifications.js';

/**
 * API client for making authenticated requests
 */
class API {
  constructor() {
    this.baseUrl = '';
    this.csrfToken = null;
    this.defaultTimeout = 300000; // 5 minutes (for browser automation processing)
    this.init();
  }

  /**
   * Initialize API client and retrieve CSRF token
   */
  init() {
    // Try to find CSRF token in meta tag
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
      this.csrfToken = csrfMeta.getAttribute('content');
    }

    // Try to find CSRF token in hidden input
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
      this.csrfToken = csrfInput.value;
    }
  }

  /**
   * Fetch wrapper with automatic CSRF token inclusion
   * @param {string} url - Request URL
   * @param {Object} options - Fetch options (method, body, etc)
   * @returns {Promise<*>} Parsed JSON response
   */
  async request(url, options = {}) {
    const config = {
      method: options.method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    };

    // Add CSRF token to request headers if present
    if (this.csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(config.method)) {
      config.headers['X-CSRF-Token'] = this.csrfToken;
    }

    // Stringify body if it's an object
    if (config.body && typeof config.body === 'object') {
      config.body = JSON.stringify(config.body);
    }

    // Add timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.defaultTimeout);
    config.signal = controller.signal;

    try {
      const response = await fetch(url, config);
      clearTimeout(timeoutId);

      // Handle network errors
      if (!response.ok) {
        throw new APIError(
          `HTTP ${response.status}`,
          response.status,
          response
        );
      }

      // Parse JSON response
      const contentType = response.headers.get('content-type');
      let data;

      if (contentType?.includes('application/json')) {
        data = await response.json();
      } else {
        data = await response.text();
      }

      return data;
    } catch (error) {
      clearTimeout(timeoutId);

      if (error instanceof APIError) {
        throw error;
      }

      if (error.name === 'AbortError') {
        throw new APIError('Request timeout', 408);
      }

      throw new APIError(error.message, 0, null);
    }
  }

  /**
   * GET request
   * @param {string} url
   * @param {Object} params - Query parameters
   * @returns {Promise<*>}
   */
  async get(url, params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const fullUrl = queryString ? `${url}?${queryString}` : url;
    return this.request(fullUrl, { method: 'GET' });
  }

  /**
   * POST request
   * @param {string} url
   * @param {Object} data
   * @returns {Promise<*>}
   */
  async post(url, data = {}) {
    return this.request(url, {
      method: 'POST',
      body: data
    });
  }

  /**
   * PUT request
   * @param {string} url
   * @param {Object} data
   * @returns {Promise<*>}
   */
  async put(url, data = {}) {
    return this.request(url, {
      method: 'PUT',
      body: data
    });
  }

  /**
   * PATCH request
   * @param {string} url
   * @param {Object} data
   * @returns {Promise<*>}
   */
  async patch(url, data = {}) {
    return this.request(url, {
      method: 'PATCH',
      body: data
    });
  }

  /**
   * DELETE request
   * @param {string} url
   * @returns {Promise<*>}
   */
  async delete(url) {
    return this.request(url, { method: 'DELETE' });
  }
}

/**
 * Custom error class for API errors
 */
class APIError extends Error {
  constructor(message, status, response = null) {
    super(message);
    this.name = 'APIError';
    this.status = status;
    this.response = response;
  }
}

/**
 * Global API instance with automatic error handling
 */
class SafeAPI extends API {
  /**
   * Fetch with automatic error notification
   * @param {string} url
   * @param {Object} options
   * @param {boolean} notify - Show error notification (default true)
   * @returns {Promise<*>}
   */
  async request(url, options = {}, notify = true) {
    try {
      return await super.request(url, options);
    } catch (error) {
      if (notify) {
        this.handleError(error);
      }
      throw error;
    }
  }

  /**
   * Handle and display API errors
   * @param {Error} error
   */
  handleError(error) {
    if (error instanceof APIError) {
      if (error.status === 401) {
        Notify.error('Session expired. Please log in again.');
      } else if (error.status === 403) {
        Notify.error('You do not have permission to perform this action.');
      } else if (error.status === 404) {
        Notify.error('Resource not found.');
      } else if (error.status === 408) {
        Notify.error('Request timeout. Please check your connection.');
      } else if (error.status >= 500) {
        Notify.error('Server error. Please try again later.');
      } else {
        Notify.error(error.message);
      }
    } else {
      Notify.error('Network error. Please check your connection.');
    }
  }

  /**
   * GET with error handling
   * @param {string} url
   * @param {Object} params
   * @param {boolean} notify
   * @returns {Promise<*>}
   */
  async get(url, params = {}, notify = true) {
    const queryString = new URLSearchParams(params).toString();
    const fullUrl = queryString ? `${url}?${queryString}` : url;
    return this.request(fullUrl, { method: 'GET' }, notify);
  }

  /**
   * POST with error handling
   * @param {string} url
   * @param {Object} data
   * @param {boolean} notify
   * @returns {Promise<*>}
   */
  async post(url, data = {}, notify = true) {
    return this.request(url, { method: 'POST', body: data }, notify);
  }

  /**
   * PUT with error handling
   * @param {string} url
   * @param {Object} data
   * @param {boolean} notify
   * @returns {Promise<*>}
   */
  async put(url, data = {}, notify = true) {
    return this.request(url, { method: 'PUT', body: data }, notify);
  }

  /**
   * PATCH with error handling
   * @param {string} url
   * @param {Object} data
   * @param {boolean} notify
   * @returns {Promise<*>}
   */
  async patch(url, data = {}, notify = true) {
    return this.request(url, { method: 'PATCH', body: data }, notify);
  }

  /**
   * DELETE with error handling
   * @param {string} url
   * @param {boolean} notify
   * @returns {Promise<*>}
   */
  async delete(url, notify = true) {
    return this.request(url, { method: 'DELETE' }, notify);
  }
}

// Export singleton instance and class
const api = new SafeAPI();
export { api, API, APIError };
