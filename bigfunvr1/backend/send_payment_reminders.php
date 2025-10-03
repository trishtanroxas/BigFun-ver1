<?php
// This script is meant to be run automatically by a cron job once per day.
// It will find payments due in 3 days and send a reminder email.

include "db.php";
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Find orders where the next due date is exactly 3 days from now
$reminder_date = date('Y-m-d', strtotime('+3 days'));

$stmt = $conn->prepare("
    SELECT o.*, s.email as user_email, s.first_name 
    FROM orders o
    JOIN signup s ON o.user_id = s.id
    WHERE o.next_due_date = ? AND o.payment_status != 'Paid'
");
$stmt->bind_param("s", $reminder_date);
$stmt->execute();
$orders_to_remind = $stmt->get_result();

if ($orders_to_remind->num_rows > 0) {
    while ($order = $orders_to_remind->fetch_assoc()) {
        $mail = new PHPMailer(true);
        try {
            // Your SMTP settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'alex1925tan@gmail.com';
            $mail->Password   = 'REDACTED_SMTP_PASSWORD';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('alex1925tan@gmail.com', 'BigFun');
            $mail->addAddress($order['user_email'], $order['first_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Upcoming Payment Reminder for Your BigFun Order';
            $mail->Body    = "
                <h1>Payment Reminder</h1>
                <p>Hello {$order['first_name']},</p>
                <p>This is a friendly reminder that your next payment for Order #{$order['id']} is due on <strong>" . date("F j, Y", strtotime($order['next_due_date'])) . "</strong>.</p>
                <p>Your remaining balance is: <strong>$" . number_format($order['remaining_balance'], 2) . "</strong></p>
                <p>Please visit our website to make your payment.</p>
                <a href='https://www.yourwebsite.com/invoices.php'>Go to Invoices</a>
            ";
            $mail->send();
            echo "Reminder sent to " . $order['user_email'] . "\n";
        } catch (Exception $e) {
            echo "Message could not be sent to " . $order['user_email'] . ". Mailer Error: {$mail->ErrorInfo}\n";
        }
    }
} else {
    echo "No payments due for reminders today.\n";
}
$stmt->close();
$conn->close();
?>