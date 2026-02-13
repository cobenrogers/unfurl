# JavaScript Utilities - Implementation Examples

This document provides real-world examples of using the JavaScript utilities in the Unfurl project.

---

## Example 1: User Registration Form

Complete form validation, submission, and notifications.

```html
<form id="register-form">
  <div class="form-group">
    <label for="name">Name</label>
    <input type="text" id="name" name="name" data-label="Name">
  </div>

  <div class="form-group">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" data-label="Email">
  </div>

  <div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" data-label="Password">
  </div>

  <div class="form-group">
    <label for="confirm">Confirm Password</label>
    <input type="password" id="confirm" name="confirm" data-label="Confirm Password">
  </div>

  <button type="submit" class="btn btn-primary">Register</button>
</form>

<script type="module">
  import { FormValidator, FormSerializer, CSRFToken } from './assets/js/forms.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';

  const form = document.getElementById('register-form');
  const validator = new FormValidator();

  // Add CSRF token to form
  CSRFToken.addToForm(form);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Validate
    const errors = validator.validate(form, {
      name: ['required', 'min:2', 'max:50'],
      email: ['required', 'email'],
      password: ['required', 'min:8'],
      confirm: ['required', 'match:password']
    });

    if (Object.keys(errors).length > 0) {
      validator.displayErrors(form, errors);
      Notify.warning('Please fix the errors above');
      return;
    }

    // Serialize and submit
    const data = FormSerializer.toObject(form);

    try {
      Notify.info('Creating account...', 0); // No auto-dismiss
      const result = await api.post('/api/auth/register', data);
      Notify.success('Account created! Redirecting...');
      setTimeout(() => {
        window.location.href = '/dashboard';
      }, 1000);
    } catch (error) {
      // Error already notified by api.post()
    }
  });
</script>
```

---

## Example 2: Article Bulk Delete with Confirmation

Delete multiple articles with confirmation dialog.

```html
<table data-bulk>
  <thead>
    <tr>
      <th><input type="checkbox" data-select-all"></th>
      <th>Title</th>
      <th>Published</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody id="articles-tbody">
    <!-- Articles populated by JavaScript -->
  </tbody>
</table>

<div class="bulk-actions" style="display: none;">
  <p>Selected: <span data-selection-count>0</span> item(s)</p>
  <button type="button" data-bulk-action="archive">Archive Selected</button>
  <button type="button" data-bulk-action="delete" class="btn-danger">Delete Selected</button>
</div>

<script type="module">
  import { BulkActions } from './assets/js/bulk-actions.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';
  import { DateUtils } from './assets/js/utils.js';

  // Sample data
  const articles = [
    {id: 1, title: 'Breaking News', published_at: '2026-02-07T10:00:00Z', status: 'published'},
    {id: 2, title: 'Feature Article', published_at: '2026-02-06T15:30:00Z', status: 'published'},
    {id: 3, title: 'Draft Post', published_at: null, status: 'draft'}
  ];

  // Populate table
  const tbody = document.getElementById('articles-tbody');
  articles.forEach(article => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td><input type="checkbox" data-item="${article.id}"></td>
      <td>${article.title}</td>
      <td>${article.published_at ? DateUtils.relative(article.published_at) : 'Not published'}</td>
      <td><span class="badge badge-${article.status}">${article.status}</span></td>
    `;
    tbody.appendChild(row);
  });

  // Initialize bulk actions
  const bulk = new BulkActions({
    confirmAction: true,
    onBulkAction: async ({action, ids}) => {
      try {
        if (action === 'delete') {
          await api.post('/api/articles/bulk-delete', {ids});
          Notify.success(`Deleted ${ids.length} article(s)`);
        } else if (action === 'archive') {
          await api.post('/api/articles/bulk-archive', {ids});
          Notify.success(`Archived ${ids.length} article(s)`);
        }
        // Reload table
        setTimeout(() => location.reload(), 1000);
      } catch (error) {
        // Error already notified
      }
    }
  });
</script>
```

---

## Example 3: Live Search with Results

Search with API calls and formatted results.

```html
<div class="search-widget">
  <input type="text" id="search" placeholder="Search articles..." class="search-input">
  <div id="results" class="search-results" style="display: none;"></div>
</div>

<script type="module">
  import { DOM, StringUtils, DateUtils } from './assets/js/utils.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';

  const searchInput = document.getElementById('search');
  const resultsDiv = document.getElementById('results');
  let searchTimeout;

  searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();

    if (query.length < 2) {
      resultsDiv.style.display = 'none';
      return;
    }

    // Debounce API call
    searchTimeout = setTimeout(async () => {
      try {
        const results = await api.get('/api/articles/search', {q: query, limit: 5});

        if (results.length === 0) {
          resultsDiv.innerHTML = '<p class="no-results">No articles found</p>';
        } else {
          resultsDiv.innerHTML = results
            .map(article => `
              <a href="/articles/${article.id}" class="search-result">
                <div class="search-result-title">${StringUtils.sanitize(article.title)}</div>
                <div class="search-result-meta">
                  ${StringUtils.truncate(article.description, 60)}
                  <span class="search-result-date">${DateUtils.relative(article.created_at)}</span>
                </div>
              </a>
            `)
            .join('');
        }

        resultsDiv.style.display = 'block';
      } catch (error) {
        // Error already notified
      }
    }, 300);
  });

  // Close results when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-widget')) {
      resultsDiv.style.display = 'none';
    }
  });
</script>
```

---

## Example 4: Settings Form with Validation and Save

Complex form with multiple field types.

```html
<form id="settings-form">
  <h2>Site Settings</h2>

  <div class="form-group">
    <label for="site-name">Site Name</label>
    <input type="text" id="site-name" name="site_name" data-label="Site Name">
  </div>

  <div class="form-group">
    <label for="site-url">Site URL</label>
    <input type="url" id="site-url" name="site_url" data-label="Site URL">
  </div>

  <div class="form-group">
    <label for="items-per-page">Items Per Page</label>
    <input type="number" id="items-per-page" name="items_per_page" min="1" max="100" data-label="Items Per Page">
  </div>

  <div class="form-group">
    <label for="auto-cleanup">
      <input type="checkbox" id="auto-cleanup" name="auto_cleanup">
      Enable Auto Cleanup
    </label>
  </div>

  <div class="form-group">
    <label for="retention-days">Retention Days</label>
    <input type="number" id="retention-days" name="retention_days" min="0" data-label="Retention Days">
  </div>

  <button type="submit" class="btn btn-primary">Save Settings</button>
</form>

<script type="module">
  import { FormValidator, FormSerializer } from './assets/js/forms.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';
  import { StorageUtils } from './assets/js/utils.js';

  const form = document.getElementById('settings-form');
  const validator = new FormValidator();

  // Load initial values
  async function loadSettings() {
    try {
      const settings = await api.get('/api/settings');
      FormSerializer.fromObject(form, settings);
      // Store last known values
      StorageUtils.set('settings-backup', settings);
    } catch (error) {
      Notify.error('Failed to load settings');
    }
  }

  // Handle form submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const errors = validator.validate(form, {
      site_name: ['required', 'min:3'],
      site_url: ['required', 'url'],
      items_per_page: ['required', 'number'],
      retention_days: ['number']
    });

    if (Object.keys(errors).length > 0) {
      validator.displayErrors(form, errors);
      return;
    }

    const data = FormSerializer.toObject(form);

    try {
      await api.put('/api/settings', data);
      Notify.success('Settings saved successfully');
      StorageUtils.set('settings-backup', data);
    } catch (error) {
      // Restore from backup on error
      const backup = StorageUtils.get('settings-backup');
      if (backup) {
        FormSerializer.fromObject(form, backup);
      }
    }
  });

  // Load on init
  loadSettings();
</script>
```

---

## Example 5: Article Editor with Auto-Save

Editor with debounced save to prevent data loss.

```html
<form id="article-editor">
  <input type="hidden" name="id" value="">

  <div class="form-group">
    <label for="title">Title</label>
    <input type="text" id="title" name="title" class="editor-field" data-label="Title">
  </div>

  <div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" class="editor-field" rows="3" data-label="Description"></textarea>
  </div>

  <div class="form-group">
    <label for="content">Content</label>
    <textarea id="content" name="content" class="editor-field" rows="10" data-label="Content"></textarea>
  </div>

  <div class="editor-status">
    <span id="save-status">Saved</span>
  </div>

  <button type="submit" class="btn btn-primary">Publish</button>
</form>

<script type="module">
  import { DOM, StringUtils } from './assets/js/utils.js';
  import { FormValidator, FormSerializer } from './assets/js/forms.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';

  const form = document.getElementById('article-editor');
  const statusEl = document.getElementById('save-status');
  const validator = new FormValidator();
  const articleId = form.elements['id'].value;

  let autoSaveTimeout;
  let hasChanges = false;

  // Mark changes
  DOM.selectAll('.editor-field', form).forEach(field => {
    field.addEventListener('change', () => {
      hasChanges = true;
      clearTimeout(autoSaveTimeout);
      statusEl.textContent = 'Unsaved changes...';
      statusEl.className = 'unsaved';
    });

    // Debounced auto-save
    field.addEventListener('input', () => {
      clearTimeout(autoSaveTimeout);
      autoSaveTimeout = setTimeout(autoSave, 1000);
    });
  });

  // Auto-save function
  async function autoSave() {
    if (!hasChanges) return;

    const data = FormSerializer.toObject(form);

    try {
      await api.put(`/api/articles/${articleId}`, data, false); // false = no notification
      hasChanges = false;
      statusEl.textContent = 'Saved';
      statusEl.className = 'saved';
    } catch (error) {
      statusEl.textContent = 'Save failed';
      statusEl.className = 'error';
    }
  }

  // Publish
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const errors = validator.validate(form, {
      title: ['required', 'min:5'],
      description: ['required', 'min:10'],
      content: ['required', 'min:50']
    });

    if (Object.keys(errors).length > 0) {
      validator.displayErrors(form, errors);
      return;
    }

    const data = FormSerializer.toObject(form);
    data.published_at = new Date().toISOString();

    try {
      await api.put(`/api/articles/${articleId}`, data);
      Notify.success('Article published!');
      setTimeout(() => {
        window.location.href = `/articles/${articleId}`;
      }, 1000);
    } catch (error) {
      // Error already notified
    }
  });

  // Warn before leaving
  window.addEventListener('beforeunload', (e) => {
    if (hasChanges) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
</script>
```

---

## Example 6: Data Table with Inline Editing

Edit table cells inline with validation.

```html
<table id="data-table">
  <thead>
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<script type="module">
  import { DOM, StringUtils } from './assets/js/utils.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';
  import { FormValidator } from './assets/js/forms.js';

  const table = document.getElementById('data-table');
  const tbody = table.querySelector('tbody');
  const validator = new FormValidator();

  // Load data
  async function loadData() {
    try {
      const items = await api.get('/api/items');
      renderTable(items);
    } catch (error) {
      Notify.error('Failed to load data');
    }
  }

  function renderTable(items) {
    tbody.innerHTML = items
      .map(item => `
        <tr data-id="${item.id}">
          <td>${item.id}</td>
          <td class="editable-cell" data-field="name">${StringUtils.sanitize(item.name)}</td>
          <td class="editable-cell" data-field="email">${StringUtils.sanitize(item.email)}</td>
          <td class="status">${item.status}</td>
          <td>
            <button class="btn-delete" title="Delete">Delete</button>
          </td>
        </tr>
      `)
      .join('');

    attachEventListeners();
  }

  function attachEventListeners() {
    // Edit cells
    DOM.on(tbody, 'click', '.editable-cell', (e) => {
      editCell(e.target);
    });

    // Delete
    DOM.on(tbody, 'click', '.btn-delete', (e) => {
      const row = DOM.closest(e.target, 'tr');
      const id = DOM.attr(row, 'data-id');
      deleteItem(id);
    });
  }

  function editCell(cell) {
    if (DOM.hasClass(cell, 'editing')) return;

    const field = DOM.attr(cell, 'data-field');
    const value = cell.textContent;

    const input = DOM.create('input', {
      type: field === 'email' ? 'email' : 'text',
      value: value,
      class: 'cell-input'
    });

    DOM.addClass(cell, 'editing');
    cell.textContent = '';
    cell.appendChild(input);
    input.focus();
    input.select();

    const save = async () => {
      const newValue = input.value.trim();

      // Validate
      const errors = validator.validateField(input, ['required', field === 'email' ? 'email' : '']);
      if (errors.length > 0) {
        Notify.error(errors[0]);
        return;
      }

      DOM.removeClass(cell, 'editing');
      const row = DOM.closest(cell, 'tr');
      const id = DOM.attr(row, 'data-id');

      try {
        await api.put(`/api/items/${id}`, {[field]: newValue});
        DOM.text(cell, newValue);
        Notify.success('Updated');
      } catch (error) {
        DOM.text(cell, value); // Revert
      }
    };

    input.addEventListener('blur', save);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') save();
      if (e.key === 'Escape') {
        DOM.removeClass(cell, 'editing');
        DOM.text(cell, value);
      }
    });
  }

  async function deleteItem(id) {
    if (!confirm('Delete this item?')) return;

    try {
      await api.delete(`/api/items/${id}`);
      const row = DOM.select(`tr[data-id="${id}"]`, table);
      DOM.remove(row);
      Notify.success('Deleted');
    } catch (error) {
      // Error already notified
    }
  }

  // Load on init
  loadData();
</script>
```

---

## Example 7: Modal with Form

Create and manage modal dialogs.

```html
<button id="open-modal" class="btn btn-primary">Add New Item</button>

<dialog id="item-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add New Item</h2>
      <button type="button" class="modal-close">×</button>
    </div>

    <form id="item-form">
      <div class="form-group">
        <label for="item-name">Name</label>
        <input type="text" id="item-name" name="name" data-label="Name">
      </div>

      <div class="form-group">
        <label for="item-description">Description</label>
        <textarea id="item-description" name="description" rows="3" data-label="Description"></textarea>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-action="cancel">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</dialog>

<script type="module">
  import { DOM } from './assets/js/utils.js';
  import { FormValidator, FormSerializer } from './assets/js/forms.js';
  import { api } from './assets/js/api.js';
  import { Notify } from './assets/js/notifications.js';

  const modal = document.getElementById('item-modal');
  const openBtn = document.getElementById('open-modal');
  const closeBtn = DOM.select('.modal-close', modal);
  const cancelBtn = DOM.select('[data-action="cancel"]', modal);
  const form = document.getElementById('item-form');
  const validator = new FormValidator();

  // Open modal
  openBtn.addEventListener('click', () => {
    FormSerializer.clear(form);
    validator.clearErrors(form);
    modal.showModal();
  });

  // Close modal
  const close = () => {
    modal.close();
  };
  closeBtn.addEventListener('click', close);
  cancelBtn.addEventListener('click', close);

  // Submit
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const errors = validator.validate(form, {
      name: ['required', 'min:2'],
      description: ['required', 'min:5']
    });

    if (Object.keys(errors).length > 0) {
      validator.displayErrors(form, errors);
      return;
    }

    const data = FormSerializer.toObject(form);

    try {
      await api.post('/api/items', data);
      Notify.success('Item created');
      close();
      // Reload table or refresh data
    } catch (error) {
      // Error already notified
    }
  });

  // Close modal on backdrop click
  modal.addEventListener('click', (e) => {
    if (e.target === modal) close();
  });
</script>
```

---

## Tips for Using These Examples

1. **Import paths** - Adjust paths based on your HTML file location
2. **API endpoints** - Replace example endpoints with your actual API routes
3. **CSS classes** - Add corresponding CSS for styling (see project's CSS files)
4. **Error handling** - The API module handles errors automatically
5. **CSRF tokens** - Automatically included if token exists in page
6. **Debouncing** - Use timeouts to prevent excessive API calls

---

*Last Updated: 2026-02-07*
