<?php
session_start();
header('Content-Type: application/json');

include 'config.php';
include 'functions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID.']);
    exit;
}

try {
    // Verify product exists
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    // Check if the product is already in the cart
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Increase quantity by 1
        $newQty = $existing['quantity'] + 1;
        $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmtUpdate->execute([$newQty, $existing['id']]);
        echo json_encode(['success' => true, 'message' => 'Item quantity updated in cart.']);
    } else {
        // Add new item to cart with quantity 1
        $stmtInsert = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $stmtInsert->execute([$user_id, $product_id]);
        echo json_encode(['success' => true, 'message' => 'Item added to cart.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>