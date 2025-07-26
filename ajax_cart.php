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

$input = json_decode(file_get_contents('php://input'), true);
$product_id = intval($input['product_id'] ?? 0);
$quantityChange = intval($input['quantity'] ?? 0);

if ($product_id <= 0 || $quantityChange === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity change.']);
    exit;
}

try {
    // Check if the product is already in the cart
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $newQty = $existing['quantity'] + $quantityChange;
        if ($newQty <= 0) {
            // Remove the item if quantity drops to zero or below
            $stmtDelete = $pdo->prepare("DELETE FROM cart WHERE id = ?");
            $stmtDelete->execute([$existing['id']]);
            echo json_encode(['success' => true, 'message' => 'Item removed from cart.']);
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmtUpdate->execute([$newQty, $existing['id']]);
            echo json_encode(['success' => true, 'message' => 'Cart updated successfully.']);
        }
    } else {
        // If item not in cart and quantityChange is positive, insert new row
        if ($quantityChange > 0) {
            $stmtInsert = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmtInsert->execute([$user_id, $product_id, $quantityChange]);
            echo json_encode(['success' => true, 'message' => 'Item added to cart.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not in cart.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}