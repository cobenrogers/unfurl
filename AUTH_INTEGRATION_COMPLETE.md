# Google OAuth Authentication - Integration Complete ✅

Google authentication has been successfully integrated into Unfurl for subdomain deployment at `unfurl.bennernet.com`.

## What Was Done

### 1. Created Auth Service (`src/Security/Auth.php`)
- Integrates with central bennernet-auth at `https://bennernet.com/auth/api/`
- Auto-authenticates in dev mode
- Validates sessions via bennernet database
- Provides login/logout URL generation

### 2. Created Login Views
- **`views/login.php`** - Beautiful Google sign-in page
- **`views/pending-approval.php`** - Account pending approval page
- **`views/partials/user-menu.php`** - Dropdown user profile menu

### 3. Updated `public/index.php`
- Added Auth service initialization (line 60)
- Added authentication middleware (protects all web UI routes)
- Added auth routes (/login, /pending-approval, /logout)
- Passed `$auth` to view routes for user menu access

### 4. Route Protection
Web UI routes now require authentication:
- ✅ /feeds, /articles, /settings, /dashboard, /process, /logs

Public endpoints (no auth required):
- ✅ /feed (RSS - for dev/testing)
- ✅ /api/feed (RSS with API key)
- ✅ /api/* (all API endpoints use API keys)
- ✅ /health (health checks)

## Adding User Menu to Views

To add the user menu to any view, include the partial in your header:

```php
<!-- In views/dashboard.php, views/settings.php, etc. -->
<div class="header" style="display: flex; justify-content: space-between; align-items: center;">
    <h1>Page Title</h1>
    <?php require __DIR__ . '/partials/user-menu.php'; ?>
</div>
```

The user menu will show:
- User avatar (first letter of name)
- User name and email
- Admin badge (if admin)
- "Manage Users" link (admins only)
- "Sign Out" button

## Testing Locally

1. **Dev Mode (Auto-login)**:
   - Just run the app - you'll be auto-authenticated as "Dev User"
   - No login screen shown

2. **Test Real Auth**:
   - Set environment to 'production' in config.php temporarily
   - You'll see the Google sign-in page
   - Make sure bennernet-auth is running

## Production Deployment

### Prerequisites

1. **bennernet-auth Configuration**

   File: `agents/bennernet-auth/auth-config.production.php`
   ```php
   define('COOKIE_DOMAIN', '.bennernet.com');  // ← CRITICAL for subdomain
   ```

2. **Google Cloud Console**

   Ensure these are in your authorized redirect URIs:
   - `https://bennernet.com/auth/api/google-callback.php`

   And authorized JavaScript origins:
   - `https://bennernet.com`
   - `https://unfurl.bennernet.com`

3. **unfurl config.php**

   Verify environment setting:
   ```php
   'app' => [
       'environment' => getenv('APP_ENV') ?: 'production',
       // ...
   ]
   ```

### How It Works in Production

1. User visits `https://unfurl.bennernet.com/feeds`
2. Auth middleware detects no session
3. Redirects to `https://bennernet.com/auth/api/google-login.php?return=https://unfurl.bennernet.com/feeds`
4. User completes Google OAuth
5. Session cookie set with domain `.bennernet.com`
6. User redirected back to unfurl
7. Authenticated!

### Single Sign-On Benefits

Because the cookie domain is `.bennernet.com`, users authenticated at:
- ✅ bennernet.com/portal/
- ✅ bennernet.com/helm/
- ✅ bennernet.com/agents/
- ✅ unfurl.bennernet.com

All share the same session - log in once, authenticated everywhere!

## Files Created/Modified

### Created:
- `src/Security/Auth.php`
- `views/login.php`
- `views/pending-approval.php`
- `views/partials/user-menu.php`
- `GOOGLE_AUTH_INTEGRATION.md` (detailed guide)
- `AUTH_INTEGRATION_COMPLETE.md` (this file)

### Modified:
- `public/index.php` (added auth initialization, middleware, routes, and $auth to views)

## Security Notes

- 🔒 All auth validation server-side via database
- 🔒 CSRF protection still active for forms
- 🔒 API endpoints use separate API key auth
- 🔒 RSS feeds can be public (/feed) or authenticated (/api/feed with key)
- 🔒 Sessions expire after 30 days (bennernet-auth default)
- 🔒 Cookies are httpOnly and secure in production

## Next Steps

1. **Add user menu to views**: Include the partial in dashboard.php, settings.php, etc.
2. **Test locally**: Verify auth flow works
3. **Deploy to production**: Test on unfurl.bennernet.com
4. **Verify SSO**: Test that login at portal also authenticates unfurl

## Troubleshooting

**Issue**: "Access denied" even after login
- Check bennernet-auth cookie domain is `.bennernet.com`
- Verify user is approved in admin panel

**Issue**: Redirecting to login in dev mode
- Check `'environment' => 'development'` in config.php
- Verify Auth service sees `$isProduction = false`

**Issue**: Can't access RSS feeds
- RSS endpoints are public by design
- /api/feed requires valid API key (create in settings)

## Questions?

See `GOOGLE_AUTH_INTEGRATION.md` for more detailed documentation.
