<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
try {
    // Fetch orders for the current user, ordered by order date descending
    $stmt = $pdo->prepare("
      SELECT id, order_date, total_amount, delivery_fee, status
      FROM orders
      WHERE user_id = ?
      ORDER BY order_date DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching order history: " . $e->getMessage());
    $orders = [];
}
?>
<main class="container mx-auto py-10 px-4 md:px-8">
  <h1 class="text-3xl font-bold text-merry-primary mb-6">Order History</h1>
  <div class="space-y-4">
    <?php if (!empty($orders)): ?>
      <?php foreach ($orders as $order): 
        // compute grand total
        $grand = floatval($order['total_amount']) + floatval($order['delivery_fee']);
      ?>
        <div class="p-4 border rounded-lg shadow hover:shadow-xl transition-shadow duration-200">
          <p class="font-semibold">Order #<?= htmlspecialchars($order['id']) ?></p>
          <p>Date: <?= htmlspecialchars($order['order_date']) ?></p>
          <p>Products Total: ₦<?= number_format($order['total_amount'], 2) ?></p>
          <p>Delivery Fee: ₦<?= number_format($order['delivery_fee'], 2) ?></p>
          <p class="font-bold">Grand Total: ₦<?= number_format($grand, 2) ?></p>
          <p>Status: <?= htmlspecialchars(ucfirst($order['status'])) ?></p>
          <a href="order-detail.php?id=<?= $order['id'] ?>" class="text-merry-primary hover:underline">
            View Details
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No orders found.</p>
    <?php endif; ?>
  </div>
</main>
<?php include 'footer.php'; ?>
