<?php
// Show errors for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; // adjust path to autoload.php

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $from = $_POST['from_email'] ?? '';
    $to = $_POST['to_email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';

    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alex1925tan@gmail.com'; // replace with real email
        $mail->Password   = 'REDACTED_SMTP_PASSWORD';   // use app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Main email → Admin
        $mail->setFrom($from, 'Website User');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body);

        $mail->send();

        // Send confirmation → User
        $mail->clearAddresses();
        $mail->addAddress($from);
        $mail->Subject = "Copy of your message: " . $subject;
        $mail->Body    = "Thank you for contacting us!<br><br><strong>Your message:</strong><br>" . nl2br($body);
        $mail->send();

        // Redirect with success flag
        header("Location: ../index.php?sent=1");
        exit();
    } catch (Exception $e) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    }
}
