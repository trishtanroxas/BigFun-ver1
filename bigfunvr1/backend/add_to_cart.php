<?php
session_start();
include "db.php";

header('Content-Type: application/json');

// Default response
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

// Ensure it's a POST request with the required data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_id']) && isset($_POST['quantity'])) {
    
    $user_id = $_SESSION['user_id'];
    $service_id = intval($_POST['service_id']);
    $quantity = intval($_POST['quantity']);

    // --- FIX: Fetch user's email directly from the database ---
    $email_stmt = $conn->prepare("SELECT email FROM signup WHERE id = ?");
    $email_stmt->bind_param("i", $user_id);
    $email_stmt->execute();
    $user_result = $email_stmt->get_result()->fetch_assoc();
    $user_email = $user_result['email'] ?? null;
    $email_stmt->close();

    if ($quantity > 0 && !empty($user_email)) {
        // Check if the item already exists in the cart
        $stmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND service_id = ?");
        $stmt->bind_param("ii", $user_id, $service_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Item exists, UPDATE the quantity
            $existing_item = $result->fetch_assoc();
            $new_quantity = $existing_item['quantity'] + $quantity;
            $update_stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $new_quantity, $existing_item['id']);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // Item does not exist, INSERT a new row
            $insert_stmt = $conn->prepare("INSERT INTO cart_items (user_id, user_email, service_id, quantity) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("isii", $user_id, $user_email, $service_id, $quantity);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
        $stmt->close();
        
        // Calculate the new total cart count
        $count_stmt = $conn->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = ?");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $new_cart_count = $count_result['total_items'] ?? 0;
        $count_stmt->close();
        
        $response = ['status' => 'success', 'message' => 'Cart updated!', 'cart_count' => $new_cart_count];
    } else {
        $response['message'] = 'Invalid quantity or could not find user email.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

$conn->close();
echo json_encode($response);
exit;
?>