<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email1 = trim($_POST['email1']);
    $email2 = trim($_POST['email2']);
    $password = trim($_POST['password']);

    if ($email1 !== $email2) {
        die("Emails do not match.");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16));

    // Save into signup table
    $stmt = $conn->prepare("
        INSERT INTO signup (email, password, token, is_verified, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->bind_param("sss", $email1, $hashedPassword, $token);

    if ($stmt->execute()) {
        // Send verification email
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'alex1925tan@gmail.com';
            $mail->Password   = 'REDACTED_SMTP_PASSWORD'; // Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('alex1925tan@gmail.com', 'BigFun Team');
            $mail->addAddress($email1);

            $verifyLink = "http://localhost:3000/backend/verify.php?token=" . $token;

            // Email template
            $mail->isHTML(true);
            $mail->Subject = "Verify Your Email - BigFun";
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

            header("Location: redirect-message.php?status=check_inbox");
            exit();
        } catch (Exception $e) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        }
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
