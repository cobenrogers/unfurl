# GitHub Configuration

## Required Secrets for CI/CD

Configure these secrets in your repository settings:
`Settings` → `Secrets and variables` → `Actions` → `New repository secret`

### Deployment Secrets (FTP)

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `FTP_SERVER` | FTP server hostname | `ftp.mir.diw.mybluehost.me` |
| `FTP_USERNAME` | FTP username | `deploy@unfurl.bennernet.com` |
| `FTP_PASSWORD` | FTP password | `your-secure-password` |
| `FTP_SERVER_DIR` | Deployment directory path | `/` (usually root for subdomain FTP accounts) |
| `APP_URL` | Base URL of your application | `https://unfurl.bennernet.com` |

### Setting Up FTP Deployment

1. **Create FTP Account in cPanel**:
   - Log into cPanel
   - Go to Files → FTP Accounts
   - Create new FTP account:
     - Username: `deploy@unfurl.bennernet.com`
     - Password: Generate strong password
     - Directory: Set to your unfurl installation directory
     - Quota: Unlimited or sufficient for your needs

2. **Test FTP Connection**:
   ```bash
   # Using curl
   curl -v ftp://your-ftp-server/ --user "username:password"

   # Or use an FTP client like FileZilla
   ```

3. **Add Secrets to GitHub**:
   - Go to: https://github.com/cobenrogers/unfurl/settings/secrets/actions
   - Click "New repository secret" for each:
     - `FTP_SERVER`: Your FTP hostname
     - `FTP_USERNAME`: Your FTP username
     - `FTP_PASSWORD`: Your FTP password
     - `FTP_SERVER_DIR`: Usually `/` for subdomain FTP accounts
     - `APP_URL`: Your application's public URL

## Workflow Overview

### CI/CD Pipeline (`ci-cd.yml`)

**Triggers:**
- Push to `main` branch
- Pull requests to `main`

**Jobs:**

1. **Test** (runs on PHP 8.1, 8.2, 8.3)
   - Validates composer.json/lock
   - Installs dependencies
   - Runs all test suites:
     - Unit tests
     - Integration tests
     - Security tests
     - Performance tests
   - Generates coverage report (PHP 8.2 only)
   - Uploads to Codecov

2. **Deploy** (runs only on main branch push, after tests pass)
   - Installs production dependencies
   - Connects via FTP
   - Deploys files (excludes tests, node_modules, .env, etc.)
   - Incremental deployment (only changed files)
   - Runs health check
   - Notifies success/failure

## Manual Deployment

If you need to deploy manually:

```bash
# From project root
./scripts/deploy.sh
```

See `docs/DEPLOYMENT.md` for detailed deployment instructions.

## Badge URLs

Add these to your README:

```markdown
[![Tests](https://github.com/cobenrogers/unfurl/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/cobenrogers/unfurl/actions)
[![codecov](https://codecov.io/gh/cobenrogers/unfurl/branch/main/graph/badge.svg)](https://codecov.io/gh/cobenrogers/unfurl)
```

## Troubleshooting

### Tests failing?
- Check PHPUnit version compatibility
- Verify all PHP extensions installed
- Review test output in Actions tab

### Deployment failing?
- Verify FTP credentials are correct
- Test FTP connection manually with `curl` or FTP client
- Check FTP_SERVER_DIR path is correct (usually `/` for subdomain accounts)
- Verify FTP account has write permissions
- Check health.php endpoint is accessible
- Review GitHub Actions logs for detailed error messages

### Health check failing?
- Verify APP_URL is correct
- Check .env file exists on server
- Verify database connection works
- Review PHP error logs on server

## Security Notes

- **Never commit secrets** - Use GitHub Secrets only
- **Rotate SSH keys** regularly (every 6-12 months)
- **Limit SSH key scope** - Use dedicated key for deployments
- **Enable 2FA** on GitHub account
- **Review Actions logs** - Check for suspicious activity
