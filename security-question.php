<?php
include 'config.php';
include 'functions.php';
include 'header.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch the user's security question
$stmt = $pdo->prepare("SELECT security_question FROM users WHERE id = ?");
$stmt->execute([$_SESSION['pending_user_id']]);
$userData = $stmt->fetch();

if (!$userData) {
    printError("User not found.");
    exit;
}
$securityQuestion = $userData['security_question'];
?>

<main class="container mx-auto py-10 px-4 md:px-8">
  <div class="max-w-md mx-auto">
    <h2 class="text-3xl font-bold mb-4 text-merry-primary">Security Verification</h2>
    <p class="mb-4">Please answer the following security question to complete your login:</p>
    <p class="font-bold mb-4"><?php echo htmlspecialchars($securityQuestion); ?></p>
    <form action="security-question-process.php" method="POST">
      <div class="mb-4">
        <label for="securityAnswer" class="block mb-2 text-sm font-medium text-gray-700">Answer:</label>
        <input type="text" id="securityAnswer" name="security_answer" class="w-full border border-gray-300 p-2 rounded-lg" placeholder="Your answer" required>
      </div>
      <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90">Submit</button>
    </form>
  </div>
</main>

<?php include 'footer.php'; ?>