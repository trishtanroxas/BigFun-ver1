<?php
session_start();
include "db.php";
include "notification_helper.php"; // ADDED: Include the notification helper
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id'] ?? 0);
$reference_code = trim($_POST['reference_code'] ?? '');

if ($order_id <= 0 || empty($reference_code)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID or empty reference code.']);
    exit;
}

try {
    // Verify the order belongs to the user and is a cash payment
    // MODIFIED: Also check that status is 'Pending'
    $stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND payment_method = 'Cash' AND payment_status = 'Pending'");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found, is not a cash payment order, or is no longer pending.");
    }
    $stmt->close();

    // Update the order with the reference code and set status to 'For Verification'
    $update_stmt = $conn->prepare("UPDATE orders SET reference_code = ?, payment_status = 'For Verification' WHERE id = ?");
    $update_stmt->bind_param("si", $reference_code, $order_id);
    
    if ($update_stmt->execute()) {
        
        // --- ADDED: CREATE NOTIFICATION ---
        create_notification(
            $conn,
            $user_id,
            "Reference Code Submitted",
            "Your reference code for order #{$order_id} has been submitted. An admin will verify your payment shortly.",
            "invoices.php"
        );
        // --- END NOTIFICATION ---

        echo json_encode(['status' => 'success', 'message' => 'Reference code submitted for verification.']);
    } else {
        throw new Exception("Failed to submit reference code.");
    }
    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>

