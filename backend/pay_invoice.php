<?php
session_start();
include "db.php";
include "notification_helper.php"; // ADDED: Include the notification helper

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid Request.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $order_id = intval($_POST['order_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_type = trim($_POST['payment_type'] ?? ''); // This will be 'full' or 'installment'

    // --- MODIFIED: This validation now accepts 'full' OR 'installment' ---
    if ($order_id <= 0 || $amount <= 0 || !in_array($payment_type, ['full', 'installment'])) {
        $response['message'] = 'Invalid payment details provided (must be full or installment).';
        http_response_code(400);
        echo json_encode($response);
        exit;
    }
    // --- END MODIFICATION ---

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

        $current_remaining = floatval($order['remaining_balance']);

        // --- MODIFIED: Handle both payment types ---
        if ($payment_type === 'full') {
            if (abs($current_remaining - $amount) > 0.01) {
                throw new Exception("Full payment amount ($" . number_format($amount, 2) . ") does not match the remaining balance ($" . number_format($current_remaining, 2) . "). Please refresh.");
            }
        } else { // 'installment'
            if ($amount > $current_remaining + 0.01) { // Add 0.01 for float comparison
                 throw new Exception("Installment amount ($" . number_format($amount, 2) . ") is greater than the remaining balance ($" . number_format($current_remaining, 2) . ").");
            }
        }
        // --- END MODIFICATION ---
        
        $payment_method = 'Card'; // All payments from this page are via card
        $transaction_id = 'TXN-' . strtoupper(uniqid());

        // This query now correctly inserts into your `order_payments` table.
        $log_stmt = $conn->prepare("INSERT INTO order_payments (order_id, payment_amount, payment_date, payment_method, transaction_id, payment_type) VALUES (?, ?, NOW(), ?, ?, ?)");
        $log_stmt->bind_param("idsss", $order_id, $amount, $payment_method, $transaction_id, $payment_type);
        $log_stmt->execute();
        $log_stmt->close();

        // --- MODIFIED: Logic to handle full vs. partial payment updates ---
        $new_remaining_balance = round($current_remaining - $amount, 2);
        $new_payment_status = '';
        $new_next_due_date = $order['next_due_date']; // Keep existing by default
        
        $notif_title = "";
        $notif_message = "";

        if ($new_remaining_balance < 0.01) {
            // PAID IN FULL
            $new_payment_status = 'Paid';
            $new_next_due_date = NULL;
            $update_stmt = $conn->prepare("UPDATE orders SET remaining_balance = 0, payment_status = 'Paid', paid_date = NOW(), next_due_date = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $order_id);
            
            $notif_title = "Payment Successful";
            $notif_message = "Your payment of $" . number_format($amount, 2) . " for order #{$order_id} was successful. This order is now fully paid.";

        } else {
            // PARTIALLY PAID (INSTALLMENT)
            $new_payment_status = 'Partially Paid';
            
            // Calculate next due date (1 month from now or 1 month from last due date)
            $current_due = new DateTime($order['next_due_date'] ?? 'now');
            $current_due->modify('+1 month');
            $new_next_due_date = $current_due->format('Y-m-d');
            
            $update_stmt = $conn->prepare("UPDATE orders SET remaining_balance = ?, payment_status = 'Partially Paid', next_due_date = ? WHERE id = ?");
            $update_stmt->bind_param("dsi", $new_remaining_balance, $new_next_due_date, $order_id);
            
            $notif_title = "Installment Payment Received";
            $notif_message = "Your installment payment of $" . number_format($amount, 2) . " for order #{$order_id} was successful. New balance: $" . number_format($new_remaining_balance, 2) . ".";
        }
        
        $update_stmt->execute();
        $update_stmt->close();
        // --- END MODIFIED UPDATE LOGIC ---
        
        // --- ADDED: CREATE NOTIFICATION ---
        create_notification(
            $conn,
            $user_id,
            $notif_title,
            $notif_message,
            "invoices.php"
        );
        // --- END NOTIFICATION ---

        $conn->commit();
        
        // MODIFIED: Send back all new data so the invoice page can update itself
        $response = [
            'status' => 'success',
            'message' => 'Payment processed successfully.',
            'new_remaining_balance' => $new_remaining_balance,
            'new_payment_status' => $new_payment_status,
            'new_next_due_date' => $new_next_due_date
        ];
        // --- END MODIFICATION ---

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(422); // Unprocessable Entity
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
exit;
?>