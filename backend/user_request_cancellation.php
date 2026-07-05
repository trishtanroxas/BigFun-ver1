<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require 'mail_config.php';
require 'email_template_user_cancel_request.php';
require 'email_template_admin_cancellation_alert.php';
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = intval($_POST['order_id'] ?? 0);

// Handle varied cancellation reasons
$reason_option = trim($_POST['reason_option'] ?? '');
$other_reason = trim($_POST['other_reason'] ?? '');
$final_reason = $reason_option;
if ($reason_option === 'Other' && !empty($other_reason)) {
    $final_reason = "Other: " . $other_reason;
} elseif (empty($reason_option)) {
    $final_reason = "No reason provided";
}


if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Order ID.']);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("SELECT o.*, s.first_name, s.last_name, s.email FROM orders o JOIN signup s ON o.user_id = s.id WHERE o.id = ? AND o.user_id = ? FOR UPDATE");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Order not found or you do not have permission to modify it.");
    }
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!in_array($data['booking_status'], ['Confirmed', 'Pending Confirmation'])) {
        throw new Exception("This booking cannot be cancelled as it is already {$data['booking_status']}.");
    }

    $request_time = date("Y-m-d H:i:s");
    $update_stmt = $conn->prepare("UPDATE orders SET booking_status = 'Pending Cancellation', cancellation_reason = ?, cancellation_timestamp = ? WHERE id = ?");
    $update_stmt->bind_param("ssi", $final_reason, $request_time, $order_id);
    $update_stmt->execute();
    $update_stmt->close();

    $customer_name = trim($data['first_name'] . ' ' . $data['last_name']);
    
    // Send email to USER
    $mail_user = new PHPMailer(true);
    try {
        configureSMTP($mail_user);
        $mail_user->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail_user->addAddress($data['email'], $customer_name);
        $mail_user->isHTML(true);
        $mail_user->Subject = 'Cancellation Request Received for Order #' . $order_id;
        $mail_user->Body    = generateUserCancelRequestEmail($customer_name, $order_id);
        $mail_user->send();
    } catch (Exception $e) { error_log("User Cancellation Request Mailer Error: {$mail_user->ErrorInfo}"); }

    // Send email to ADMIN
    $mail_admin = new PHPMailer(true);
    try {
        configureSMTP($mail_admin);
        $mail_admin->setFrom(MAIL_FROM, 'BigFun System');
        $mail_admin->addAddress(MAIL_ADMIN, 'BigFun Admin');
        $mail_admin->isHTML(true);
        $mail_admin->Subject = 'ACTION REQUIRED: New Cancellation Request for Order #' . $order_id;
        $mail_admin->Body    = generateAdminCancellationAlert($customer_name, $order_id, $data['date_event'], $request_time, $final_reason);
        $mail_admin->send();
    } catch (Exception $e) { error_log("Admin Cancellation Alert Mailer Error: {$mail_admin->ErrorInfo}"); }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Your cancellation request has been submitted for admin approval.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>