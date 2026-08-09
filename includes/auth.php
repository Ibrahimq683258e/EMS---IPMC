<?php
/**
 * Authentication and Session Management Helper
 */

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // You could set session.cookie_secure if on HTTPS, but let's be flexible for localhost.
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

/**
 * Enforce that the user must be logged in. Redirect to login.php if not.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Enforce role-based access control (RBAC).
 * Redirects to dashboard if user role is not in the list of allowed roles.
 * @param array $allowed_roles e.g. ['Admin', 'HR']
 */
function requireRoles($allowed_roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        $_SESSION['error_msg'] = "Access denied! You do not have permission to view that page.";
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Helper to check if current user is Admin
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'Admin';
}

/**
 * Helper to check if current user is HR
 */
function isHR() {
    return isLoggedIn() && $_SESSION['user_role'] === 'HR';
}

/**
 * Helper to check if current user is Employee
 */
function isEmployee() {
    return isLoggedIn() && $_SESSION['user_role'] === 'Employee';
}

/**
 * Prevent Cross-Site Request Forgery (CSRF)
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>