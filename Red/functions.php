<?php
/**
 * Print an error message in a consistent format and halt execution.
 *
 * @param string $errorMsg The error message to display.
 */
function printError($errorMsg) {
    echo "<div style='padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 5px; margin: 10px 0;'>";
    echo "Error: " . htmlspecialchars($errorMsg);
    echo "</div>";
    exit();
}

/**
 * Sanitize input data to prevent XSS and injection attacks.
 *
 * @param string $data The data to sanitize.
 * @return string The sanitized data.
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Generate a CSRF token and store it in the session.
 *
 * @return string The generated CSRF token.
 */
function generateCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verify a provided CSRF token against the session token.
 *
 * @param string $token The token to verify.
 * @return bool True if valid, false otherwise.
 */
function verifyCsrfToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']);
        return true;
    }
    return false;
}
?>