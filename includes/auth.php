<?php
/**
 * RMS -- Authentication & Authorization
 */

const ROLE_SUPERADMIN = 'superadmin';
const ROLE_TEACHER = 'teacher';
const ROLE_STUDENT = 'student';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/** Require an authenticated session for protected pages. */
function require_login(): array
{
    start_session();
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        redirect(url('auth/login.php'));
    }
    // Verify account still active
    $active = db_val('SELECT status FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if ($active === null || (int) $active !== 1) {
        session_destroy();
        redirect(url('auth/login.php?inactive=1'));
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'name' => $_SESSION['name'] ?? 'User',
    ];
}

/** Require a specific role; otherwise deny access. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('<!DOCTYPE html><html><head><title>403</title><link rel="stylesheet" href="' . e(url('assets/vendor/bootstrap/bootstrap.min.css')) . '"></head>
        <body class="bg-light d-flex align-items-center" style="min-height:100vh"><div class="container text-center">
        <h1 class="display-1 fw-bold text-danger">403</h1><p class="lead">Access denied. You do not have permission to view this page.</p>
        <a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to Home</a></div></body></html>');
    }
    return $user;
}

/** Current user details helper */
function current_user(): array
{
    start_session();
    return [
        'id' => (int) ($_SESSION['user_id'] ?? 0),
        'role' => $_SESSION['role'] ?? '',
        'name' => $_SESSION['name'] ?? '',
        'username' => $_SESSION['username'] ?? '',
    ];
}

/** Login an existing user record */
function perform_login(int $userId, string $role, string $name, string $username): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $name;
    $_SESSION['username'] = $username;
    if ($role === ROLE_TEACHER) {
        $_SESSION['teacher_id'] = (int) (db_val('SELECT teacher_id FROM users WHERE id = ?', [$userId]) ?? 0);
    } else {
        unset($_SESSION['teacher_id']);
    }
    db_run('UPDATE users SET last_login = NOW() WHERE id = ?', [$userId]);
    audit('Login', $role . ' logged in');
}

/** Logout */
function perform_logout(): void
{
    start_session();
    audit('Logout', 'User logged out');
    $_SESSION = [];
    session_destroy();
}