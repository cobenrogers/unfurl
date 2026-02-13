# JavaScript Utilities - Quick Start Guide

All utilities are vanilla JavaScript modules (ES6+) with no external dependencies.

## Files

- **utils.js** (9.9 KB) - DOM, string, date, array, and storage helpers
- **api.js** (6.7 KB) - Fetch wrapper with automatic CSRF tokens
- **notifications.js** (7.0 KB) - Toast notification system
- **forms.js** (9.7 KB) - Form validation, serialization, CSRF utilities
- **bulk-actions.js** (10 KB) - Bulk selection and action handling

## Quick Examples

### DOM Manipulation

```javascript
import { DOM } from './utils.js';

DOM.select('.button');
DOM.selectAll('.item');
DOM.create('button', {class: 'btn'}, 'Click me');
DOM.on(element, 'click', () => {});
DOM.addClass(element, 'active');
```

### API Requests (Auto CSRF)

```javascript
import { api } from './api.js';

const users = await api.get('/api/users');
const result = await api.post('/api/users', {name: 'John'});
await api.delete('/api/users/1');
```

### Notifications

```javascript
import { Notify } from './notifications.js';

Notify.success('Saved!');
Notify.error('Failed');
Notify.info('Loading...');
Notify.warning('Confirm action');
```

### Form Validation

```javascript
import { FormValidator, FormSerializer } from './forms.js';

const validator = new FormValidator();
const errors = validator.validate(form, {
  email: ['required', 'email'],
  password: ['required', 'min:8']
});

if (Object.keys(errors).length === 0) {
  const data = FormSerializer.toObject(form);
  await api.post('/api/register', data);
}
```

### Bulk Selection

```javascript
import { BulkActions } from './bulk-actions.js';

const bulk = new BulkActions({
  onBulkAction: async ({action, ids}) => {
    await api.post('/api/bulk-action', {action, ids});
  }
});
```

## String Utilities

```javascript
import { StringUtils } from './utils.js';

StringUtils.truncate('Long text', 10);      // 'Long te...'
StringUtils.slug('Hello World!');            // 'hello-world'
StringUtils.capitalize('hello');             // 'Hello'
StringUtils.sanitize(html);                  // Safe HTML
StringUtils.formatBytes(1024 * 1024);        // '1 MB'
```

## Date Utilities

```javascript
import { DateUtils } from './utils.js';

DateUtils.format(date, 'Y-m-d H:M:S');      // '2026-02-07 15:30:45'
DateUtils.relative(date);                    // '2h ago'
DateUtils.toISO(new Date());                // ISO string
DateUtils.parse('2026-02-07T15:30:45Z');    // Date object
```

## Array Utilities

```javascript
import { ArrayUtils } from './utils.js';

ArrayUtils.unique([1, 2, 2, 3]);             // [1, 2, 3]
ArrayUtils.groupBy(items, 'category');       // {cat1: [...], cat2: [...]}
ArrayUtils.flatten([[1, 2], [3, [4, 5]]]);   // [1, 2, 3, 4, 5]
```

## Storage (localStorage)

```javascript
import { StorageUtils } from './utils.js';

StorageUtils.set('user', {id: 1, name: 'John'});
const user = StorageUtils.get('user', {});
StorageUtils.remove('user');
```

## Form Serialization

```javascript
import { FormSerializer } from './forms.js';

// To object
const data = FormSerializer.toObject(form);

// To FormData (for file uploads)
const formData = FormSerializer.toFormData(form);

// Populate from object
FormSerializer.fromObject(form, {name: 'Jane', email: 'jane@example.com'});
```

## CSRF Tokens

Automatically handled by `api.js`. Manually:

```javascript
import { CSRFToken } from './forms.js';

const token = CSRFToken.getToken();
CSRFToken.addToForm(form);
const headers = CSRFToken.getHeaders();
await CSRFToken.refresh();
```

## Full Documentation

See `/docs/JAVASCRIPT-UTILITIES.md` for complete API reference and examples.

## Browser Support

Requires modern browsers with ES6 support:
- Chrome 55+, Firefox 52+, Safari 11+, Edge 15+

## Module Syntax

Use in HTML:

```html
<script type="module">
  import { DOM } from './utils.js';
  import { api } from './api.js';
  // ...
</script>
```

Or import in another module:

```javascript
import { api } from './api.js';
import { Notify } from './notifications.js';
```

---

All modules are self-contained and can be used independently.
