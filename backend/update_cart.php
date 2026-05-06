<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

include "db.php"; // Include your database connection

$user_id = $_SESSION['user_id'];
$cart_item_id = $_POST['cart_item_id'] ?? 0;
$quantity = $_POST['quantity'] ?? 0;

// Validate input
if (empty($cart_item_id) || !is_numeric($cart_item_id)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid item ID.']);
    exit;
}

if (!is_numeric($quantity) || $quantity < 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid quantity.']);
    exit;
}

// --- Database Update Logic ---
$conn->begin_transaction();

try {
    
    // ** NEW: Check the max quantity for this item **
    $max_quantity = 1; // Default
    if ($quantity > 0) { // No need to check if we are deleting
        $stmt_check = $conn->prepare("
            SELECT s.service_name 
            FROM cart_items ci
            JOIN services s ON ci.service_id = s.id
            WHERE ci.id = ? AND ci.user_id = ?
        ");
        $stmt_check->bind_param("ii", $cart_item_id, $user_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result()->fetch_assoc();
        
        if ($check_result) {
            if ($check_result['service_name'] === 'Premium Mechanical Bull QLD') {
                $max_quantity = 2;
            }
        }
        $stmt_check->close();
        
        // Enforce the limit
        if ($quantity > $max_quantity) {
            // Silently set quantity to the max allowed
            $quantity = $max_quantity; 
        }
    }
    // ** END NEW **

    if ($quantity == 0) {
        // Remove item from cart
        $stmt_delete = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
        $stmt_delete->bind_param("ii", $cart_item_id, $user_id);
        $stmt_delete->execute();
        $stmt_delete->close();
    } else {
        // Update item quantity (with the potentially corrected quantity)
        $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt_update->bind_param("iii", $quantity, $cart_item_id, $user_id);
        $stmt_update->execute();
        $stmt_update->close();
    }

    // --- Recalculate Totals ---
    $cart_total = 0;
    $cart_count = 0;

    $cart_stmt = $conn->prepare("
        SELECT ci.quantity, s.price
        FROM cart_items ci
        JOIN services s ON ci.service_id = s.id
        WHERE ci.user_id = ?
    ");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $result = $cart_stmt->get_result();

    if ($result->num_rows > 0) {
        while ($item = $result->fetch_assoc()) {
            $cart_total += $item['price'] * $item['quantity'];
            $cart_count += $item['quantity'];
        }
    }
    $cart_stmt->close();

    // Commit changes
    $conn->commit();
    
    // Send back the new totals
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'cart_total' => number_format($cart_total, 2),
        'cart_count' => $cart_count
    ]);

} catch (Exception $e) {
    $conn->rollback();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>