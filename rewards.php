<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
try {
    $stmt = $pdo->prepare("SELECT reward_points, description FROM rewards WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $rewards = $stmt->fetch();
    if (!$rewards) {
        $rewards = ['reward_points' => 0, 'description' => 'No rewards available yet.'];
    }
} catch (Exception $e) {
    printError("Error fetching rewards: " . $e->getMessage());
    $rewards = ['reward_points' => 0, 'description' => 'Error fetching rewards.'];
}
?>
<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Rewards & Loyalty</h1>
  <div class="border p-6 rounded-lg shadow mb-8">
    <p class="text-xl font-semibold">Reward Points: <?php echo $rewards['reward_points']; ?></p>
    <p class="mt-2"><?php echo sanitize($rewards['description']); ?></p>
  </div>
  <!-- Form to redeem rewards -->
  <form method="POST" class="max-w-md mx-auto border p-6 rounded-lg shadow">
    <input type="hidden" name="action" value="redeem_rewards">
    <div class="mb-4">
      <label class="block mb-1">Redeem Points:</label>
      <input type="number" name="redeem_points" class="w-full border p-2 rounded-lg" min="1" required>
    </div>
    <button type="submit" class="w-full bg-merry-primary text-merry-white py-2 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-merry-primary">
      Redeem
    </button>
  </form>
</main>
<?php include 'footer.php'; ?>