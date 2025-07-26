<?php
include 'header.php';
include 'config.php';
include 'functions.php';

// Include the environment configuration at the top
require_once 'EnvLoader.php';
EnvLoader::load(__DIR__ . '/.env');

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    printError("You must be logged in to checkout.");
    exit;
}

// Fetch cart items
$stmt = $pdo->prepare("
    SELECT p.id AS product_id, p.name, p.price, p.image_path, c.quantity
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cartItems = $stmt->fetchAll();
if (empty($cartItems)) {
    echo "<main class='container mx-auto py-10'><p>Your cart is empty. <a href='products.php' class='text-green-600 underline'>Continue Shopping</a></p></main>";
    include 'footer.php';
    exit;
}

// Calculate product total
$total = 0;
foreach ($cartItems as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Fetch saved addresses
$stmt = $pdo->prepare("
  SELECT id, full_name, address_line1, city, state
  FROM addresses
  WHERE user_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$savedAddresses = $stmt->fetchAll();

// Get payment configuration from environment
$paymentConfig = [
    'bank_name' => EnvLoader::get('BANK_NAME', 'Bank Name Not Set'),
    'account_number' => EnvLoader::get('BANK_ACCOUNT_NUMBER', '****'),
    'account_name' => EnvLoader::get('BANK_ACCOUNT_NAME', 'Account Name Not Set'),
    'currency' => EnvLoader::get('PAYMENT_CURRENCY', 'NGN'),
    'instructions' => EnvLoader::get('PAYMENT_INSTRUCTIONS', 'Please include your order number in the payment reference'),
    'deadline_days' => (int) EnvLoader::get('PAYMENT_DEADLINE_DAYS', 7)
];

// Validate payment configuration
$paymentConfigured = !empty($paymentConfig['bank_name']) && 
                     $paymentConfig['bank_name'] !== 'Bank Name Not Set' &&
                     !empty($paymentConfig['account_number']) && 
                     $paymentConfig['account_number'] !== '****' &&
                     !empty($paymentConfig['account_name']) && 
                     $paymentConfig['account_name'] !== 'Account Name Not Set';

// Handle manual checkout POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    // Validation
    $errors = [];
    
    // Check if payment is configured before processing
    if (!$paymentConfigured) {
        $errors[] = "Payment configuration is incomplete. Please contact support.";
    }
    
    // Validate address selection
    if (empty($_POST['address_option'])) {
        $errors[] = "Please select or add a shipping address.";
    }
    
    // Validate delivery option
    if (empty($_POST['delivery_option'])) {
        $errors[] = "Please select a delivery option.";
    }
    
    // Validate payment method
    if (empty($paymentMethod)) {
        $errors[] = "Please select a payment method.";
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            printError($error);
        }
    } else {
        // Determine address_id
        if ($_POST['address_option'] === 'new') {
            // Validate new address fields
            $requiredFields = ['new_full_name', 'new_address_line1', 'new_city', 'new_state'];
            foreach ($requiredFields as $field) {
                if (empty(trim($_POST[$field] ?? ''))) {
                    $errors[] = "All address fields are required.";
                    break;
                }
            }
            
            if (empty($errors)) {
                try {
                    $stmtIns = $pdo->prepare("
                      INSERT INTO addresses (user_id, full_name, address_line1, city, state, created_at)
                      VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmtIns->execute([
                      $user_id,
                      sanitize($_POST['new_full_name']),
                      sanitize($_POST['new_address_line1']),
                      sanitize($_POST['new_city']),
                      sanitize($_POST['new_state'])
                    ]);
                    $address_id = $pdo->lastInsertId();
                } catch (Exception $e) {
                    $errors[] = "Error saving address: " . $e->getMessage();
                }
            }
        } else {
            $address_id = intval($_POST['address_option']);
            // Verify address belongs to user
            $stmt = $pdo->prepare("SELECT id FROM addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            if (!$stmt->fetch()) {
                $errors[] = "Invalid address selected.";
            }
        }

        // Process order if no errors
        if (empty($errors) && $paymentMethod === 'manual') {
            $deliveryFee = floatval($_POST['delivery_fee'] ?? 0);
            $grandTotal = $total + $deliveryFee;
            $deliveryOption = sanitize($_POST['delivery_option']);

            try {
                $pdo->beginTransaction();

                // Create order with approved status and show payment details
                $stmtOrder = $pdo->prepare("
                  INSERT INTO orders
                    (user_id, order_date, total_amount, delivery_fee, status, address_id, payment_method, delivery_option, created_at)
                  VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtOrder->execute([
                  $user_id,
                  $total,
                  $deliveryFee,
                  'approved', // Show payment details immediately
                  $address_id,
                  'manual',
                  $deliveryOption
                ]);
                $order_id = $pdo->lastInsertId();

                // Add order details
                $stmtDet = $pdo->prepare("
                  INSERT INTO order_details (order_id, product_id, quantity, price)
                  VALUES (?, ?, ?, ?)
                ");
                foreach ($cartItems as $item) {
                    $stmtDet->execute([
                      $order_id,
                      $item['product_id'],
                      $item['quantity'],
                      $item['price']
                    ]);
                }

                // Clear cart
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")
                    ->execute([$user_id]);

                $pdo->commit();

                // Calculate payment deadline
                $deadlineDate = date('Y-m-d', strtotime('+' . $paymentConfig['deadline_days'] . ' days'));
                
                // Show payment instructions immediately with environment variables
                ?>
                <main class="container mx-auto py-10 px-4">
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                            <h2 class="text-2xl font-bold text-green-800 mb-4">Order Created Successfully!</h2>
                            <p class="text-green-700 mb-2">Order ID: <strong>#<?= $order_id ?></strong></p>
                            <p class="text-green-700">Total Amount: <strong><?= htmlspecialchars($paymentConfig['currency']) ?><?= number_format($grandTotal, 2) ?></strong></p>
                            <p class="text-green-700">Payment Deadline: <strong><?= date('F j, Y', strtotime($deadlineDate)) ?></strong></p>
                        </div>
                        
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-6">
                            <h3 class="text-lg font-semibold text-yellow-800 mb-3">Payment Instructions</h3>
                            <p class="text-yellow-700 mb-4">Please transfer <strong><?= htmlspecialchars($paymentConfig['currency']) ?><?= number_format($grandTotal, 2) ?></strong> to:</p>
                            <div class="bg-white rounded-lg p-4 border">
                                <ul class="space-y-2 text-sm">
                                    <li><strong>Bank:</strong> <?= htmlspecialchars($paymentConfig['bank_name']) ?></li>
                                    <li><strong>Account Name:</strong> <?= htmlspecialchars($paymentConfig['account_name']) ?></li>
                                    <li><strong>Account Number:</strong> <?= htmlspecialchars($paymentConfig['account_number']) ?></li>
                                    <li><strong>Reference:</strong> Order #<?= $order_id ?></li>
                                </ul>
                            </div>
                            <?php if (!empty($paymentConfig['instructions'])): ?>
                                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded">
                                    <p class="text-blue-800 text-sm">
                                        <strong>Note:</strong> <?= htmlspecialchars($paymentConfig['instructions']) ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center space-x-4">
                            <a href="confirm_payment.php?order_id=<?= $order_id ?>" 
                               class="inline-block bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition-colors">
                                I've Made the Payment
                            </a>
                            <a href="orders.php" 
                               class="inline-block bg-gray-600 text-white py-3 px-6 rounded-lg hover:bg-gray-700 transition-colors">
                                View My Orders
                            </a>
                        </div>
                    </div>
                </main>
                <?php
                
            } catch (Exception $e) {
                $pdo->rollBack();
                printError("Error processing your order: " . $e->getMessage());
            }

            include 'footer.php';
            exit;
        }
        
        // Display errors if any
        if (!empty($errors)) {
            foreach ($errors as $error) {
                printError($error);
            }
        }
    }
}

// Helper function to get product image
function getProductImage($imagePath) {
    if (empty($imagePath)) return null;
    
    $filename = basename($imagePath);
    // Use the correct web path with /niffyhm/
    $webPath = "/niffyhm/Red/uploads/" . $filename;
    $serverPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;
    
    if (file_exists($serverPath)) {
        return $webPath;
    }
    
    return null;
}
?>

<style>
.checkout-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 20px;
    font-family: 'Inter', sans-serif;
}

.checkout-section {
    background: white;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #5ce1e6;
}

.cart-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.cart-item:hover {
    border-color: #e0e0e0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.cart-item img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 6px;
    margin-right: 15px;
}

.address-option {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.address-option:hover {
    background: #f8f8f8;
    border-color: #5ce1e6;
}

.address-option input[type="radio"] {
    margin-right: 12px;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: #5ce1e6;
    box-shadow: 0 0 0 3px rgba(92, 225, 230, 0.1);
}

.btn-primary {
    background: #5ce1e6;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary:hover {
    background: #4dd4d9;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #f8f8f8;
    color: #333;
    border: 1px solid #ddd;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #f0f0f0;
    border-color: #ccc;
}

.fee-summary {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.total-line:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 18px;
    color: #1a1a1a;
}

.config-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
    color: #856404;
}

@media (max-width: 768px) {
    .checkout-container {
        padding: 20px 15px;
    }
    
    .checkout-section {
        padding: 20px;
    }
    
    .cart-item {
        flex-direction: column;
        text-align: center;
    }
    
    .cart-item img {
        margin-right: 0;
        margin-bottom: 10px;
    }
}
</style>

<main class="checkout-container">
    <h1 class="text-3xl font-bold text-center mb-8" style="color: #1a1a1a;">Checkout</h1>
    
    <?php if (!$paymentConfigured): ?>
        <div class="config-warning">
            <strong>Payment Configuration Notice:</strong> 
            Payment details are not fully configured. Please contact support before placing orders.
        </div>
    <?php endif; ?>
    
    <form id="checkoutForm" method="POST" action="">
        <input type="hidden" name="action" value="checkout">
        <input type="hidden" name="payment_method" id="payment_method" value="">
        <input type="hidden" name="delivery_fee" id="delivery_fee_input" value="0.00">
        <input type="hidden" name="delivery_option" id="delivery_option_input" value="">

        <!-- Cart Summary -->
        <section class="checkout-section">
            <h2 class="section-title">Order Summary</h2>
            <?php foreach ($cartItems as $item): 
                $product_image = getProductImage($item['image_path']);
            ?>
                <div class="cart-item">
                    <?php if ($product_image): ?>
                        <img src="<?= htmlspecialchars($product_image) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php endif; ?>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-gray-600"><?= htmlspecialchars($paymentConfig['currency']) ?><?= number_format($item['price'], 2) ?> × <?= intval($item['quantity']) ?></p>
                        <p class="font-medium">Subtotal: <?= htmlspecialchars($paymentConfig['currency']) ?><?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="total-line">
                <span>Product Total:</span>
                <span><?= htmlspecialchars($paymentConfig['currency']) ?><?= number_format($total, 2) ?></span>
            </div>
        </section>

        <!-- Shipping Address -->
        <section class="checkout-section">
            <h2 class="section-title">Shipping Address</h2>
            <div id="savedAddresses" class="space-y-3 mb-4">
                <?php if ($savedAddresses): ?>
                    <?php foreach ($savedAddresses as $addr): ?>
                        <label class="address-option">
                            <input type="radio" name="address_option" value="<?= $addr['id'] ?>" required>
                            <span>
                                <strong><?= htmlspecialchars($addr['full_name']) ?></strong><br>
                                <?= htmlspecialchars($addr['address_line1']) ?><br>
                                <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <label class="address-option">
                    <input type="radio" name="address_option" value="new" required>
                    <span><strong>Add New Address</strong></span>
                </label>
            </div>
            
            <div id="newAddressSection" class="hidden">
                <input type="text" name="new_full_name" placeholder="Full Name" class="form-input">
                <input type="text" name="new_address_line1" placeholder="Address Line 1" class="form-input">
                <input type="text" name="new_city" placeholder="City" class="form-input">
                <input type="text" name="new_state" placeholder="State" class="form-input">
            </div>
        </section>

        <!-- Delivery Option -->
        <section class="checkout-section">
            <h2 class="section-title">Delivery Option</h2>
            <select id="deliveryOption" class="form-input" required>
                <option value="">— Select Delivery Option —</option>
                <option value="Island">Island</option>
                <option value="Mainland">Mainland</option>
                <option value="Inter-state (park)">Inter-state (park)</option>
                <option value="Inter-state (doorstep)">Inter-state (doorstep)</option>
                <option value="Pick-up">Pick-up</option>
            </select>
        </section>

        <!-- Fee Summary -->
        <section id="feeSummary" class="checkout-section hidden">
            <h2 class="section-title">Payment Summary</h2>
            <div class="fee-summary">
                <div class="total-line">
                    <span>Product Total:</span>
                    <span><?= htmlspecialchars($paymentConfig['currency']) ?><?= number_format($total, 2) ?></span>
                </div>
                <div class="total-line">
                    <span>Delivery Fee:</span>
                    <span><?= htmlspecialchars($paymentConfig['currency']) ?><span id="deliveryFee">0.00</span></span>
                </div>
                <div class="total-line">
                    <span>Grand Total:</span>
                    <span><?= htmlspecialchars($paymentConfig['currency']) ?><span id="grandTotal"><?= number_format($total, 2) ?></span></span>
                </div>
            </div>
        </section>

        <!-- Submit Order Button -->
        <section class="checkout-section">
            <button type="button" id="makePaymentBtn" class="btn-primary w-full py-4 text-lg hidden" <?= !$paymentConfigured ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                <?= $paymentConfigured ? 'Submit Order for Review' : 'Payment Not Configured' ?>
            </button>
        </section>
    </form>
</main>

<?php include 'footer.php'; ?>

<script>
// Address selection handling
document.querySelectorAll('input[name="address_option"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const newAddressSection = document.getElementById('newAddressSection');
        if (this.value === 'new') {
            newAddressSection.classList.remove('hidden');
            // Make new address fields required
            newAddressSection.querySelectorAll('input').forEach(input => {
                input.required = true;
            });
        } else {
            newAddressSection.classList.add('hidden');
            // Remove required attribute from new address fields
            newAddressSection.querySelectorAll('input').forEach(input => {
                input.required = false;
            });
        }
    });
});

// Delivery option handling
const deliverySelect = document.getElementById('deliveryOption');
const feeSummary = document.getElementById('feeSummary');
const paymentBtn = document.getElementById('makePaymentBtn');

deliverySelect.addEventListener('change', function() {
    const selectedOption = this.value;
    document.getElementById('delivery_option_input').value = selectedOption;
    
    if (!selectedOption) {
        feeSummary.classList.add('hidden');
        paymentBtn.classList.add('hidden');
        return;
    }
    
    // Fetch delivery fee
    fetch(`get_delivery_fee.php?location=${encodeURIComponent(selectedOption)}`)
        .then(response => response.json())
        .then(data => {
            const deliveryFee = parseFloat(data.fee_amount) || 0;
            const productTotal = <?= $total ?>;
            const grandTotal = productTotal + deliveryFee;
            
            document.getElementById('deliveryFee').textContent = deliveryFee.toFixed(2);
            document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
            document.getElementById('delivery_fee_input').value = deliveryFee.toFixed(2);
            
            feeSummary.classList.remove('hidden');
            
            // Only show payment button if payment is configured
            const paymentConfigured = <?= $paymentConfigured ? 'true' : 'false' ?>;
            if (paymentConfigured) {
                paymentBtn.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error fetching delivery fee:', error);
            alert('Error calculating delivery fee. Please try again.');
        });
});

// Order submission
document.getElementById('makePaymentBtn').addEventListener('click', function() {
    // Check if payment is configured
    const paymentConfigured = <?= $paymentConfigured ? 'true' : 'false' ?>;
    if (!paymentConfigured) {
        alert('Payment is not configured. Please contact support.');
        return;
    }
    
    // Validate form
    const addressSelected = document.querySelector('input[name="address_option"]:checked');
    const deliverySelected = document.getElementById('deliveryOption').value;
    
    if (!addressSelected) {
        alert('Please select a shipping address.');
        return;
    }
    
    if (!deliverySelected) {
        alert('Please select a delivery option.');
        return;
    }
    
    // If new address is selected, validate new address fields
    if (addressSelected.value === 'new') {
        const newAddressFields = document.querySelectorAll('#newAddressSection input[required]');
        let allFilled = true;
        newAddressFields.forEach(field => {
            if (!field.value.trim()) {
                allFilled = false;
            }
        });
        
        if (!allFilled) {
            alert('Please fill in all address fields.');
            return;
        }
    }
    
    // Set payment method and submit
    document.getElementById('payment_method').value = 'manual';
    document.getElementById('checkoutForm').submit();
});
</script>