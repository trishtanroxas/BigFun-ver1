<?php
session_start();

// --- Advanced Error Handling ---
// We will catch any PHP errors and send them as JSON
error_reporting(0);
ini_set('display_errors', 0);
$error_list = [];

set_error_handler(function($severity, $message, $file, $line) use (&$error_list) {
    $error_list[] = "Error: [$severity] $message in $file on line $line";
});

register_shutdown_function(function() use (&$error_list) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (headers_sent() === false) { // Ensure we can send JSON headers
             header('Content-Type: application/json');
             http_response_code(500); // Internal Server Error
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal PHP Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'],
            'all_errors' => $error_list
        ]);
        exit;
    }
});
// --- END Error Handling ---


// --- MODIFIED: Use __DIR__ for reliable file paths ---
if (!@include_once __DIR__ . "/db.php") {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Fatal Error: Could not include "db.php".']);
    exit;
}

// --- CHECK 1: notification_helper.php (for create_notification function) ---
if (!@include_once __DIR__ . "/notification_helper.php") {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Fatal Error: Could not include "notification_helper.php". Please check the file path.']);
    exit;
}

// --- ADDED: PHPMailer Requirements ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CHECK 2: vendor/autoload.php ---
// Use __DIR__ to make path relative to this 'backend' folder
if (!@require __DIR__ . '/../vendor/autoload.php') {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Fatal Error: Could not require "../vendor/autoload.php". Please check the file path.']);
    exit;
}
// --- END PHPMailer ---

header('Content-Type: application/json');
$response = ['status' => 'error', 'message' => 'Invalid action.'];

// --- Admin Security Check ---
if (!isset($_SESSION['admin_id'])) {
    $response['message'] = 'Unauthorized: Admin access required.';
    echo json_encode($response);
    exit;
}

// ##################################################################
// ###                  EMAIL TEMPLATE FUNCTIONS                  ###
// ##################################################################

// --- TEMPLATE 1: Booking Confirmed ---
function generateBookingConfirmedEmail($customer_name, $order_id) {
    return <<<HTML
    <!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333}.container{max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px}.header{padding:10px 20px;text-align:center;border-radius:8px 8px 0 0}.content h1{color:#333}.info-box{background-color:#f9f9f9;padding:15px;border-radius:5px;margin-top:20px}</style></head>
    <body><div class="container">
        <div class="header" style="background-color:#28a745; color:white;"><h2>Booking Confirmed!</h2></div>
        <div class="content"><h1>Your Booking is Confirmed</h1>
            <p>Hello {$customer_name},</p>
            <p>Great news! Your <strong>Order #{$order_id}</strong> has been reviewed and is now confirmed by our team.</p>
            <p>We look forward to your event! If you have any questions, feel free to contact us.</p>
            <p>Sincerely,<br>The BigFun Team</p>
        </div>
    </div></body></html>
HTML;
}

// --- TEMPLATE 2: Admin Cancelled Booking ---
function generateAdminCancelledEmail($customer_name, $order_id, $reason) {
    return <<<HTML
    <!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333}.container{max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px}.header{padding:10px 20px;text-align:center;border-radius:8px 8px 0 0}.content h1{color:#333}.info-box{background-color:#f9f9f9;padding:15px;border-radius:5px;margin-top:20px}</style></head>
    <body><div class="container">
        <div class="header" style="background-color:#dc3545; color:white;"><h2>Booking Cancelled</h2></div>
        <div class="content"><h1>Your Booking Has Been Cancelled</h1>
            <p>Hello {$customer_name},</p>
            <p>Unfortunately, we have had to cancel your <strong>Order #{$order_id}</strong>. An administrator has provided the following reason:</p>
            <div class="info-box" style="border-left: 4px solid #dc3545;">
                <strong>Reason:</strong><br>
                <em>{$reason}</em>
            </div>
            <p>Our team will contact you shortly regarding any payments made. We apologize for the inconvenience.</p>
            <p>Sincerely,<br>The BigFun Team</p>
        </div>
    </div></body></html>
HTML;
}

// --- TEMPLATE 3: Refund Processed ---
function generateRefundProcessedEmail($customer_name, $order_id, $refund_amount) {
    return <<<HTML
    <!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333}.container{max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px}.header{padding:10px 20px;text-align:center;border-radius:8px 8px 0 0}.content h1{color:#333}.info-box{background-color:#f9f9f9;padding:15px;border-radius:5px;margin-top:20px}</style></head>
    <body><div class="container">
        <div class="header" style="background-color:#17a2b8; color:white;"><h2>Refund Processed</h2></div>
        <div class="content"><h1>Your Refund is On Its Way</h1>
            <p>Hello {$customer_name},</p>
            <p>We have processed a refund for your cancelled <strong>Order #{$order_id}</strong>.</p>
            <div class="info-box">
                <strong>Order ID:</strong> #{$order_id}<br>
                <strong>Refund Amount:</strong> \${$refund_amount}
            </div>
            <p>Please allow 3-5 business days for the amount to reflect in your account, depending on your bank's processing times.</p>
            <p>Sincerely,<br>The BigFun Team</p>
        </div>
    </div></body></html>
HTML;
}

// --- TEMPLATE 4: Cancellation Denied ---
function generateCancellationDeniedEmail($customer_name, $order_id, $reason) {
    return <<<HTML
    <!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;color:#333}.container{max-width:600px;margin:20px auto;padding:20px;border:1px solid #ddd;border-radius:8px}.header{padding:10px 20px;text-align:center;border-radius:8px 8px 0 0}.content h1{color:#333}.info-box{background-color:#f9f9f9;padding:15px;border-radius:5px;margin-top:20px}</style></head>
    <body><div class="container">
        <div class="header" style="background-color:#ffc107; color:#333;"><h2>Cancellation Request Denied</h2></div>
        <div class="content"><h1>Your Cancellation Request has been Denied</h1>
            <p>Hello {$customer_name},</p>
            <p>This email is to inform you that your request to cancel <strong>Order #{$order_id}</strong> was not approved. Your booking has been restored to "Confirmed".</p>
            <div class="info-box" style="border-left: 4px solid #ffc107;">
                <strong>Reason:</strong><br>
                <em>{$reason}</em>
            </div>
            <p>Your booking is now active again. Please check your "Invoices & Payments" page for any outstanding balances.</p>
            <p>Sincerely,<br>The BigFun Team</p>
        </div>
    </div></body></html>
HTML;
}

// --- Reusable Email Sending Function ---
function send_admin_notification_email($to_email, $to_name, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); 
        $mail->Host = 'smtp.gmail.com'; 
        $mail->SMTPAuth = true; 
        $mail->Username = 'alex1925tan@gmail.com'; // Your email
        $mail->Password = 'REDACTED_SMTP_PASSWORD'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port = 587;
        
        $mail->setFrom('no-reply@bigfun.com', 'BigFun');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
    } catch (Exception $e) {
        // Log error but don't fail the transaction
        error_log("Mailer Error for {$to_email}: {$mail->ErrorInfo}");
        // We can add this to the non-fatal error list
        global $error_list;
        $error_list[] = "Mailer Error: {$mail->ErrorInfo}";
    }
}

// ##################################################################
// ###                  MAIN BACKEND LOGIC                      ###
// ##################################################################

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';
$order_id = intval($_POST['order_id'] ?? 0);

if ($order_id <= 0) {
    $response['message'] = 'Invalid Order ID.';
    echo json_encode($response);
    exit;
}

// --- Start transaction and try/catch block to cover ALL queries ---
$conn->begin_transaction();
try {
    // --- Get the User ID and Email for the order (needed for all notifications) ---
    $stmt_user = $conn->prepare("
        SELECT o.user_id, o.total_amount, o.remaining_balance, s.first_name, s.last_name, s.email 
        FROM orders o
        JOIN signup s ON o.user_id = s.id
        WHERE o.id = ?");
    $stmt_user->bind_param("i", $order_id);
    $stmt_user->execute();
    $order_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    $user_id = $order_data['user_id'] ?? null;
    $customer_name = trim(($order_data['first_name'] ?? '') . ' ' . ($order_data['last_name'] ?? 'Customer'));
    $customer_email = $order_data['email'] ?? null;
    

    if (!$user_id || !$customer_email) {
        throw new Exception('Could not find user data for this order.');
    }

    // --- Process the requested action ---
    switch ($action) {

        // --- CASE 1: Admin Confirms a New Booking ---
        case 'confirm_booking':
            $stmt = $conn->prepare("UPDATE orders SET booking_status = 'Confirmed' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            
            // 1. Create On-Site Notification
            create_notification(
                $conn, $user_id,
                "Booking Confirmed!",
                "Your Order #" . $order_id . " has been confirmed by our team. We look forward to your event!",
                "booking-status.php"
            );
            
            // 2. Send Email
            send_admin_notification_email(
                $customer_email, $customer_name,
                "Your Booking is Confirmed! (Order #{$order_id})",
                generateBookingConfirmedEmail($customer_name, $order_id)
            );
            
            $response = ['status' => 'success', 'message' => 'Booking confirmed. User has been notified.'];
            break;

        // --- CASE 2: Admin Cancels a New Booking (Your Request) ---
        case 'admin_cancel_booking':
            $reason = trim($_POST['cancellation_reason'] ?? 'Schedule conflict');
            if (empty($reason)) throw new Exception('A reason is required for cancellation.');
            
            // --- NEW: Better Business Logic ---
            // Check if user paid, and set status to 'Refund Required' if they did.
            $total_paid = $order_data['total_amount'] - $order_data['remaining_balance'];
            $new_payment_status = ($total_paid > 0) ? 'Refund Required' : 'Cancelled';

            $stmt = $conn->prepare("UPDATE orders SET 
                                        booking_status = 'Cancelled', 
                                        payment_status = ?,
                                        cancellation_reason = ? 
                                    WHERE id = ?");
            $stmt->bind_param("ssi", $new_payment_status, $reason, $order_id);
            $stmt->execute();
            
            // 1. Create On-Site Notification
            create_notification(
                $conn, $user_id,
                "Booking Cancelled",
                "Unfortunately, your Order #" . $order_id . " has been cancelled by our team. Reason: " . $reason,
                "booking-status.php"
            );
            
            // 2. Send Email
            send_admin_notification_email(
                $customer_email, $customer_name,
                "Your Booking Has Been Cancelled (Order #{$order_id})",
                generateAdminCancelledEmail($customer_name, $order_id, $reason)
            );

            // Give admin feedback if a refund is needed
            $admin_message = 'Booking cancelled. User has been notified.';
            if ($new_payment_status == 'Refund Required') {
                $admin_message .= ' **This order was paid. Please process a refund.**';
            }
            $response = ['status' => 'success', 'message' => $admin_message];
            break;

        // --- CASE 3: Admin Approves a Customer's Refund Request ---
        case 'approve_refund':
            $refund_amount = floatval($_POST['refund_amount'] ?? 0);
            $total_paid = $order_data['total_amount'] - $order_data['remaining_balance'];

            if ($refund_amount < 0) throw new Exception('Refund amount cannot be negative.');
            if ($refund_amount > $total_paid) throw new Exception('Refund amount cannot be more than the total paid ($' . number_format($total_paid, 2) . ').');
            
            $stmt = $conn->prepare("UPDATE orders SET 
                                        booking_status = 'Refunded', 
                                        payment_status = 'Refunded', 
                                        refund_amount = ?, 
                                        refund_date = NOW()
                                    WHERE id = ?");
            $stmt->bind_param("di", $refund_amount, $order_id);
            $stmt->execute();

            // 1. Create On-Site Notification
            create_notification(
                $conn, $user_id,
                "Refund Processed",
                "Your refund of $" . number_format($refund_amount, 2) . " for Order #" . $order_id . " has been processed.",
                "invoices.php"
            );
            
            // 2. Send Email
            send_admin_notification_email(
                $customer_email, $customer_name,
                "Your Refund Has Been Processed (Order #{$order_id})",
                generateRefundProcessedEmail($customer_name, $order_id, number_format($refund_amount, 2))
            );

            $response = ['status' => 'success', 'message' => 'Refund approved. User has been notified.'];
            break;

        // --- CASE 4: Admin Denies a Customer's Cancellation Request ---
        case 'deny_cancellation':
            $reason = trim($_POST['denial_reason'] ?? 'No reason provided.');
            if (empty($reason)) throw new Exception('A reason for denial is required.');
            
            // Restore the booking to its previous 'Confirmed' state
            $stmt = $conn->prepare("UPDATE orders SET 
                                        booking_status = 'Confirmed', 
                                        payment_status = (CASE WHEN remaining_balance = 0 THEN 'Paid' WHEN remaining_balance < total_amount THEN 'Partially Paid' ELSE 'Pending' END),
                                        cancellation_reason = NULL, 
                                        cancellation_timestamp = NULL
                                    WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            // 1. Create On-Site Notification
            create_notification(
                $conn, $user_id,
                "Cancellation Request Denied",
                "Your request for Order #" . $order_id . " was denied. Reason: " . $reason,
                "booking-status.php"
            );
            
            // 2. Send Email
            send_admin_notification_email(
                $customer_email, $customer_name,
                "Your Cancellation Request Was Denied (Order #{$order_id})",
                generateCancellationDeniedEmail($customer_name, $order_id, $reason)
            );

            $response = ['status' => 'success', 'message' => 'Cancellation denied. User has been notified.'];
            break;

        default:
            throw new Exception("Invalid action specified.");
    }
    
    // If all went well, commit the transaction
    $conn->commit();

} catch (Exception $e) {
    // If anything failed, roll back all database changes
    $conn->rollback();
    http_response_code(400); // Bad Request (logic or data error)
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// --- NEW: Final JSON Output ---
// This will now include any non-fatal errors if they occurred (like a mail error)
if (!empty($error_list)) {
    $response['all_errors'] = $error_list;
    if ($response['status'] === 'success') {
        $response['message'] .= " (with " . count($error_list) . " non-fatal error(s))";
    }
}

$conn->close();
echo json_encode($response);
?>