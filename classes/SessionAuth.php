<?php

declare(strict_types=1);

namespace GemData\Classes;

class SessionAuth
{
    public function __construct(private Database $db, private ActivityLogger $logger)
    {
    }

    public function loginUser(string $email, string $password): bool
    {
        if (!$this->canAttemptUserLogin($email)) {
            $this->logUserLogin(null, $email, false, 'throttled');
            return false;
        }

        $user = $this->db->first('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]);
        if (!$user || $user['status'] !== 'active') {
            $this->logUserLogin(null, $email, false);
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            $this->logUserLogin((int) $user['id'], $email, false);
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity_at'] = time();
        $this->db->execute('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $user['id']]);
        $this->logUserLogin((int) $user['id'], $email, true, 'success');
        return true;
    }

    public function loginAdmin(string $email, string $password): bool
    {
        if (!$this->canAttemptAdminLogin($email)) {
            $this->logAdminLogin(null, $email, false, 'throttled');
            return false;
        }

        $admin = $this->db->first(
            'SELECT a.*, r.slug AS role_slug, r.name AS role_name
             FROM admins a
             LEFT JOIN admin_roles r ON r.id = a.role_id
             WHERE a.email = :email LIMIT 1',
            ['email' => $email]
        );
        if (!$admin || (int) $admin['is_active'] !== 1) {
            $this->logAdminLogin(null, $email, false);
            return false;
        }
        if (!password_verify($password, $admin['password_hash'])) {
            $this->logAdminLogin((int) $admin['id'], $email, false);
            return false;
        }
        session_regenerate_id(true);
        if ($this->adminRequiresTwoFactor($admin)) {
            $_SESSION['admin_2fa_pending_id'] = (int) $admin['id'];
            $_SESSION['admin_2fa_verified'] = false;
            $_SESSION['last_activity_at'] = time();
            $this->logAdminLogin((int) $admin['id'], $email, true, 'password_verified_2fa_pending');
            return true;
        }
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_2fa_verified'] = true;
        $_SESSION['last_activity_at'] = time();
        $this->db->execute('UPDATE admins SET last_login_at = NOW() WHERE id = :id', ['id' => $admin['id']]);
        $this->logAdminLogin((int) $admin['id'], $email, true, 'success');
        return true;
    }

    private function adminRequiresTwoFactor(array $admin): bool
    {
        return (bool) config('security.admin_2fa_enabled', false)
            || (int) ($admin['two_factor_enabled'] ?? 0) === 1
            || ($admin['role_slug'] ?? '') === 'super_admin';
    }

    public function pendingTwoFactorAdmin(): ?array
    {
        if (empty($_SESSION['admin_2fa_pending_id'])) {
            return null;
        }
        return $this->db->first(
            'SELECT a.*, r.slug AS role_slug, r.name AS role_name
             FROM admins a
             LEFT JOIN admin_roles r ON r.id = a.role_id
             WHERE a.id = :id AND a.is_active = 1 LIMIT 1',
            ['id' => (int) $_SESSION['admin_2fa_pending_id']]
        );
    }

    public function completeAdminTwoFactor(int $adminId): void
    {
        session_regenerate_id(true);
        unset($_SESSION['admin_2fa_pending_id']);
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['admin_2fa_verified'] = true;
        $_SESSION['last_activity_at'] = time();
        $this->db->execute('UPDATE admins SET last_login_at = NOW(), two_factor_enabled = 1 WHERE id = :id', ['id' => $adminId]);
        $this->logger->log('admin', $adminId, 'admin_2fa_verified', 'Admin completed two-factor verification.');
    }

    public function logoutUser(): void
    {
        unset($_SESSION['user_id']);
        $this->destroySession();
    }

    public function logoutAdmin(): void
    {
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_2fa_pending_id'], $_SESSION['admin_2fa_verified']);
        $this->destroySession();
    }

    /**
     * Fully destroy the current session, clear data, and expire the cookie.
     */
    private function destroySession(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return $this->db->first(
            'SELECT u.*, w.balance FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = :id',
            ['id' => $_SESSION['user_id']]
        );
    }

    public function admin(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }
        return $this->db->first(
            'SELECT a.*, r.slug AS role_slug, r.name AS role_name
             FROM admins a
             LEFT JOIN admin_roles r ON r.id = a.role_id
             WHERE a.id = :id',
            ['id' => $_SESSION['admin_id']]
        );
    }

    public function requireUser(): ?array
    {
        return $this->user();
    }

    public function requireAdmin(): ?array
    {
        return $this->admin();
    }

    public function confirmAdminPassword(int $adminId, string $password): bool
    {
        $admin = $this->db->first('SELECT password_hash FROM admins WHERE id = :id LIMIT 1', ['id' => $adminId]);
        if (!$admin) {
            return false;
        }

        return password_verify($password, (string) $admin['password_hash']);
    }

    private function logUserLogin(?int $userId, string $email, bool $success, string $reason = 'invalid_credentials'): void
    {
        $this->db->execute(
            'INSERT INTO user_login_logs (user_id, email, ip_address, user_agent, was_successful)
             VALUES (:user_id, :email, :ip_address, :user_agent, :was_successful)',
            [
                'user_id' => $userId,
                'email' => $email,
                'ip_address' => client_ip(),
                'user_agent' => current_user_agent(),
                'was_successful' => $success ? 1 : 0,
            ]
        );
        $this->logger->log('user', $userId ?? 0, 'user_login_' . ($success ? 'success' : 'failed'), 'User login attempt recorded.', [
            'email' => $email,
            'reason' => $reason,
            'ip_address' => client_ip(),
        ]);
    }

    private function logAdminLogin(?int $adminId, string $email, bool $success, string $reason = 'invalid_credentials'): void
    {
        $this->db->execute(
            'INSERT INTO admin_login_logs (admin_id, email, ip_address, user_agent, was_successful)
             VALUES (:admin_id, :email, :ip_address, :user_agent, :was_successful)',
            [
                'admin_id' => $adminId,
                'email' => $email,
                'ip_address' => client_ip(),
                'user_agent' => current_user_agent(),
                'was_successful' => $success ? 1 : 0,
            ]
        );
        $this->logger->log('admin', $adminId ?? 0, 'admin_login_' . ($success ? 'success' : 'failed'), 'Admin login attempt recorded.', [
            'email' => $email,
            'reason' => $reason,
            'ip_address' => client_ip(),
        ]);
    }

    private function canAttemptAdminLogin(string $email): bool
    {
        $windowMinutes = max(1, (int) config('app.admin_login_attempt_window_minutes', 15));
        $limit = max(1, (int) config('app.admin_login_attempt_limit', 5));
        // Cast to int before interpolation — PDO cannot bind INTERVAL values
        $window = (int) $windowMinutes;
        $row = $this->db->first(
            'SELECT COUNT(*) AS total
             FROM admin_login_logs
             WHERE email = :email
               AND ip_address = :ip_address
               AND was_successful = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $window . ' MINUTE)',
            [
                'email'      => $email,
                'ip_address' => client_ip(),
            ]
        );

        return (int) ($row['total'] ?? 0) < $limit;
    }

    private function canAttemptUserLogin(string $email): bool
    {
        $windowMinutes = max(1, (int) config('app.user_login_attempt_window_minutes', 15));
        $limit = max(1, (int) config('app.user_login_attempt_limit', 5));
        // Cast to int before interpolation — PDO cannot bind INTERVAL values
        $window = (int) $windowMinutes;
        $row = $this->db->first(
            'SELECT COUNT(*) AS total
             FROM user_login_logs
             WHERE email = :email
               AND ip_address = :ip_address
               AND was_successful = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $window . ' MINUTE)',
            [
                'email'      => $email,
                'ip_address' => client_ip(),
            ]
        );

        return (int) ($row['total'] ?? 0) < $limit;
    }
}
