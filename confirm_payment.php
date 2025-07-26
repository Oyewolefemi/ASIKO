<?php
include 'header.php';
include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    printError("You must be logged in to confirm payment.");
    include 'footer.php';
    exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
if (!$order_id) {
    printError("Invalid order ID.");
    include 'footer.php';
    exit;
}

// Verify order belongs to user and get order details
$stmt = $pdo->prepare("
    SELECT o.*, a.full_name, a.address_line1, a.city, a.state 
    FROM orders o
    LEFT JOIN addresses a ON o.address_id = a.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    printError("Order not found or you don't have permission to access it.");
    include 'footer.php';
    exit;
}

// Check if order is in correct status for payment confirmation
if ($order['status'] !== 'approved') {
    printError("This order cannot be confirmed for payment at this time.");
    include 'footer.php';
    exit;
}

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_payment') {
    try {
        // Update order status to pending (payment submitted, awaiting verification)
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'pending', payment_confirmed_at = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$order_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $payment_confirmed = true;
        } else {
            printError("Error confirming payment. Please try again.");
        }
    } catch (Exception $e) {
        printError("Error processing payment confirmation: " . $e->getMessage());
    }
}

$grand_total = $order['total_amount'] + $order['delivery_fee'];
?>

<style>
.confirm-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
    font-family: 'Inter', sans-serif;
}

.order-card {
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

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.btn-primary {
    background: #5ce1e6;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
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
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-secondary:hover {
    background: #f0f0f0;
    border-color: #ccc;
}

.payment-details {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

@media (max-width: 768px) {
    .confirm-container {
        padding: 20px 15px;
    }
    
    .order-card {
        padding: 20px;
    }
    
    .info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<main class="confirm-container">
    <?php if (isset($payment_confirmed) && $payment_confirmed): ?>
        <!-- Payment Confirmed Success Message -->
        <div class="order-card">
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                <h2 class="text-2xl font-bold text-green-800 mb-4">Payment Confirmation Submitted!</h2>
                <p class="text-green-700 mb-2">Order ID: <strong>#<?= $order_id ?></strong></p>
                <p class="text-green-700">Your payment confirmation has been received and is being processed.</p>
            </div>
            
            <div class="bg-blue-50 border-l-4 border-blue-400 p-6 mb-6">
                <h3 class="text-lg font-semibold text-blue-800 mb-3">What happens next?</h3>
                <p class="text-blue-700 mb-2">• Our team will verify your payment</p>
                <p class="text-blue-700 mb-2">• Once verified, your order will be processed</p>
                <p class="text-blue-700 mb-2">• You'll receive updates on your order status</p>
                <p class="text-blue-700">• Your order will be prepared and shipped</p>
            </div>
            
            <div class="text-center space-x-4">
                <a href="orders.php" class="btn-primary">
                    View My Orders
                </a>
                <a href="products.php" class="btn-secondary">
                    Continue Shopping
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Payment Confirmation Form -->
        <h1 class="text-3xl font-bold text-center mb-8" style="color: #1a1a1a;">Confirm Payment</h1>
        
        <div class="order-card">
            <h2 class="section-title">Order Details</h2>
            <div class="info-row">
                <span><strong>Order ID:</strong></span>
                <span>#<?= $order_id ?></span>
            </div>
            <div class="info-row">
                <span><strong>Order Date:</strong></span>
                <span><?= date('M j, Y g:i A', strtotime($order['order_date'])) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Status:</strong></span>
                <span class="capitalize"><?= htmlspecialchars($order['status']) ?></span>
            </div>
            <div class="info-row">
                <span><strong>Delivery Option:</strong></span>
                <span><?= htmlspecialchars($order['delivery_option']) ?></span>
            </div>
        </div>

        <div class="order-card">
            <h2 class="section-title">Shipping Address</h2>
            <p><strong><?= htmlspecialchars($order['full_name']) ?></strong></p>
            <p><?= htmlspecialchars($order['address_line1']) ?></p>
            <p><?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?></p>
        </div>

        <div class="order-card">
            <h2 class="section-title">Payment Information</h2>
            <div class="info-row">
                <span>Product Total:</span>
                <span>₦<?= number_format($order['total_amount'], 2) ?></span>
            </div>
            <div class="info-row">
                <span>Delivery Fee:</span>
                <span>₦<?= number_format($order['delivery_fee'], 2) ?></span>
            </div>
            <div class="info-row" style="font-weight: 600; font-size: 18px;">
                <span>Grand Total:</span>
                <span>₦<?= number_format($grand_total, 2) ?></span>
            </div>
            
            <div class="payment-details">
                <h3 class="text-lg font-semibold mb-3">Bank Transfer Details</h3>
                <div class="space-y-2">
                    <p><strong>Bank:</strong> First Bank of Nigeria</p>
                    <p><strong>Account Name:</strong> MyStore Ltd.</p>
                    <p><strong>Account Number:</strong> 1234567890</p>
                    <p><strong>Amount:</strong> ₦<?= number_format($grand_total, 2) ?></p>
                    <p><strong>Reference:</strong> Order #<?= $order_id ?></p>
                </div>
            </div>
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <p class="text-yellow-700 text-sm">
                    <strong>Important:</strong> Please ensure you use "Order #<?= $order_id ?>" as your transfer reference/narration. 
                    This helps us identify your payment quickly.
                </p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="confirm_payment">
                <div class="text-center space-x-4">
                    <button type="submit" class="btn-primary" onclick="return confirm('Have you completed the bank transfer for ₦<?= number_format($grand_total, 2) ?>?')">
                        Yes, I've Made the Payment
                    </button>
                    <a href="orders.php" class="btn-secondary">
                        Back to Orders
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>