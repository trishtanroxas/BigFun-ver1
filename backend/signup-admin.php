<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the content type to JSON for all responses
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require 'mail_config.php';
include "db.php";

// Function to send a JSON response and exit
function send_json_response($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Data Validation ---
    $email1 = isset($_POST['email1']) ? trim($_POST['email1']) : '';
    $email2 = isset($_POST['email2']) ? trim($_POST['email2']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($email1) || empty($email2) || empty($password)) {
        send_json_response('error', 'Please fill in all required fields.');
    }
    if (!filter_var($email1, FILTER_VALIDATE_EMAIL)) {
        send_json_response('error', 'Please provide a valid email address.');
    }
    if ($email1 !== $email2) {
        send_json_response('error', 'The email addresses do not match.');
    }

    // --- CROSS-TABLE VALIDATION ---
    // 1. Check if email is already a regular user
    $userCheckStmt = $conn->prepare("SELECT id FROM signup WHERE email = ?");
    $userCheckStmt->bind_param("s", $email1);
    $userCheckStmt->execute();
    $userCheckStmt->store_result();
    if ($userCheckStmt->num_rows > 0) {
        send_json_response('error', 'This email is already registered as a regular user account.');
    }
    $userCheckStmt->close();

    // 2. Check if email is already an admin
    $adminCheckStmt = $conn->prepare("SELECT id FROM admin_signup WHERE email = ?");
    $adminCheckStmt->bind_param("s", $email1);
    $adminCheckStmt->execute();
    $adminCheckStmt->store_result();
    if ($adminCheckStmt->num_rows > 0) {
        send_json_response('error', 'This email address is already registered as an admin.');
    }
    $adminCheckStmt->close();


    // --- Proceed with Admin Registration ---
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16));

    $stmt = $conn->prepare("
        INSERT INTO admin_signup (email, password, token, is_verified, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("sss", $email1, $hashedPassword, $token);

    if ($stmt->execute()) {
        $mail = new PHPMailer(true);
        try {
            configureSMTP($mail);

            $mail->setFrom(MAIL_FROM, 'BigFun Admin Team');
            $mail->addAddress($email1);

            $verifyLink = "http://localhost:3000/backend/verify-admin.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = "Verify Your Admin Account - BigFun";
            $mail->Body    = "
                 <!DOCTYPE html>
                 <html lang='en'>
                 <head>
                   <meta charset='UTF-8'>
                   <title>Verify Your Admin Email - BigFun</title>
                 </head>
                 <body style=\"font-family:Arial,sans-serif;background:#f9f9f9;color:#333;\">
                   <div style='max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0px 4px 12px rgba(0,0,0,0.1);text-align:center;padding:30px 20px;'>
                     <h2>Welcome Admin 🎉</h2>
                     <p>Please confirm your admin account by clicking the button below:</p>
                     <a href='$verifyLink' style='display:inline-block;background:#8C367C;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;'>Verify Admin Email</a>
                     <div style='margin-top:30px;font-size:14px;color:#777;'>
                       <p>© 2025 BigFun. All rights reserved.</p>
                     </div>
                   </div>
                 </body>
                 </html>
            ";

            $mail->send();
            send_json_response('success', 'A verification link has been sent to your admin email address. Please check your inbox!');

        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            send_json_response('error', 'We could not send the verification email. Please try again later.');
        }
    } else {
        error_log("Database Error: " . $stmt->error);
        send_json_response('error', 'An error occurred while creating your account. Please try again.');
    }

    $stmt->close();
    $conn->close();
} else {
    send_json_response('error', 'Invalid request method.');
}