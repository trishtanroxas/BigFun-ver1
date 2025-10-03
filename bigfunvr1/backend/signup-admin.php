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

    // hash password & create token
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16));

    // ✅ insert into admin_signup table
    $stmt = $conn->prepare("
        INSERT INTO admin_signup (email, password, token, is_verified, created_at)
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
            $mail->Username   = 'alex1925tan@gmail.com'; // replace with your email
            $mail->Password   = 'REDACTED_SMTP_PASSWORD';   // Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('alex1925tan@gmail.com', 'BigFun Admin Team');
            $mail->addAddress($email1);

            $verifyLink = "http://localhost:3000/backend/verify-admin.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = "Verify Your Admin Account - BigFun";
            $mail->Body    = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                  <meta charset='UTF-8'>
                  <title>Verify Your Email - BigFun</title>
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
