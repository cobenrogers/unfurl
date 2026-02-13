# GitHub Configuration

## Required Secrets for CI/CD

Configure these secrets in your repository settings:
`Settings` → `Secrets and variables` → `Actions` → `New repository secret`

### Deployment Secrets

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `SSH_PRIVATE_KEY` | Private SSH key for cPanel access | Contents of your private key file |
| `SSH_HOST` | cPanel server hostname | `yoursite.com` |
| `SSH_USER` | cPanel username for SSH | `cpanel_username` |
| `DEPLOY_PATH` | Path to unfurl installation | `/home/user/public_html/unfurl` |
| `APP_URL` | Base URL of your application | `https://yoursite.com/unfurl` |

### Setting Up SSH Key

1. **Generate SSH key** (if you don't have one):
   ```bash
   ssh-keygen -t ed25519 -C "github-actions-unfurl"
   ```

2. **Add public key to cPanel**:
   - Log into cPanel
   - Go to Security → SSH Access → Manage SSH Keys
   - Import or paste your public key
   - Authorize the key

3. **Add private key to GitHub**:
   - Copy entire private key (including BEGIN/END lines)
   - Go to repository Settings → Secrets → New repository secret
   - Name: `SSH_PRIVATE_KEY`
   - Value: Paste private key
   - Save

4. **Test SSH connection**:
   ```bash
   ssh -i path/to/private_key cpanel_user@yoursite.com
   ```

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
   - Sets up SSH connection
   - Deploys via rsync
   - Sets file permissions
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
- Verify SSH key is correct and authorized
- Check cPanel SSH access is enabled
- Verify DEPLOY_PATH exists and is writable
- Check health.php endpoint is accessible

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
