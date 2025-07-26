<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    printError("You must be logged in to view addresses.");
    exit;
}

// Handle deletion if "delete" is provided in GET
if (isset($_GET['delete'])) {
    $addr_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$addr_id, $user_id]);
        header("Location: addresses.php");
        exit;
    } catch (Exception $e) {
        printError("Error deleting address: " . $e->getMessage());
    }
}

// Retrieve addresses
try {
    $stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $addresses = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching addresses: " . $e->getMessage());
    $addresses = [];
}
?>
<main class="container mx-auto py-10 px-4 md:px-8 pb-24">
  <h1 class="text-3xl font-bold text-green-600 mb-6">My Addresses</h1>
  <?php if (!empty($addresses)): ?>
    <div class="space-y-4">
      <?php foreach ($addresses as $addr): ?>
        <div class="border p-4 rounded-lg shadow">
          <p class="font-semibold text-green-600"><?php echo sanitize($addr['full_name']); ?></p>
          <p><?php echo sanitize($addr['address_line1']); ?></p>
          <p><?php echo sanitize($addr['city']); ?>, <?php echo sanitize($addr['state']); ?></p>
          <p class="text-sm text-gray-500 mt-2">Added on: <?php echo $addr['created_at']; ?></p>
          <a href="addresses.php?delete=<?php echo $addr['id']; ?>" onclick="return confirm('Delete this address?');" class="text-red-500 text-sm">Delete</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>No addresses found.</p>
  <?php endif; ?>
</main>
<?php include 'footer.php'; ?>