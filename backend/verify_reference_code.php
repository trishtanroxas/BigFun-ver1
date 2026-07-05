<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require 'mail_config.php';
require 'email_template_payment_verified.php';
include "db.php";

header('Content-Type: application/json');

// Admin authentication check
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Administrator access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID.']);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Fetch order and customer details
    $stmt = $conn->prepare("
        SELECT o.id, o.total_amount, o.reference_code, s.first_name, s.last_name, s.email
        FROM orders o
        JOIN signup s ON o.user_id = s.id
        WHERE o.id = ? AND o.payment_status = 'For Verification'
        FOR UPDATE
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found or is not awaiting verification.");
    }
    $data = $result->fetch_assoc();
    $stmt->close();

    // 2. Update the order status to 'Paid'
    $paid_date = date("Y-m-d H:i:s");
    $update_stmt = $conn->prepare(
        "UPDATE orders SET payment_status = 'Paid', remaining_balance = 0, paid_date = ? WHERE id = ?"
    );
    $update_stmt->bind_param("si", $paid_date, $order_id);
    $update_stmt->execute();
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception("Failed to update the order status.");
    }
    $update_stmt->close();
    
    // 3. ✨ FIX: Insert a record into the payment history table
    $log_stmt = $conn->prepare(
        "INSERT INTO order_payments (order_id, payment_amount, payment_date, payment_method, transaction_id, payment_type) VALUES (?, ?, ?, 'Cash', ?, 'Verified Payment')"
    );
    $log_stmt->bind_param("idss", $order_id, $data['total_amount'], $paid_date, $data['reference_code']);
    $log_stmt->execute();
    $log_stmt->close();


    // 4. Send confirmation email to the customer
    $customer_name = trim($data['first_name'] . ' ' . $data['last_name']);
    $mail = new PHPMailer(true);
    try {
        configureSMTP($mail);

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($data['email'], $customer_name);
        $mail->isHTML(true);
        $mail->Subject = 'Payment Verified for Your Order #' . $order_id;
        $mail->Body    = generatePaymentVerifiedEmailBody($customer_name, $order_id, $data['total_amount']);
        
        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error for Order #{$order_id}: {$mail->ErrorInfo}");
    }

    // Commit the transaction
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Payment verified and customer has been notified.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>