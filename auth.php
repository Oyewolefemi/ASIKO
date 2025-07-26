<?php
ob_start();
include 'config.php';
include 'functions.php';
include 'header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        // Registration Process
        $name             = sanitize($_POST['name']);
        $email            = sanitize($_POST['email']);
        $password         = $_POST['password'];
        $confirmPassword  = $_POST['confirmPassword'];
        $securityQuestion = sanitize($_POST['security_question']);
        $securityAnswer   = $_POST['security_answer'];
        
        if ($password !== $confirmPassword) {
            printError("Passwords do not match.");
        } else {
            // Hash the password and the security answer
            $passwordHash         = secureHash($password);
            $securityAnswerHash   = secureHash($securityAnswer);
            
            try {
                // Note: Ensure your users table has columns for security_question and security_answer_hash.
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, security_question, security_answer_hash) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $email, $passwordHash, $securityQuestion, $securityAnswerHash])) {
                    echo "<div class='bg-green-200 border border-green-400 text-green-800 p-4 rounded mb-4'>Registration successful.</div>";
                } else {
                    printError("Registration failed.");
                }
            } catch (Exception $e) {
                printError("Registration error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'login') {
        // Initial Login Process (Email & Password)
        $email    = sanitize($_POST['email']);
        $password = $_POST['password'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && verifyPassword($password, $user['password'])) {
                // Instead of setting user_id, we store it in a temporary session variable.
                $_SESSION['pending_user_id'] = $user['id'];
                header("Location: security-question.php");
                exit;
            } else {
                printError("Invalid email or password.");
            }
        } catch (Exception $e) {
            printError("Login error: " . $e->getMessage());
        }
    }
}
?>

<main class="container mx-auto py-10 px-4 md:px-8">
  <div class="max-w-md mx-auto">
    <div class="flex justify-center mb-6" role="tablist">
      <button id="showLogin" role="tab" aria-selected="true" aria-controls="loginPanel" class="px-4 py-2 border-b-2 border-merry-green-primary font-bold focus:outline-none">
        Login
      </button>
      <button id="showRegister" role="tab" aria-selected="false" aria-controls="registerPanel" class="ml-4 px-4 py-2 border-b-2 border-transparent font-bold focus:outline-none hover:border-merry-green-primary">
        Register
      </button>
    </div>

    <!-- Login Form -->
    <div id="loginPanel" role="tabpanel" class="transition-opacity duration-300 opacity-100">
      <h2 class="text-3xl font-bold mb-4 text-merry-primary">Login</h2>
      <form id="loginFormElem" method="POST" novalidate>
        <input type="hidden" name="action" value="login">
        <div class="mb-4">
          <label for="loginEmail" class="block mb-2 text-sm font-medium text-gray-700">Email:</label>
          <input type="email" id="loginEmail" name="email" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="you@example.com" required>
        </div>
        <div class="mb-4">
          <label for="loginPassword" class="block mb-2 text-sm font-medium text-gray-700">Password:</label>
          <input type="password" id="loginPassword" name="password" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="********" required>
        </div>
        <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
          Login
        </button>
      </form>
    </div>

    <!-- Registration Form -->
    <div id="registerPanel" role="tabpanel" class="transition-opacity duration-300 opacity-0 pointer-events-none">
      <h2 class="text-3xl font-bold mb-4 text-merry-primary">Register</h2>
      <form id="registerFormElem" method="POST" novalidate>
        <input type="hidden" name="action" value="register">
        <div class="mb-4">
          <label for="registerName" class="block mb-2 text-sm font-medium text-gray-700">Full Name:</label>
          <input type="text" id="registerName" name="name" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="Your Name" required>
        </div>
        <div class="mb-4">
          <label for="registerEmail" class="block mb-2 text-sm font-medium text-gray-700">Email:</label>
          <input type="email" id="registerEmail" name="email" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="you@example.com" required>
        </div>
        <div class="mb-4">
          <label for="registerPassword" class="block mb-2 text-sm font-medium text-gray-700">Password:</label>
          <input type="password" id="registerPassword" name="password" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="********" required>
        </div>
        <div class="mb-4">
          <label for="confirmPassword" class="block mb-2 text-sm font-medium text-gray-700">Confirm Password:</label>
          <input type="password" id="confirmPassword" name="confirmPassword" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="********" required>
        </div>
        <!-- Security Question and Answer Fields -->
        <div class="mb-4">
          <label for="securityQuestion" class="block mb-2 text-sm font-medium text-gray-700">Security Question:</label>
          <input type="text" id="securityQuestion" name="security_question" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="e.g., What is your favorite pet?" required>
        </div>
        <div class="mb-4">
          <label for="securityAnswer" class="block mb-2 text-sm font-medium text-gray-700">Security Answer:</label>
          <input type="text" id="securityAnswer" name="security_answer" class="w-full border border-gray-300 p-2 rounded-lg focus:outline-none focus:border-merry-primary transition duration-200" placeholder="Your answer" required>
        </div>
        <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
          Register
        </button>
      </form>
    </div>
  </div>
</main>
<?php include 'footer.php'; ?>
<script>
  const showLoginBtn = document.getElementById('showLogin');
  const showRegisterBtn = document.getElementById('showRegister');
  const loginPanel = document.getElementById('loginPanel');
  const registerPanel = document.getElementById('registerPanel');

  function showPanel(panelToShow, panelToHide, buttonActive, buttonInactive) {
    panelToShow.classList.remove('opacity-0', 'pointer-events-none');
    panelToShow.classList.add('opacity-100');
    panelToHide.classList.remove('opacity-100');
    panelToHide.classList.add('opacity-0', 'pointer-events-none');
    buttonActive.setAttribute('aria-selected', 'true');
    buttonInactive.setAttribute('aria-selected', 'false');
    buttonActive.classList.add('border-merry-green-primary');
    buttonInactive.classList.remove('border-merry-green-primary');
  }

  showLoginBtn.addEventListener('click', () => { showPanel(loginPanel, registerPanel, showLoginBtn, showRegisterBtn); });
  showRegisterBtn.addEventListener('click', () => { showPanel(registerPanel, loginPanel, showRegisterBtn, showLoginBtn); });
</script>
