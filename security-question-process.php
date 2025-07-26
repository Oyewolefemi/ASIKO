<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'config.php';
include 'functions.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: auth.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['pending_user_id'];
    $securityAnswerInput = $_POST['security_answer'];

    // Retrieve the stored security answer hash from the database
    $stmt = $pdo->prepare("SELECT security_answer_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();

    if (!$userData) {
        printError("User not found.");
        exit;
    }
    $storedHash = $userData['security_answer_hash'];

    // Verify the security answer
    if (verifyPassword($securityAnswerInput, $storedHash)) {
        // Successful verification – complete the login.
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['pending_user_id']);
        header("Location: my-account.php");
        exit;
    } else {
        printError("Security answer is incorrect.");
    }
}
?>