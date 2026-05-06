<?php
session_start();
header('Content-Type: application/json');
include "db.php";

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    // Fetch all orders that require some form of admin action
    $query = "
        SELECT 
            o.id,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            o.date_event,
            o.booking_status,
            o.payment_status,
            o.cancellation_reason,
            o.reference_code
        FROM orders o
        JOIN signup s ON o.user_id = s.id
        WHERE 
            o.booking_status = 'Pending Confirmation' OR 
            o.booking_status = 'Pending Cancellation' OR 
            o.payment_status = 'For Verification'
        ORDER BY o.date_event ASC
    ";
    
    $result = $conn->query($query);
    $actionable_orders = $result->fetch_all(MYSQLI_ASSOC);

    // Categorize the orders
    $confirmations = [];
    $cancellations = [];
    $verifications = [];

    foreach ($actionable_orders as $order) {
        if ($order['booking_status'] === 'Pending Confirmation') {
            $confirmations[] = $order;
        } elseif ($order['booking_status'] === 'Pending Cancellation') {
            $cancellations[] = $order;
        } elseif ($order['payment_status'] === 'For Verification') {
            $verifications[] = $order;
        }
    }

    echo json_encode([
        'status' => 'success',
        'confirmations' => $confirmations,
        'cancellations' => $cancellations,
        'verifications' => $verifications
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>