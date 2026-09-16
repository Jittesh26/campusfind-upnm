<?php
// csrf.php – CSRF token generation and validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a CSRF token and store it in session
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get the current CSRF token (or generate if missing)
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        generateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token
 */
function validateCsrfToken($submitted_token) {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

/**
 * Output a hidden input with the CSRF token (for forms)
 */
function csrfInput() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}
?>