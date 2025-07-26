<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Handle deletion via GET if needed
if (isset($_GET['delete'])) {
    $cart_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        header("Location: cart.php");
        exit;
    } catch (Exception $e) {
        printError("Error removing item from cart: " . $e->getMessage());
    }
}

// Handle increase via GET (optional fallback)
if (isset($_GET['increase'])) {
    $product_id = intval($_GET['increase']);
    try {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch();
        if ($existing) {
            $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE id = ?");
            $stmtUpdate->execute([$existing['id']]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $stmtInsert->execute([$user_id, $product_id]);
        }
        header("Location: cart.php");
        exit;
    } catch (Exception $e) {
        printError("Error increasing quantity: " . $e->getMessage());
    }
}

// Retrieve cart items (join with products)
try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, p.id as product_id, p.name, p.price, p.image_path, c.quantity
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cartItems = $stmt->fetchAll();
} catch (Exception $e) {
    printError("Error fetching cart items: " . $e->getMessage());
    $cartItems = [];
}

// Calculate total cost
$total = 0;
foreach ($cartItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

function getProductImage($imagePath) {
    if (empty($imagePath)) return null;
    
    $filename = basename($imagePath);
    
    // Use the correct web path with /kiosk/
    $webPath = "/niffyhm/Red/uploads/" . $filename;
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;
    
    if (file_exists($serverPath)) {
        return $webPath;
    }
    
    return null;
}

?>
<main class="container mx-auto py-10 px-4 md:px-8 pb-24">
  <h1 class="text-3xl font-bold text-green-600 mb-6">Your Shopping Cart</h1>
  
  <?php if (!empty($cartItems)): ?>
    <div class="space-y-4">
      <?php foreach ($cartItems as $item): ?>
        <?php 
        // Get the processed image path
        $imagePath = getProductImage($item['image_path']);
        ?>
        <div class="flex flex-col md:flex-row items-center border p-4 rounded-lg shadow hover:shadow transition-shadow duration-200">
          <?php if ($imagePath): ?>
            <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-24 h-24 rounded mr-4 object-cover">
          <?php endif; ?>
          <div class="flex-1">
            <h2 class="text-xl font-semibold text-green-600"><?php echo htmlspecialchars($item['name']); ?></h2>
            <p class="mt-1">Price: ₦<?php echo number_format($item['price'], 2); ?></p>
            <p class="mt-1">Quantity: <?php echo $item['quantity']; ?></p>
            <p class="mt-1 font-semibold">Subtotal: ₦<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
          </div>
          <div class="flex flex-col space-y-1">
            <button data-productid="<?php echo $item['product_id']; ?>" class="increase-btn bg-gray-200 text-gray-800 py-1 px-3 rounded hover:bg-gray-300 transition">+</button>
            <button data-productid="<?php echo $item['product_id']; ?>" class="decrease-btn bg-gray-200 text-gray-800 py-1 px-3 rounded hover:bg-gray-300 transition">–</button>
            <button data-productid="<?php echo $item['product_id']; ?>" class="remove-btn bg-gray-200 text-gray-800 py-1 px-3 rounded hover:bg-gray-300 transition" onclick="return confirm('Are you sure you want to remove this item?');">Remove</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-8">
      <p class="text-xl font-bold">Total: ₦<?php echo number_format($total, 2); ?></p>
      <a href="checkout.php" class="mt-4 inline-block bg-green-600 text-white py-2 px-4 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-green-600">
        Checkout
      </a>
    </div>
  <?php else: ?>
    <div class="text-center py-8">
      <p class="text-lg text-gray-600 mb-4">Your cart is empty.</p>
      <a href="products.php" class="bg-green-600 text-white py-2 px-4 rounded-full hover:opacity-90 transition-all focus:outline-none focus:ring-2 focus:ring-green-600">
        Continue Shopping
      </a>
    </div>
  <?php endif; ?>
</main>
<?php include 'footer.php'; ?>

<script>
  // Function to update cart using AJAX (for increase, decrease, remove)
  function updateCart(productId, quantityChange) {
    fetch('ajax_cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId, quantity: quantityChange })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      console.error(err);
      alert("Network or server error.");
    });
  }

  // Attach event listeners for Increase button
  document.querySelectorAll('.increase-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const productId = btn.getAttribute('data-productid');
      updateCart(productId, 1);
    });
  });

  // Attach event listeners for Decrease button
  document.querySelectorAll('.decrease-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const productId = btn.getAttribute('data-productid');
      updateCart(productId, -1);
    });
  });

  // Attach event listeners for Remove button
  document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const productId = btn.getAttribute('data-productid');
      updateCart(productId, -1000); // ensure removal
    });
  });
</script>