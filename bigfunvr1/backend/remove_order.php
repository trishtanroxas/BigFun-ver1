<?php
session_start();
header('Content-Type: application/json');

// 1. Security: Check for valid session and request method
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to perform this action.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

include 'db.php';

// 2. Input Validation
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID provided.']);
    exit;
}

// 3. Server-Side Verification (CRITICAL)
// Before deleting, we MUST verify three things:
// a) The order exists.
// b) The order belongs to the currently logged-in user.
// c) The order's booking status is 'Cancelled'.
$stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND booking_status = 'Cancelled' LIMIT 1");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // If no row is found, it's either not the user's order, it doesn't exist, or it's not cancelled.
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to remove this order record or it cannot be removed.']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();


// 4. Perform Deletion
// We only reach this point if all checks have passed.
$delete_stmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
$delete_stmt->bind_param("ii", $order_id, $user_id);

if ($delete_stmt->execute()) {
    // If you set up cascading deletes in your database, related items/payments will also be deleted.
    echo json_encode(['status' => 'success', 'message' => 'Order record has been removed.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove the order record from the database.']);
}

$delete_stmt->close();
$conn->close();
?>