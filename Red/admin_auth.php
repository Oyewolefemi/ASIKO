<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Set mode: 'login' (default) or 'register'
$mode = (isset($_GET['mode']) && $_GET['mode'] === 'register') ? 'register' : 'login';

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Simple username validation (letters, numbers, underscores, 3–32 chars)
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $error = "Username must be 3–32 characters and contain only letters, numbers, or underscores.";
    } elseif (empty($password)) {
        $error = "Please enter a password.";
    } elseif (isset($_POST['auth_action']) && $_POST['auth_action'] === 'login') {
        // --- Login Process ---
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = ?");
        if ($stmt->execute([$username])) {
            $admin = $stmt->fetch();
            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                // Log the login action
                $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'login', 'Admin logged in successfully.')");
                $logStmt->execute([$admin['id']]);
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Database query failed.";
        }
    } elseif (isset($_POST['auth_action']) && $_POST['auth_action'] === 'register') {
        // --- Registration Process ---
        $confirm_password = $_POST['confirm_password'] ?? '';
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check if username is taken
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
            if ($stmt->execute([$username])) {
                if ($stmt->fetch()) {
                    $error = "Username is already taken.";
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $insertStmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
                    if ($insertStmt->execute([$username, $password_hash])) {
                        $success = "Admin registered successfully. You can now log in.";
                        $mode = 'login'; // Switch to login after registration
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            } else {
                $error = "Database error during registration.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin <?php echo ucfirst($mode); ?> - MBC E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Admin <?php echo ucfirst($mode); ?></h2>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-2 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-2 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <form action="admin_auth.php?mode=<?php echo $mode; ?>" method="post" autocomplete="off">
            <input type="hidden" name="auth_action" value="<?php echo $mode; ?>">
            <div class="mb-4">
                <label class="block mb-1">Username</label>
                <input type="text" name="username" class="w-full border rounded p-2" required>
            </div>
            <div class="mb-4">
                <label class="block mb-1">Password</label>
                <input type="password" name="password" class="w-full border rounded p-2" required>
            </div>
            <?php if ($mode === 'register'): ?>
                <div class="mb-4">
                    <label class="block mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full border rounded p-2" required>
                </div>
            <?php endif; ?>
            <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded">
                <?php echo ucfirst($mode); ?>
            </button>
        </form>
        <p class="text-center mt-4">
            <?php if ($mode === 'login'): ?>
                Don't have an account? <a href="admin_auth.php?mode=register" class="text-blue-500">Register here</a>.
            <?php else: ?>
                Already registered? <a href="admin_auth.php" class="text-blue-500">Login here</a>.
            <?php endif; ?>
        </p>
    </div>
</body>
</html>
