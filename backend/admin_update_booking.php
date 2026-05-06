<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
include "db.php";

// --- Email Template Functions ---
function generateBookingConfirmedEmailBody($customer_name, $order_id, $event_date) {
    return <<<HTML
<!DOCTYPE html><html><body><h1>Your Booking #{$order_id} is Confirmed!</h1><p>Hello {$customer_name},</p><p>Great news! Your booking for {$event_date} has been confirmed by our team. We look forward to seeing you!</p></body></html>
HTML;
}
function generateCancellationApprovedEmailBody($customer_name, $order_id, $refund_amount) {
    return <<<HTML
<!DOCTYPE html><html><body><h1>Your Cancellation for Order #{$order_id} is Approved</h1><p>Hello {$customer_name},</p><p>Your request to cancel has been approved. A refund of $${refund_amount} has been processed.</p></body></html>
HTML;
}
function generatePaymentVerifiedEmailBody($customer_name, $order_id, $total_amount) {
    return <<<HTML
<!DOCTYPE html><html><body><h1>Payment Verified for Order #{$order_id}</h1><p>Hello {$customer_name},</p><p>Your cash payment of $${total_amount} for Order #{$order_id} has been verified. Thank you!</p></body></html>
HTML;
}

// --- Main Logic ---
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Administrator access required.']);
    exit;
}

$action = $_POST['action'] ?? '';
$order_id = intval($_POST['order_id'] ?? 0);

if ($order_id <= 0 || empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID or Action.']);
    exit;
}

$conn->begin_transaction();

try {
    // Fetch the order and customer data
    $stmt = $conn->prepare("SELECT o.*, s.first_name, s.last_name, s.email FROM orders o JOIN signup s ON o.user_id = s.id WHERE o.id = ? FOR UPDATE");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception("Order not found.");
    $data = $result->fetch_assoc();
    $stmt->close();

    $customer_name = trim($data['first_name'] . ' ' . $data['last_name']);
    $success_message = '';

    // Use a switch to handle different actions
    switch ($action) {
        case 'confirm_booking':
            if ($data['booking_status'] !== 'Pending Confirmation') throw new Exception("Order is not pending confirmation.");
            $update_stmt = $conn->prepare("UPDATE orders SET booking_status = 'Confirmed' WHERE id = ?");
            $update_stmt->bind_param("i", $order_id);
            $update_stmt->execute();
            $update_stmt->close();
            $email_subject = 'Your Booking is Confirmed! (Order #' . $order_id . ')';
            $email_body = generateBookingConfirmedEmailBody($customer_name, $order_id, $data['date_event']);
            $success_message = 'Booking confirmed and customer notified.';
            break;

        case 'approve_cancellation':
            if ($data['booking_status'] !== 'Pending Cancellation') throw new Exception("Order is not pending cancellation.");
            $cancellation_time = date("Y-m-d H:i:s");
            $update_stmt = $conn->prepare("UPDATE orders SET booking_status = 'Cancelled', payment_status = 'Refunded', cancellation_timestamp = ? WHERE id = ?");
            $update_stmt->bind_param("si", $cancellation_time, $order_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $total_paid = floatval($data['total_amount']) - floatval($data['remaining_balance']);
            if ($total_paid > 0) {
                $refund_amount = -abs($total_paid);
                $log_stmt = $conn->prepare("INSERT INTO order_payments (order_id, payment_amount, payment_date, payment_type) VALUES (?, ?, ?, 'Refund')");
                $log_stmt->bind_param("ids", $order_id, $refund_amount, $cancellation_time);
                $log_stmt->execute();
                $log_stmt->close();
            }
            $email_subject = 'Cancellation Approved for Order #' . $order_id;
            $email_body = generateCancellationApprovedEmailBody($customer_name, $order_id, number_format($total_paid, 2));
            $success_message = 'Cancellation approved and customer notified.';
            break;
            
        case 'verify_payment':
            if ($data['payment_status'] !== 'For Verification') throw new Exception("Order is not awaiting payment verification.");
            $paid_date = date("Y-m-d H:i:s");
            $update_stmt = $conn->prepare("UPDATE orders SET payment_status = 'Paid', remaining_balance = 0, paid_date = ? WHERE id = ?");
            $update_stmt->bind_param("si", $paid_date, $order_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $log_stmt = $conn->prepare("INSERT INTO order_payments (order_id, payment_amount, payment_date, payment_method, transaction_id, payment_type) VALUES (?, ?, ?, 'Cash', ?, 'Verified Payment')");
            $log_stmt->bind_param("idss", $order_id, $data['total_amount'], $paid_date, $data['reference_code']);
            $log_stmt->execute();
            $log_stmt->close();
            
            $email_subject = 'Payment Verified for Your Order #' . $order_id;
            $email_body = generatePaymentVerifiedEmailBody($customer_name, $order_id, number_format($data['total_amount'], 2));
            $success_message = 'Payment verified and customer has been notified.';
            break;

        default:
            throw new Exception("Invalid action specified.");
    }

    // Send the appropriate email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true; $mail->Username = 'alex1925tan@gmail.com'; $mail->Password = 'REDACTED_SMTP_PASSWORD'; $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
        $mail->setFrom('alex1925tan@gmail.com', 'BigFun');
        $mail->addAddress($data['email'], $customer_name);
        $mail->isHTML(true);
        $mail->Subject = $email_subject;
        $mail->Body = $email_body;
        $mail->send();
    } catch (Exception $e) { error_log("Admin Action Mailer Error ({$action}): {$mail->ErrorInfo}"); }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => $success_message]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>