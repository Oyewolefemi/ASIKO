<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_auth.php');
    exit();
}

if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = 'No order ID provided.';
    header('Location: orders.php');
    exit();
}

$order_id = intval($_GET['order_id']);

try {
    $pdo->beginTransaction();
    
    // Verify order exists and is pending manual payment
    $stmt = $pdo->prepare("
        SELECT o.id, o.status, o.payment_method, u.email 
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Order not found.');
    }
    
    if ($order['status'] !== 'pending') {
        throw new Exception('Order is not pending approval.');
    }
    
    if ($order['payment_method'] !== 'manual') {
        throw new Exception('Order is not a manual payment order.');
    }
    
    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'active', approved_at = CURRENT_TIMESTAMP, approved_by = ?, approved_by_admin = 1
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['admin_id'], $order_id]);
    
    $pdo->commit();
    $_SESSION['success'] = "Order #{$order_id} approved successfully.";
    
} catch (Exception $e) {
    $pdo->rollback();
    $_SESSION['error'] = "Error approving order: " . $e->getMessage();
}

header('Location: orders.php');
exit();
?>