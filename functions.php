<?php
// functions.php: Utility functions and error reporting

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sanitize input to avoid XSS
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Securely hash a password
function secureHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify a password against a hash
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Print an error message with consistent styling (using Tailwind CSS)
function printError($errorMessage) {
    echo "<div class='bg-red-200 border border-red-400 text-red-800 p-4 rounded mb-4'>";
    echo "Error: " . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
    echo "</div>";
}
?>