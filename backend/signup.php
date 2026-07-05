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
    // DO NOT trim the password, as spaces can be valid characters
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email1) || empty($email2) || empty($password)) {
        send_json_response('error', 'Please fill in all required fields.');
    }

    if (!filter_var($email1, FILTER_VALIDATE_EMAIL)) {
        send_json_response('error', 'Please provide a valid email address.');
    }

    if ($email1 !== $email2) {
        send_json_response('error', 'The email addresses do not match.');
    }

    // --- NEW: Server-Side Password Validation ---
    $specialCharRegex = '/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/';
    $upperCaseRegex = '/[A-Z]/';
    $numberRegex = '/[0-9]/';

    if (strlen($password) < 12) {
        send_json_response('error', 'Password must be at least 12 characters.');
    }
    if (!preg_match($upperCaseRegex, $password)) {
        send_json_response('error', 'Password must contain at least one uppercase letter (A-Z).');
    }
    if (!preg_match($numberRegex, $password)) {
        send_json_response('error', 'Password must contain at least one number (0-9).');
    }
    if (!preg_match($specialCharRegex, $password)) {
        send_json_response('error', 'Password must contain at least one special character (e.g., !@#$).');
    }
    // --- End of New Validation ---


    // --- Database Interaction ---
    // Use BCRYPT with a defined cost for strong, consistent hashing
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $token = bin2hex(random_bytes(32)); // 64-char token, fits varchar(64)

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM signup WHERE email = ?");
    $checkStmt->bind_param("s", $email1);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        send_json_response('error', 'This email address is already registered.');
    }
    $checkStmt->close();


    // Save new user into signup table
    $stmt = $conn->prepare("
        INSERT INTO signup (email, password, token, is_verified, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("sss", $email1, $hashedPassword, $token);

    if ($stmt->execute()) {
        // --- Send Verification Email ---
        $mail = new PHPMailer(true);

        try {
            configureSMTP($mail);

            $mail->setFrom(MAIL_FROM, 'BigFun Team');
            $mail->addAddress($email1);

            // --- MODIFIED: Dynamic Verification Link ---
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            // This assumes your verify.php file is in the 'backend' folder
            $verifyLink = "{$protocol}://{$host}/backend/verify.php?token=" . $token; 
            // If verify.php is in root, use:
            // $verifyLink = "{$protocol}://{$host}/verify.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = "Verify Your Email - BigFun";
            // Using your existing beautiful HTML email
            $mail->Body    = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                  <meta charset='UTF-8'>
                  <title>Verify Your Email - BigFun</title>
                  <link href='https://fonts.googleapis.com/css2?family=Inria+Sans:wght@400;700&family=Inter:wght@400;600&display=swap' rel='stylesheet'>
                </head>
                <body style=\"margin:0;padding:0;font-family:'Inter',sans-serif;background:#f9f9f9;color:#333;\">
                  <div style='max-width:600px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0px 4px 12px rgba(0,0,0,0.1);text-align:center;padding:30px 20px;'>
                    <h2 style=\"font-family:'Inria Sans',sans-serif;font-size:24px;margin-bottom:15px;color:#222;\">Welcome to BigFun 🎉</h2>
                    <p style='font-size:16px;color:#555;margin-bottom:20px;'>Please confirm your email address by clicking the button below:</p>
                    <a href='$verifyLink' style='display:inline-block;background:#8C367C;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;font-family:Inter,sans-serif;'>Verify Email</a>
                    <div style='margin-top:30px;font-size:14px;color:#777;'>
                      <p>© 2025 BigFun. All rights reserved.</p>
                      <p>📞 1800 244 386 | ✉ hire.enquiries@bigfunqld.com.au</p>
                    </div>
                  </div>
                </body>
                </html>
            ";

            $mail->send();
            send_json_response('success', 'A verification link has been sent to your email address. Please check your inbox!');

        } catch (Exception $e) {
            // Log the detailed error for debugging, but send a generic message to the user
            error_log("Mailer Error: " . $mail->ErrorInfo);
            // Even if mail fails, the account was created. Let them know.
            send_json_response('success', 'Account created! However, we could not send the verification email. Please contact support.');
        }
    } else {
        // Log the detailed error for debugging
        error_log("Database Error: " . $stmt->error);
        send_json_response('error', 'An error occurred while creating your account. Please try again.');
    }

    $stmt->close();
    $conn->close();
} else {
    // Handle cases where the script is accessed directly without POST
    send_json_response('error', 'Invalid request method.');
}

?>