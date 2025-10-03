<?php
// FILE: backend/process_payment.php (MERGED & IMPROVED)

session_start();
include "db.php";
require '../vendor/autoload.php';
require 'payment_email_template.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// --- 1. Validation ---
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id']) || !isset($_POST['amount']) || !isset($_POST['payment_type'])) {
    $response['message'] = 'Invalid request. Missing required payment details.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id']);
$amount_paid = floatval($_POST['amount']);
$payment_type = trim($_POST['payment_type']); // Use the payment type sent from the form

// --- 2. Start Transaction ---
// ADDED: Using a transaction ensures both logging the payment and updating the order succeed or fail together.
$conn->begin_transaction();

try {
    // --- 3. Log the Payment ---
    $log_stmt = $conn->prepare("INSERT INTO order_payments (order_id, payment_amount, payment_method, payment_type) VALUES (?, ?, 'Card', ?)");
    $log_stmt->bind_param("ids", $order_id, $amount_paid, $payment_type);
    $log_stmt->execute();
    $log_stmt->close();

    // --- 4. Fetch Order and User Data ---
    // Added "FOR UPDATE" to lock the row during the transaction, preventing race conditions.
    $order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $order_stmt->bind_param("ii", $order_id, $user_id);
    $order_stmt->execute();
    $order = $order_stmt->get_result()->fetch_assoc();
    $order_stmt->close();

    if (!$order) {
        throw new Exception('Order not found or access denied.');
    }

    $user_stmt = $conn->prepare("SELECT email, first_name FROM signup WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    // --- 5. Recalculate Balances and Determine New Status ---
    $total_paid_stmt = $conn->prepare("SELECT SUM(payment_amount) as total_paid FROM order_payments WHERE order_id = ?");
    $total_paid_stmt->bind_param("i", $order_id);
    $total_paid_stmt->execute();
    $total_paid = $total_paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;
    $total_paid_stmt->close();

    $new_remaining = $order['total_amount'] - $total_paid;
    $new_paid_count = $order['installments_paid'] + 1;
    $new_status = 'Partially Paid';
    $new_paid_date = null;
    $next_due = $order['next_due_date']; // Default to the current due date

    // CRITICAL FIX: Correctly calculate the next due date for installments.
    if ($order['payment_method'] === 'Installment') {
        $current_due_date = new DateTime($order['next_due_date'] ?? 'now');
        $next_due = $current_due_date->modify('+1 month')->format('Y-m-d');
    }

    if ($new_remaining <= 0.01) {
        $new_remaining = 0;
        $new_status = 'Paid';
        $new_paid_date = date("Y-m-d H:i:s");
        $next_due = null; // No next due date if fully paid
    }

    // --- 6. Update the Main Order Record ---
    $update_stmt = $conn->prepare("UPDATE orders SET remaining_balance = ?, installments_paid = ?, next_due_date = ?, payment_status = ?, paid_date = ? WHERE id = ?");
    $update_stmt->bind_param("disssi", $new_remaining, $new_paid_count, $next_due, $new_status, $new_paid_date, $order_id);
    $update_stmt->execute();
    $update_stmt->close();

    // --- 7. Commit Transaction ---
    $conn->commit();

    // --- 8. Send Confirmation Email ---
    $order['remaining_balance'] = $new_remaining;
    $order['next_due_date'] = $next_due;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alex1925tan@gmail.com'; // Use environment variables for sensitive data in production
        $mail->Password   = 'REDACTED_SMTP_PASSWORD'; // Use environment variables
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('alex1925tan@gmail.com', 'BigFun');
        $mail->addAddress($user['email'], $user['first_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmation for Order #' . $order_id;
        $mail->Body    = generatePaymentConfirmationEmail($user, $order, $amount_paid);
        $mail->send();
    } catch (Exception $e) {
        // Log error but don't fail the entire process if email fails
        error_log("Payment confirmation email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
    
    // --- 9. Send Success Response ---
    $response = [
        'status' => 'success',
        'message' => 'Payment successful!',
        'new_remaining_balance' => $new_remaining,
        'new_next_due_date' => $next_due,
        'new_status' => $new_status,
    ];

} catch (Exception $e) {
    $conn->rollback(); // If anything fails, undo all database changes
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>