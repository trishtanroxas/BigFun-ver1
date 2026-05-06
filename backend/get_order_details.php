<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

include "db.php";

$order_id = intval($_GET['order_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'A valid Order ID is required.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Order not found.']);
    exit;
}

// --- Logic to handle status overrides and generate notices ---
$order['cancellation_notice'] = ''; 

$event_date = new DateTime($order['date_event']);
$today = new DateTime('today');
if ($event_date < $today && $order['booking_status'] === 'Pending Confirmation') {
    $order['booking_status'] = 'Not Serviced (Expired)';
}

if ($order['booking_status'] === 'Pending Cancellation' && !empty($order['cancellation_timestamp'])) {
    $cancellation_date = date("F j, Y, g:i a", strtotime($order['cancellation_timestamp']));
    $order['cancellation_notice'] = "A cancellation request was submitted for this booking on {$cancellation_date}. It is currently awaiting admin approval.";
} elseif (in_array($order['booking_status'], ['Cancelled', 'Refunded']) && !empty($order['cancellation_timestamp'])) {
     $cancellation_date = date("F j, Y", strtotime($order['cancellation_timestamp']));
    $order['cancellation_notice'] = "This booking was cancelled on {$cancellation_date}.";
}

// --- Retroactively Log Initial Card Payment ---
if ($order['payment_method'] === 'Card' && $order['payment_status'] === 'Paid' && !empty($order['paid_date'])) {
    $check_stmt = $conn->prepare("SELECT id FROM order_payments WHERE order_id = ? AND payment_type = 'Initial Card Payment'");
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $payment_exists = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();
    if (!$payment_exists) {
        $insert_stmt = $conn->prepare("INSERT INTO order_payments (order_id, payment_amount, payment_date, payment_type) VALUES (?, ?, ?, 'Initial Card Payment')");
        $insert_stmt->bind_param("ids", $order_id, $order['total_amount'], $order['paid_date']);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}

// --- Fetch items and payments ---
$items = [];
$item_stmt = $conn->prepare("SELECT oi.quantity, oi.price_per_item, s.service_name FROM order_items oi JOIN services s ON oi.service_id = s.id WHERE oi.order_id = ?");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items_result = $item_stmt->get_result();
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$item_stmt->close();

$payments = [];
$payment_stmt = $conn->prepare("SELECT payment_amount, payment_date, payment_type FROM order_payments WHERE order_id = ? ORDER BY payment_date ASC");
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payments_result = $payment_stmt->get_result();
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}
$payment_stmt->close();

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