<?php
session_start();
// Set the content type to JSON for API responses
header('Content-Type: application/json');

// Immediately stop if the user is not logged in. Send a 401 Unauthorized status.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

include "db.php";

// --- 1. Validate Input ---
// Safely get the order_id from the GET request.
$order_id = intval($_GET['order_id'] ?? 0);
$user_id = $_SESSION['user_id'];

// Ensure the Order ID is a valid positive number. Send a 400 Bad Request status if not.
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid Order ID is required.']);
    exit;
}

// --- 2. Fetch Core Order Data (with Security Check) ---
// Prepare a query to fetch the order, ensuring it belongs to the currently logged-in user.
// This is a CRITICAL security measure to prevent users from viewing other people's orders.
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();
$stmt->close();

// If no order is found, send a 404 Not Found status.
if (!$order) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Order not found or you do not have permission to view it.']);
    exit;
}

// --- 3. Fetch Associated Order Items ---
$items = [];
$item_stmt = $conn->prepare("
    SELECT oi.quantity, oi.price_per_item, s.service_name 
    FROM order_items oi 
    JOIN services s ON oi.service_id = s.id 
    WHERE oi.order_id = ?
");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items_result = $item_stmt->get_result();
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$item_stmt->close();

// --- 4. Fetch Payment History ---
$payments = [];
// Assuming your payment table is named 'order_payments' as in your original code.
$payment_stmt = $conn->prepare("SELECT payment_amount, payment_date, payment_type FROM order_payments WHERE order_id = ? ORDER BY payment_date ASC");
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payments_result = $payment_stmt->get_result();
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}
$payment_stmt->close();

// --- 5. Perform Calculations on the Backend ---
// Calculate total paid amount and the remaining balance.
$total_paid = array_sum(array_column($payments, 'payment_amount'));
$remaining_balance = $order['total_amount'] - $total_paid;

// Add the calculated values to the order details for easy use on the frontend.
$order['total_paid'] = $total_paid;
$order['remaining_balance'] = $remaining_balance;

// --- 6. Send the Final Successful Response ---
// If all queries were successful, send a 200 OK status with the complete data package.
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);

$conn->close();
exit;
?>