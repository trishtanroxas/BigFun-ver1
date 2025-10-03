<?php
session_start();
include "db.php";
// You can add email sending logic here if needed later

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid Request.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = trim($_POST['payment_type'] ?? '');

    if ($order_id <= 0 || $amount <= 0 || $payment_type !== 'Full Payment') {
        $response['message'] = 'Invalid payment details provided.';
        echo json_encode($response);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Lock the row to prevent simultaneous payments
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            throw new Exception("Order not found or you do not have permission to pay for it.");
        }

        if (abs(floatval($order['remaining_balance']) - $amount) > 0.01) {
             throw new Exception("Payment amount does not match the remaining balance. Please refresh the page.");
        }
        
        // --- FIXED: Code updated to match your 'order_payments' table structure ---
        $payment_method = 'Card'; // All payments from this page are via card
        $transaction_id = 'TXN-' . strtoupper(uniqid()); // Generate a unique transaction ID

        $log_stmt = $conn->prepare("INSERT INTO order_payments (order_id, payment_amount, payment_date, payment_method, transaction_id, payment_type) VALUES (?, ?, NOW(), ?, ?, ?)");
        $log_stmt->bind_param("idsss", $order_id, $amount, $payment_method, $transaction_id, $payment_type);
        $log_stmt->execute();
        $log_stmt->close();

        // Update the main order to be fully paid
        $update_stmt = $conn->prepare("UPDATE orders SET remaining_balance = 0, payment_status = 'Paid', paid_date = NOW(), next_due_date = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $order_id);
        $update_stmt->execute();
        $update_stmt->close();

        $conn->commit();
        
        $response = ['status' => 'success', 'message' => 'Payment processed successfully.'];

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
exit;
?>