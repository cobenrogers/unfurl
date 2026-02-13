/**
 * Unfurl - Utility Functions
 * Generic DOM, string, and date helpers
 */

/**
 * Query selector shortcuts
 */
const DOM = {
  /**
   * Select single element
   * @param {string} selector - CSS selector
   * @param {Element} context - Optional parent element (defaults to document)
   * @returns {Element|null}
   */
  select(selector, context = document) {
    return context.querySelector(selector);
  },

  /**
   * Select multiple elements
   * @param {string} selector - CSS selector
   * @param {Element} context - Optional parent element (defaults to document)
   * @returns {NodeList}
   */
  selectAll(selector, context = document) {
    return context.querySelectorAll(selector);
  },

  /**
   * Get element by ID (faster than querySelector)
   * @param {string} id - Element ID
   * @returns {Element|null}
   */
  byId(id) {
    return document.getElementById(id);
  },

  /**
   * Create new element
   * @param {string} tag - HTML tag name
   * @param {Object} attrs - Element attributes {class, id, data-*, etc}
   * @param {string} html - Optional inner HTML
   * @returns {Element}
   */
  create(tag, attrs = {}, html = '') {
    const el = document.createElement(tag);

    Object.entries(attrs).forEach(([key, value]) => {
      if (key === 'class') {
        el.className = value;
      } else if (key === 'html') {
        el.innerHTML = value;
      } else if (key.startsWith('data-')) {
        el.dataset[key.slice(5)] = value;
      } else {
        el.setAttribute(key, value);
      }
    });

    if (html) {
      el.innerHTML = html;
    }

    return el;
  },

  /**
   * Add event listener with optional delegation
   * @param {Element} el - Target element
   * @param {string} event - Event name
   * @param {string|Function} selectorOrCallback - CSS selector for delegation or callback
   * @param {Function} callback - Optional callback if selector provided
   */
  on(el, event, selectorOrCallback, callback) {
    if (typeof selectorOrCallback === 'function') {
      el.addEventListener(event, selectorOrCallback);
    } else {
      el.addEventListener(event, function(e) {
        if (e.target.matches(selectorOrCallback)) {
          callback.call(e.target, e);
        }
      });
    }
  },

  /**
   * Remove element from DOM
   * @param {Element} el
   */
  remove(el) {
    el.parentNode?.removeChild(el);
  },

  /**
   * Add class to element
   * @param {Element} el
   * @param {string} className
   */
  addClass(el, className) {
    el.classList.add(className);
  },

  /**
   * Remove class from element
   * @param {Element} el
   * @param {string} className
   */
  removeClass(el, className) {
    el.classList.remove(className);
  },

  /**
   * Toggle class on element
   * @param {Element} el
   * @param {string} className
   * @param {boolean} force - Optional force add/remove
   */
  toggleClass(el, className, force) {
    el.classList.toggle(className, force);
  },

  /**
   * Check if element has class
   * @param {Element} el
   * @param {string} className
   * @returns {boolean}
   */
  hasClass(el, className) {
    return el.classList.contains(className);
  },

  /**
   * Get or set element attribute
   * @param {Element} el
   * @param {string} name
   * @param {string} value - Optional, if set will be attribute value
   * @returns {string|undefined}
   */
  attr(el, name, value) {
    if (value === undefined) {
      return el.getAttribute(name);
    }
    el.setAttribute(name, value);
  },

  /**
   * Get or set element text content
   * @param {Element} el
   * @param {string} text - Optional text content
   * @returns {string}
   */
  text(el, text) {
    if (text === undefined) {
      return el.textContent;
    }
    el.textContent = text;
  },

  /**
   * Check if element matches selector
   * @param {Element} el
   * @param {string} selector
   * @returns {boolean}
   */
  matches(el, selector) {
    return el.matches(selector);
  },

  /**
   * Find closest parent matching selector
   * @param {Element} el
   * @param {string} selector
   * @returns {Element|null}
   */
  closest(el, selector) {
    return el.closest(selector);
  }
};

/**
 * String utility functions
 */
const StringUtils = {
  /**
   * Truncate string to max length with ellipsis
   * @param {string} str
   * @param {number} maxLength
   * @param {string} suffix - Optional suffix (default "...")
   * @returns {string}
   */
  truncate(str, maxLength, suffix = '...') {
    if (str.length <= maxLength) return str;
    return str.substring(0, maxLength - suffix.length) + suffix;
  },

  /**
   * Sanitize HTML string to prevent XSS
   * @param {string} html
   * @returns {string}
   */
  sanitize(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  },

  /**
   * Decode HTML entities
   * @param {string} html
   * @returns {string}
   */
  decodeHTML(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
  },

  /**
   * Slugify string (for URLs)
   * @param {string} str
   * @returns {string}
   */
  slug(str) {
    return str
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/[\s_]+/g, '-')
      .replace(/^-+|-+$/g, '');
  },

  /**
   * Capitalize first letter
   * @param {string} str
   * @returns {string}
   */
  capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  },

  /**
   * Format bytes to human readable
   * @param {number} bytes
   * @param {number} decimals
   * @returns {string}
   */
  formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
  }
};

/**
 * Date/Time utility functions
 */
const DateUtils = {
  /**
   * Format date to readable string
   * @param {Date|string} date
   * @param {string} format - Format string (Y=year, m=month, d=day, H=hour, M=minute, S=second)
   * @returns {string}
   */
  format(date, format = 'Y-m-d H:M:S') {
    const d = typeof date === 'string' ? new Date(date) : date;

    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');

    return format
      .replace('Y', year)
      .replace('m', month)
      .replace('d', day)
      .replace('H', hours)
      .replace('M', minutes)
      .replace('S', seconds);
  },

  /**
   * Get relative time (e.g., "2 hours ago")
   * @param {Date|string} date
   * @returns {string}
   */
  relative(date) {
    const d = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffMs = now - d;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    if (diffDays < 30) return `${Math.floor(diffDays / 7)}w ago`;
    if (diffDays < 365) return `${Math.floor(diffDays / 30)}mo ago`;
    return `${Math.floor(diffDays / 365)}y ago`;
  },

  /**
   * Parse ISO date string
   * @param {string} isoString
   * @returns {Date}
   */
  parse(isoString) {
    return new Date(isoString);
  },

  /**
   * Get ISO date string
   * @param {Date} date
   * @returns {string}
   */
  toISO(date = new Date()) {
    return date.toISOString();
  }
};

/**
 * Array utility functions
 */
const ArrayUtils = {
  /**
   * Check if value is in array
   * @param {Array} arr
   * @param {*} value
   * @returns {boolean}
   */
  includes(arr, value) {
    return arr.includes(value);
  },

  /**
   * Remove value from array
   * @param {Array} arr
   * @param {*} value
   * @returns {Array}
   */
  remove(arr, value) {
    return arr.filter(item => item !== value);
  },

  /**
   * Get unique values from array
   * @param {Array} arr
   * @returns {Array}
   */
  unique(arr) {
    return [...new Set(arr)];
  },

  /**
   * Group array by key
   * @param {Array} arr
   * @param {Function|string} keyOrFn - Function or property name
   * @returns {Object}
   */
  groupBy(arr, keyOrFn) {
    const fn = typeof keyOrFn === 'function' ? keyOrFn : item => item[keyOrFn];
    return arr.reduce((groups, item) => {
      const key = fn(item);
      groups[key] = groups[key] || [];
      groups[key].push(item);
      return groups;
    }, {});
  },

  /**
   * Flatten nested array
   * @param {Array} arr
   * @param {number} depth - Depth to flatten (default Infinity)
   * @returns {Array}
   */
  flatten(arr, depth = Infinity) {
    return arr.reduce((flat, item) => {
      return flat.concat(depth > 1 && Array.isArray(item) ? this.flatten(item, depth - 1) : item);
    }, []);
  }
};

/**
 * Storage utility functions
 */
const StorageUtils = {
  /**
   * Get value from localStorage
   * @param {string} key
   * @param {*} defaultValue
   * @returns {*}
   */
  get(key, defaultValue = null) {
    try {
      const value = localStorage.getItem(key);
      return value ? JSON.parse(value) : defaultValue;
    } catch (e) {
      return defaultValue;
    }
  },

  /**
   * Set value in localStorage
   * @param {string} key
   * @param {*} value
   */
  set(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
      console.error('LocalStorage error:', e);
    }
  },

  /**
   * Remove value from localStorage
   * @param {string} key
   */
  remove(key) {
    localStorage.removeItem(key);
  },

  /**
   * Clear all localStorage
   */
  clear() {
    localStorage.clear();
  }
};

export { DOM, StringUtils, DateUtils, ArrayUtils, StorageUtils };
