# JavaScript Utilities Documentation

## Overview

The Unfurl project includes a comprehensive set of vanilla JavaScript utility modules for building interactive web interfaces. All modules are written in ES6+, fully documented with JSDoc, and designed to work without external frameworks.

**Location**: `/public/assets/js/`

---

## Table of Contents

1. [utils.js](#utilsjs) - DOM, string, date, and storage helpers
2. [api.js](#apijs) - Fetch wrapper with CSRF token auto-inclusion
3. [notifications.js](#notificationsjs) - Toast notification system
4. [forms.js](#formsjs) - Form validation and serialization
5. [bulk-actions.js](#bulk-actionsjs) - Bulk selection and actions

---

## utils.js

Generic utility functions organized into logical groups.

### DOM Helpers

Fast, chainable DOM manipulation functions.

#### DOM.select(selector, context)
Select a single element using CSS selector.

```javascript
import { DOM } from './utils.js';

const element = DOM.select('.my-button');
const child = DOM.select('.child', parentElement);
```

#### DOM.selectAll(selector, context)
Select multiple elements.

```javascript
const items = DOM.selectAll('.item');
items.forEach(item => console.log(item));
```

#### DOM.byId(id)
Select element by ID (faster than querySelector).

```javascript
const header = DOM.byId('header');
```

#### DOM.create(tag, attrs, html)
Create new element with attributes.

```javascript
const button = DOM.create('button', {
  class: 'btn btn-primary',
  id: 'submit-btn',
  'data-action': 'submit'
}, 'Click me');

const input = DOM.create('input', {
  type: 'text',
  name: 'email',
  placeholder: 'Enter email'
});
```

#### DOM.on(el, event, selectorOrCallback, callback)
Add event listener with optional delegation.

```javascript
// Direct listener
DOM.on(button, 'click', () => {
  console.log('Clicked!');
});

// Event delegation
DOM.on(table, 'click', 'button.delete', (e) => {
  console.log('Delete button clicked');
});
```

#### DOM.addClass(el, className)
#### DOM.removeClass(el, className)
#### DOM.toggleClass(el, className, force)
#### DOM.hasClass(el, className)
Class management.

```javascript
DOM.addClass(element, 'active');
if (DOM.hasClass(element, 'active')) {
  DOM.removeClass(element, 'active');
}
DOM.toggleClass(element, 'disabled');
```

#### DOM.attr(el, name, value)
Get/set attributes.

```javascript
const href = DOM.attr(link, 'href');
DOM.attr(link, 'href', 'https://example.com');
```

#### DOM.text(el, text)
Get/set text content.

```javascript
const label = DOM.text(element); // Get
DOM.text(element, 'New text'); // Set
```

#### DOM.matches(el, selector)
Check if element matches selector.

```javascript
if (DOM.matches(e.target, '.button')) {
  // Handle button
}
```

#### DOM.closest(el, selector)
Find closest parent matching selector.

```javascript
const row = DOM.closest(cell, 'tr');
const form = DOM.closest(input, 'form');
```

### String Helpers

#### StringUtils.truncate(str, maxLength, suffix)
Truncate string with ellipsis.

```javascript
import { StringUtils } from './utils.js';

const short = StringUtils.truncate('Long text...', 10); // 'Long te...'
const custom = StringUtils.truncate('Long text', 10, '-->'); // 'Long t-->'
```

#### StringUtils.sanitize(html)
Sanitize HTML to prevent XSS.

```javascript
const safe = StringUtils.sanitize('<script>alert("xss")</script>');
// Returns: &lt;script&gt;alert("xss")&lt;/script&gt;
```

#### StringUtils.decodeHTML(html)
Decode HTML entities.

```javascript
const text = StringUtils.decodeHTML('Hello &amp; goodbye');
// Returns: 'Hello & goodbye'
```

#### StringUtils.slug(str)
Convert string to URL-safe slug.

```javascript
const slug = StringUtils.slug('Hello World!');
// Returns: 'hello-world'
```

#### StringUtils.capitalize(str)
Capitalize first letter.

```javascript
const text = StringUtils.capitalize('hello');
// Returns: 'Hello'
```

#### StringUtils.formatBytes(bytes, decimals)
Format bytes to human-readable size.

```javascript
const size = StringUtils.formatBytes(1024 * 1024); // '1 MB'
const precise = StringUtils.formatBytes(1536, 3); // '1.5 KB'
```

### Date Helpers

#### DateUtils.format(date, format)
Format date with custom format string.

```javascript
import { DateUtils } from './utils.js';

const date = new Date('2026-02-07T15:30:45Z');
const formatted = DateUtils.format(date, 'Y-m-d H:M:S');
// Returns: '2026-02-07 15:30:45'
```

**Format codes:**
- `Y` - Full year (2026)
- `m` - Month with leading zero (02)
- `d` - Day with leading zero (07)
- `H` - Hour with leading zero (15)
- `M` - Minute with leading zero (30)
- `S` - Second with leading zero (45)

#### DateUtils.relative(date)
Get relative time (e.g., "2 hours ago").

```javascript
const time = DateUtils.relative(new Date('2026-02-07T10:00:00Z'));
// Returns: '2h ago'
```

#### DateUtils.parse(isoString)
Parse ISO date string.

```javascript
const date = DateUtils.parse('2026-02-07T15:30:45Z');
```

#### DateUtils.toISO(date)
Get ISO date string.

```javascript
const iso = DateUtils.toISO(new Date());
// Returns: '2026-02-07T08:00:00.000Z'
```

### Array Helpers

#### ArrayUtils.unique(arr)
Get unique values.

```javascript
import { ArrayUtils } from './utils.js';

const unique = ArrayUtils.unique([1, 2, 2, 3, 3, 3]);
// Returns: [1, 2, 3]
```

#### ArrayUtils.groupBy(arr, keyOrFn)
Group array by key or function.

```javascript
const users = [{id: 1, role: 'admin'}, {id: 2, role: 'user'}];
const grouped = ArrayUtils.groupBy(users, 'role');
// Returns: {admin: [{...}], user: [{...}]}
```

#### ArrayUtils.flatten(arr, depth)
Flatten nested array.

```javascript
const nested = [[1, 2], [3, [4, 5]]];
const flat = ArrayUtils.flatten(nested);
// Returns: [1, 2, 3, 4, 5]
```

### Storage Helpers

#### StorageUtils.get(key, defaultValue)
Get from localStorage with JSON parsing.

```javascript
import { StorageUtils } from './utils.js';

const user = StorageUtils.get('user', {});
const theme = StorageUtils.get('theme', 'light');
```

#### StorageUtils.set(key, value)
Save to localStorage with JSON stringification.

```javascript
StorageUtils.set('user', {id: 1, name: 'John'});
StorageUtils.set('preferences', {theme: 'dark'});
```

#### StorageUtils.remove(key)
Remove from localStorage.

```javascript
StorageUtils.remove('user');
```

#### StorageUtils.clear()
Clear all localStorage.

```javascript
StorageUtils.clear();
```

---

## api.js

Fetch wrapper with automatic CSRF token inclusion and error handling.

### Basic Usage

```javascript
import { api } from './api.js';

// GET request
const users = await api.get('/api/users');

// POST request
const result = await api.post('/api/users', {
  name: 'John',
  email: 'john@example.com'
});

// PUT request
await api.put('/api/users/1', {name: 'Jane'});

// DELETE request
await api.delete('/api/users/1');
```

### CSRF Token Handling

The API client automatically includes the CSRF token in request headers. The token is retrieved from:

1. Meta tag: `<meta name="csrf-token" content="token-value">`
2. Hidden input: `<input type="hidden" name="csrf_token" value="token-value">`

**No manual configuration needed** - the token is automatically found and included.

### Query Parameters

```javascript
// GET with parameters
const users = await api.get('/api/users', {
  page: 1,
  limit: 10,
  search: 'john'
});
// URL becomes: /api/users?page=1&limit=10&search=john
```

### Error Handling

The `SafeAPI` instance automatically shows error notifications:

```javascript
try {
  const data = await api.post('/api/data', {name: 'test'});
} catch (error) {
  // Error already notified via Notify.error()
  console.error(error);
}
```

Disable auto-notification:

```javascript
// Pass false as last parameter
const result = await api.post('/api/data', data, false);
```

### Error Types

| Status | Message |
|--------|---------|
| 401 | Session expired. Please log in again. |
| 403 | You do not have permission to perform this action. |
| 404 | Resource not found. |
| 408 | Request timeout. |
| 5xx | Server error. Please try again later. |

### Custom Configuration

For advanced usage, use the base `API` class:

```javascript
import { API } from './api.js';

const api = new API();
api.defaultTimeout = 60000; // 60 seconds

const response = await api.request('/api/data', {
  method: 'POST',
  body: {key: 'value'},
  headers: {'X-Custom': 'header-value'}
});
```

---

## notifications.js

Toast notification system for user feedback.

### Basic Usage

```javascript
import { Notify } from './notifications.js';

Notify.success('Item created successfully!');
Notify.error('Failed to save changes');
Notify.info('Loading...');
Notify.warning('This action cannot be undone');
```

### Auto-Dismiss Duration

```javascript
// Auto-dismiss after 5 seconds (default)
Notify.success('Saved!');

// Custom duration (milliseconds)
Notify.info('Loading...', 3000);

// No auto-dismiss
Notify.warning('Keep this visible', 0);
```

### Styling

Notifications automatically style themselves based on type:

- **Success**: Green border and text
- **Error**: Red border and text
- **Info**: Blue border and text
- **Warning**: Orange border and text

### Multiple Notifications

Multiple notifications stack vertically:

```javascript
Notify.info('Operation started...');
setTimeout(() => Notify.success('Operation complete!'), 2000);
```

### Clear All

```javascript
Notify.clear(); // Remove all notifications
```

### Dismissing Individual Notifications

Users can dismiss notifications by clicking the × button. Programmatically:

```javascript
const toast = Notify.success('Item saved');
setTimeout(() => Notify.remove(toast), 3000); // Auto-remove after 3s
```

---

## forms.js

Form validation, serialization, and CSRF utilities.

### Form Validation

#### Basic Validation

```javascript
import { FormValidator } from './forms.js';

const validator = new FormValidator();
const form = document.querySelector('form');

const errors = validator.validate(form, {
  name: ['required', 'min:2', 'max:50'],
  email: ['required', 'email'],
  password: ['required', 'min:8'],
  confirm: ['required', 'match:password']
});

if (Object.keys(errors).length > 0) {
  validator.displayErrors(form, errors);
} else {
  // Submit form
}
```

#### Validation Rules

- `required` - Field must not be empty
- `email` - Must be valid email format
- `url` - Must be valid URL
- `min:N` - Minimum N characters
- `max:N` - Maximum N characters
- `match:fieldName` - Must match another field
- `number` - Must be a number
- `phone` - Must be valid phone number
- `pattern:regex` - Must match regex pattern

#### Custom Messages

```javascript
validator.messages.required = '{field} cannot be blank';
validator.messages.email = '{field} must be a valid email address';
```

#### Validate Single Field

```javascript
const field = form.elements['email'];
const errors = validator.validateField(field, ['required', 'email']);
```

#### Clear Validation Errors

```javascript
validator.clearErrors(form);
```

### Form Serialization

#### Serialize to Object

```javascript
import { FormSerializer } from './forms.js';

const form = document.querySelector('form');
const data = FormSerializer.toObject(form);
// {name: 'John', email: 'john@example.com', ...}
```

#### Serialize to FormData

Useful for file uploads:

```javascript
const formData = FormSerializer.toFormData(form);
// Use with fetch:
await fetch('/api/upload', {
  method: 'POST',
  body: formData
});
```

#### Serialize to URL Encoded

```javascript
const encoded = FormSerializer.toUrlEncoded(form);
// 'name=John&email=john@example.com'
```

#### Populate Form from Object

```javascript
FormSerializer.fromObject(form, {
  name: 'Jane',
  email: 'jane@example.com',
  subscribe: true
});
```

#### Clear Form

```javascript
FormSerializer.clear(form); // Same as form.reset()
```

### CSRF Token Management

#### Get Token

```javascript
import { CSRFToken } from './forms.js';

const token = CSRFToken.getToken();
```

#### Add to Form

```javascript
const form = document.querySelector('form');
CSRFToken.addToForm(form);
// Adds: <input type="hidden" name="csrf_token" value="...">
```

#### Get Headers Object

For API requests:

```javascript
const headers = CSRFToken.getHeaders();
const response = await fetch('/api/data', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    ...headers
  },
  body: JSON.stringify(data)
});
```

#### Refresh Token

```javascript
const newToken = await CSRFToken.refresh();
```

### Complete Example

```javascript
import { FormValidator, FormSerializer } from './forms.js';
import { Notify } from './notifications.js';
import { api } from './api.js';

const form = document.querySelector('form');
const validator = new FormValidator();

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  // Validate
  const errors = validator.validate(form, {
    name: ['required', 'min:2'],
    email: ['required', 'email']
  });

  if (Object.keys(errors).length > 0) {
    validator.displayErrors(form, errors);
    return;
  }

  // Serialize and submit
  const data = FormSerializer.toObject(form);
  try {
    const result = await api.post('/api/users', data);
    Notify.success('User created successfully');
    form.reset();
  } catch (error) {
    // Error already notified
  }
});
```

---

## bulk-actions.js

Manage bulk selection and actions in data tables.

### Basic Setup

```javascript
import { BulkActions } from './bulk-actions.js';

const bulk = new BulkActions({
  tableSelector: 'table[data-bulk]',
  selectAllSelector: 'input[data-select-all]',
  itemCheckboxSelector: 'input[data-item]',
  bulkActionButtons: '[data-bulk-action]'
});
```

### HTML Structure

```html
<table data-bulk>
  <thead>
    <tr>
      <th><input type="checkbox" data-select-all"></th>
      <th>Name</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><input type="checkbox" data-item="1"></td>
      <td>Item 1</td>
    </tr>
    <tr>
      <td><input type="checkbox" data-item="2"></td>
      <td>Item 2</td>
    </tr>
  </tbody>
</table>

<div class="bulk-actions">
  <button type="button" data-bulk-action="delete">Delete Selected</button>
  <button type="button" data-bulk-action="archive">Archive Selected</button>
</div>
```

### Handling Bulk Actions

```javascript
const bulk = new BulkActions({
  onBulkAction: async ({action, ids, count}) => {
    console.log(`Action: ${action}, Items: ${count}`);

    if (action === 'delete') {
      await api.post('/api/items/bulk-delete', {ids});
      Notify.success(`${count} items deleted`);
      bulk.clear(); // Clear selection
    }
  }
});
```

### Selection Change Callback

```javascript
const bulk = new BulkActions({
  onSelectionChange: ({selectedIds, count}) => {
    console.log(`Selected ${count} items`);
  }
});
```

### Programmatic Control

#### Get Selected IDs

```javascript
const ids = bulk.getSelectedIds();
const count = bulk.getCount();
```

#### Select/Deselect

```javascript
bulk.select('1');           // Select item 1
bulk.deselect('1');         // Deselect item 1
bulk.toggle('1');           // Toggle item 1
bulk.selectAll();           // Select all items
bulk.deselectAll();         // Deselect all items
bulk.clear();               // Clear all (same as deselectAll)
```

#### Check Selection

```javascript
if (bulk.isSelected('1')) {
  console.log('Item 1 is selected');
}
```

### Confirmation Dialog

By default, bulk actions require confirmation:

```javascript
const bulk = new BulkActions({
  confirmAction: true, // Show confirmation (default)
  onBulkAction: ({action, ids}) => {
    // Only called after user confirms
  }
});
```

Disable confirmation:

```javascript
const bulk = new BulkActions({
  confirmAction: false
});
```

### Complete Example

```html
<table data-bulk>
  <thead>
    <tr>
      <th><input type="checkbox" data-select-all"></th>
      <th>ID</th>
      <th>Name</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><input type="checkbox" data-item="1"></td>
      <td>1</td>
      <td>Article 1</td>
    </tr>
    <tr>
      <td><input type="checkbox" data-item="2"></td>
      <td>2</td>
      <td>Article 2</td>
    </tr>
  </tbody>
</table>

<script type="module">
  import { BulkActions } from './bulk-actions.js';
  import { api } from './api.js';
  import { Notify } from './notifications.js';

  const bulk = new BulkActions({
    onBulkAction: async ({action, ids}) => {
      if (action === 'delete') {
        await api.post('/api/articles/bulk-delete', {ids});
        Notify.success(`Deleted ${ids.length} articles`);
        bulk.clear();
        location.reload(); // Refresh page
      }
    }
  });
</script>
```

---

## Module Imports

All modules use ES6 `import/export`. To use them in your HTML:

```html
<script type="module">
  import { DOM, StringUtils, DateUtils } from './utils.js';
  import { api } from './api.js';
  import { Notify } from './notifications.js';
  import { FormValidator, FormSerializer } from './forms.js';
  import { BulkActions } from './bulk-actions.js';

  // Your code here
</script>
```

Or import at the top of another module:

```javascript
import { api } from './api.js';
import { Notify } from './notifications.js';

export async function submitData(data) {
  const result = await api.post('/api/data', data);
  Notify.success('Data saved');
  return result;
}
```

---

## Best Practices

### 1. Use DOM Helpers for Consistency

```javascript
// Good
const button = DOM.select('.button');
DOM.on(button, 'click', handleClick);

// Avoid
const button = document.querySelector('.button');
button.addEventListener('click', handleClick);
```

### 2. Validate Forms Before Submission

```javascript
form.addEventListener('submit', (e) => {
  e.preventDefault();
  const errors = validator.validate(form, rules);
  if (Object.keys(errors).length > 0) {
    validator.displayErrors(form, errors);
    return;
  }
  // Submit
});
```

### 3. Always Handle API Errors

```javascript
try {
  const data = await api.post('/api/data', payload);
} catch (error) {
  // Error already notified by SafeAPI
  console.error('Request failed:', error);
}
```

### 4. Use Relative Dates for User Feedback

```javascript
const createdAt = article.created_at;
console.log(DateUtils.relative(createdAt)); // "2h ago"
```

### 5. Sanitize User Input

```javascript
const unsafe = userInput;
const safe = StringUtils.sanitize(unsafe);
element.innerHTML = safe;
```

---

## Browser Support

All modules require modern browser features:

- ES6 (Classes, Arrow Functions, Destructuring)
- Fetch API
- LocalStorage API
- Modern DOM APIs (querySelectorAll, classList, etc.)

**Minimum versions:**
- Chrome 55+
- Firefox 52+
- Safari 11+
- Edge 15+
- IE: Not supported

---

## File Sizes

| File | Size | Lines |
|------|------|-------|
| utils.js | 9.9 KB | ~315 |
| api.js | 6.7 KB | ~245 |
| notifications.js | 7.0 KB | ~255 |
| forms.js | 9.7 KB | ~390 |
| bulk-actions.js | 10 KB | ~380 |
| **Total** | **42.3 KB** | **~1,585** |

---

## Testing

All utilities can be tested in the browser console:

```javascript
// Test DOM helpers
const el = DOM.create('div', {class: 'test'}, 'Hello');
DOM.addClass(el, 'active');
console.log(DOM.hasClass(el, 'active')); // true

// Test string utils
console.log(StringUtils.truncate('Hello World', 5)); // 'Hello...'
console.log(StringUtils.slug('Hello World!')); // 'hello-world'

// Test date utils
console.log(DateUtils.format(new Date(), 'Y-m-d')); // '2026-02-07'
console.log(DateUtils.relative(new Date())); // 'just now'

// Test API
api.get('/api/test').then(console.log);

// Test notifications
Notify.success('Test notification');
```

---

## Changelog

### v1.0 (2026-02-07)
- Initial release with 5 core modules
- Complete JSDoc documentation
- Vanilla JavaScript (no dependencies)
- ES6+ with module syntax

---

*Last Updated: 2026-02-07*
