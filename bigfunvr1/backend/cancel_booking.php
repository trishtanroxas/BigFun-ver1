<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
// ❗ NEW: Get the cancellation reason from the form
$reason = trim($_POST['reason'] ?? 'Cancelled by user from dashboard.');

// Start a transaction to ensure both operations succeed or fail together
$conn->begin_transaction();

try {
    // 1. Update the booking status in the 'orders' table
    $stmt = $conn->prepare("
        UPDATE orders 
        SET booking_status = 'Cancelled' 
        WHERE id = ? AND user_id = ? AND booking_status = 'Pending Confirmation'
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // 2. ❗ MODIFIED: Insert a record with the reason into the 'booking_cancellations' table
        $insert_stmt = $conn->prepare("
            INSERT INTO booking_cancellations (order_id, user_id, cancelled_by, reason) 
            VALUES (?, ?, 'User', ?)
        ");
        $insert_stmt->bind_param("iis", $order_id, $user_id, $reason);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Booking has been cancelled.']);

    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Booking could not be cancelled. It may have already been confirmed or cancelled.']);
    }
    $stmt->close();

} catch (mysqli_sql_exception $exception) {
    $conn->rollback();
    error_log("Cancellation failed: " . $exception->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}

$conn->close();
?>