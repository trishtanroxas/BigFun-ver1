<?php
session_start();
include "db.php"; // Make sure this path is correct

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to add items.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$service_id = $_POST['service_id'] ?? 0;
$quantity_to_add = $_POST['quantity'] ?? 1;

if (empty($service_id) || $quantity_to_add <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid service or quantity.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Get Service Name and Set Max Quantity
    $stmt_service = $conn->prepare("SELECT service_name FROM services WHERE id = ?");
    $stmt_service->bind_param("i", $service_id);
    $stmt_service->execute();
    $service_result = $stmt_service->get_result()->fetch_assoc();
    
    if (!$service_result) {
        throw new Exception('Service not found.');
    }
    
    $service_name = $service_result['service_name'];
    $max_quantity = 1; // Default limit for all items
    if ($service_name === 'Premium Mechanical Bull QLD') {
        $max_quantity = 2; // Special limit
    }
    $stmt_service->close();
    
    // 2. Check current quantity in cart
    $current_quantity = 0;
    $stmt_check = $conn->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND service_id = ?");
    $stmt_check->bind_param("ii", $user_id, $service_id);
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    if ($check_result->num_rows > 0) {
        $current_quantity = $check_result->fetch_assoc()['quantity'];
    }
    $stmt_check->close();
    
    // 3. Check if new quantity exceeds max
    $new_quantity = $current_quantity + $quantity_to_add;
    
    if ($new_quantity > $max_quantity) {
        if ($current_quantity > 0) {
            throw new Exception("You can only have {$max_quantity} of this item in your cart. You already have {$current_quantity}.");
        } else {
            throw new Exception("You can only add {$max_quantity} of this item to your cart.");
        }
    }
    
    // 4. Add or update cart (This handles both new items and items already in cart)
    $stmt_upsert = $conn->prepare("
        INSERT INTO cart_items (user_id, service_id, quantity) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = ?
    ");
    $stmt_upsert->bind_param("iiii", $user_id, $service_id, $new_quantity, $new_quantity);
    $stmt_upsert->execute();
    $stmt_upsert->close();

    // 5. Get new total cart count for the header
    $cart_count = 0;
    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $cart_count = $count_result['total_items'] ?? 0;
    $count_stmt->close();
    
    $conn->commit();
    echo json_encode(['status' => 'success', 'cart_count' => $cart_count]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>