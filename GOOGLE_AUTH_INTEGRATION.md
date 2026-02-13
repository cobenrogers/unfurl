# Google OAuth Authentication Integration for Unfurl

This document explains how to integrate the bennernet-auth Google OAuth system into Unfurl for subdomain deployment at unfurl.bennernet.com.

## Files Created

1. `src/Security/Auth.php` - Authentication service class
2. `views/login.php` - Google sign-in page
3. `views/pending-approval.php` - Pending approval page

## Step 1: Update `public/index.php`

Add Auth service initialization after other services (around line 64):

```php
use Unfurl\Security\Auth;

// ... existing code ...

// Initialize authentication
$isProduction = $config['app']['environment'] === 'production';
$auth = new Auth($db->getPdo(), $isProduction);

// ... existing services ...
```

## Step 2: Add Auth Routes

Add these routes after the home route (around line 76):

```php
// Authentication Routes
$router->get('/login', function () {
    require __DIR__ . '/../views/login.php';
});

$router->get('/pending-approval', function () use ($auth) {
    $user = $auth->getCurrentUser();
    require __DIR__ . '/../views/pending-approval.php';
});

$router->get('/logout', function () use ($auth) {
    header('Location: ' . $auth->getLogoutUrl());
    exit;
});
```

## Step 3: Protect Routes (Optional)

To require authentication for specific routes, add auth checks:

### Option A: Protect All Routes

Add after router initialization (line 66):

```php
// Require authentication for all routes except login
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$publicPaths = ['/login', '/pending-approval'];

if (!in_array($currentPath, $publicPaths) && !str_starts_with($currentPath, '/rss/')) {
    $auth->requireApproval();
}
```

### Option B: Protect Specific Routes

Add auth check inside route closures:

```php
$router->get('/feeds', function () use ($auth, $feedRepo, ...) {
    $auth->requireApproval(); // Require auth for this route

    // ... rest of route handler ...
});
```

## Step 4: Add User Menu to Views

Create a user menu partial at `views/partials/user-menu.php`:

```php
<?php
// views/partials/user-menu.php
$user = $auth->getCurrentUser();
?>
<?php if ($user): ?>
<div class="user-menu">
    <button id="user-menu-btn" class="user-avatar">
        <?= htmlspecialchars(substr($user['name'], 0, 1)) ?>
    </button>
    <div id="user-dropdown" class="user-dropdown hidden">
        <div class="user-info">
            <strong><?= htmlspecialchars($user['name']) ?></strong>
            <small><?= htmlspecialchars($user['email']) ?></small>
            <?php if ($user['is_admin']): ?>
                <span class="badge">Admin</span>
            <?php endif; ?>
        </div>
        <div class="user-actions">
            <?php if ($user['is_admin']): ?>
                <a href="https://bennernet.com/auth/admin/">Manage Users</a>
            <?php endif; ?>
            <a href="/logout">Sign Out</a>
        </div>
    </div>
</div>

<script>
// Toggle dropdown
document.getElementById('user-menu-btn').addEventListener('click', () => {
    document.getElementById('user-dropdown').classList.toggle('hidden');
});

// Close when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-menu')) {
        document.getElementById('user-dropdown').classList.add('hidden');
    }
});
</script>

<style>
.user-menu { position: relative; }
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #667eea;
    color: white;
    border: none;
    font-weight: 600;
    cursor: pointer;
}
.user-dropdown {
    position: absolute;
    right: 0;
    top: 48px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    min-width: 240px;
    z-index: 1000;
}
.user-dropdown.hidden { display: none; }
.user-info {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
}
.user-info strong { display: block; margin-bottom: 4px; }
.user-info small { color: #718096; }
.badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 8px;
    background: #9f7aea;
    color: white;
    border-radius: 4px;
    font-size: 12px;
}
.user-actions { padding: 8px 0; }
.user-actions a {
    display: block;
    padding: 10px 16px;
    color: #2d3748;
    text-decoration: none;
}
.user-actions a:hover { background: #f7fafc; }
</style>
<?php endif; ?>
```

Then include it in your existing views (dashboard.php, etc.):

```php
<?php require __DIR__ . '/partials/user-menu.php'; ?>
```

## Step 5: Production Configuration

### Database Configuration

Ensure `config.php` has the auth database configured:

```php
'database' => [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'unfurl_db',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
],
```

### bennernet-auth Configuration

The main bennernet-auth needs to be configured for subdomain support:

**File:** `agents/bennernet-auth/auth-config.production.php`

```php
// Cookie domain - use leading dot for all subdomains
define('COOKIE_DOMAIN', '.bennernet.com');  // CRITICAL for subdomain auth

// Redirect URIs - Google Cloud Console must have these whitelisted
define('GOOGLE_REDIRECT_URI', 'https://bennernet.com/auth/api/google-callback.php');
```

### Google Cloud Console

Add to **Authorized redirect URIs**:
- `https://bennernet.com/auth/api/google-callback.php`

Add to **Authorized JavaScript origins**:
- `https://bennernet.com`
- `https://unfurl.bennernet.com`

## How It Works

### Authentication Flow:

1. User visits `https://unfurl.bennernet.com/`
2. Auth check redirects to `https://bennernet.com/auth/api/google-login.php?return=https://unfurl.bennernet.com/`
3. Google OAuth flow happens on main domain
4. Cookie set with domain `.bennernet.com` (works for all subdomains)
5. User redirected back to `https://unfurl.bennernet.com/`
6. Session cookie validated, user is authenticated

### Session Sharing:

Because the cookie is set on `.bennernet.com`, the same session works across:
- `https://bennernet.com/portal/`
- `https://bennernet.com/helm/`
- `https://bennernet.com/agents/`
- `https://unfurl.bennernet.com/`

Users log in once and are authenticated everywhere!

## Development Mode

In development, `Auth.php` auto-authenticates as "Dev User" when `$isProduction = false`.

To test real auth locally:
1. Set `$isProduction = true` in index.php
2. Ensure bennernet-auth is running on localhost:8082
3. Configure local hosts file if needed

## Testing

1. **Not logged in**: Should redirect to login page
2. **Logged in, approved**: Should access all features
3. **Logged in, not approved**: Should see pending approval page
4. **Logout**: Should redirect to bennernet.com auth logout

## Security Notes

- All auth validation happens server-side via database session lookups
- Cookies are httpOnly and secure in production
- CSRF protection still applies to form submissions
- Session tokens expire after configured duration (30 days default)
