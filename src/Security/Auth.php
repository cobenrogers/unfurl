<?php

declare(strict_types=1);

namespace Unfurl\Security;

/**
 * Authentication Service
 *
 * Integrates with centralized bennernet-auth Google OAuth system.
 * For subdomain setup (unfurl.bennernet.com), points to main domain auth.
 */
class Auth
{
    private const SESSION_COOKIE_NAME = 'bennernet_session';
    private const AUTH_API_BASE = 'https://bennernet.com/auth/api';

    private ?\PDO $pdo;
    private string $authDbName;
    private bool $isProduction;

    public function __construct(\PDO $pdo, bool $isProduction = false)
    {
        $this->pdo = $pdo;
        $this->isProduction = $isProduction;

        // Database name where bennernet_users and bennernet_sessions live
        $this->authDbName = $isProduction ? 'mirdiwmy_bennernet' : 'bennernet_local';
    }

    /**
     * Get current authenticated user from session cookie
     */
    public function getCurrentUser(): ?array
    {
        // Development bypass - auto-authenticate as dev user
        if (!$this->isProduction) {
            return [
                'id' => 'dev-user-001',
                'name' => 'Dev User',
                'email' => 'dev@bennernet.local',
                'avatar_url' => null,
                'is_admin' => true,
                'is_approved' => true
            ];
        }

        $token = $_COOKIE[self::SESSION_COOKIE_NAME] ?? '';

        if (empty($token)) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT u.* FROM {$this->authDbName}.bennernet_users u
                JOIN {$this->authDbName}.bennernet_sessions s ON s.user_id = u.id
                WHERE s.token = ?
                  AND s.expires_at > NOW()
            ");
            $stmt->execute([$token]);

            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("Auth error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    /**
     * Check if user is authenticated AND approved
     */
    public function isApproved(): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        return (bool)$user['is_approved'] || (bool)$user['is_admin'];
    }

    /**
     * Require authentication - redirect to login if not authenticated
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirectToLogin();
        }
    }

    /**
     * Require authentication and approval - redirect if not approved
     */
    public function requireApproval(): void
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            $this->redirectToLogin();
        }

        if (!$user['is_approved'] && !$user['is_admin']) {
            $this->redirectToPendingApproval();
        }
    }

    /**
     * Redirect to Google OAuth login
     */
    private function redirectToLogin(): void
    {
        $returnUrl = $this->getCurrentUrl();
        $loginUrl = self::AUTH_API_BASE . '/google-login.php?return=' . urlencode($returnUrl);

        header('Location: ' . $loginUrl);
        exit;
    }

    /**
     * Redirect to pending approval page
     */
    private function redirectToPendingApproval(): void
    {
        header('Location: /pending-approval');
        exit;
    }

    /**
     * Get logout URL
     */
    public function getLogoutUrl(): string
    {
        return self::AUTH_API_BASE . '/logout.php';
    }

    /**
     * Get current full URL for return redirect
     */
    private function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'unfurl.bennernet.com';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $protocol . '://' . $host . $uri;
    }
}
