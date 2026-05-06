<?php
session_start();
// --- Enable error reporting for debugging ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

include "db.php"; // Make sure this connects successfully

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // For debugging constants

// --- Verify vendor autoload path ---
$vendorAutoload = '../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    // Log error: error_log("Error: vendor/autoload.php not found at " . $vendorAutoload);
    echo json_encode(["status" => "error", "message" => "Server configuration error [Code 1]. Please contact support."]);
    exit();
}
require $vendorAutoload;

// --- Removed require_once for email helpers as the function will be defined here ---
// require_once 'attemptlogin-admin_notify.php'; // No longer needed for this specific email body

// --- Helper function to get requester IP/Location ---
function getRequesterInfo() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $info = ['ip' => $ip, 'location' => 'N/A', 'isp' => 'N/A'];
    if ($ip == '127.0.0.1' || $ip == '::1') {
        $info['location'] = 'Localhost';
        $info['isp'] = 'Local Network';
        return $info;
    }
    try {
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,isp,query";
        $data = @file_get_contents($url);
        if ($data) {
            $json = json_decode($data, true);
            if ($json && $json['status'] == 'success') {
                $info['location'] = implode(', ', array_filter([$json['city'], $json['regionName'], $json['country']]));
                $info['isp'] = $json['isp'];
            }
        }
    } catch (Exception $e) { /* Log error if needed */ }
    return $info;
}


header('Content-Type: application/json');
$response = [];

// --- Get requester info immediately ---
$requester_info = getRequesterInfo(); // Call the function defined above
$log_timestamp = date('Y-m-d H:i:s'); // Get current time for logging

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    // --- Log the request attempt ---
    $log_message = "{$log_timestamp} - ADMIN Password reset requested for email '{$email}' by IP: {$requester_info['ip']} (Location: {$requester_info['location']}, ISP: {$requester_info['isp']})\n";
    $log_file = __DIR__ . '/admin_password_reset_log.txt'; // Separate log file for admin resets
    file_put_contents($log_file, $log_message, FILE_APPEND);


    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ["status" => "error", "message" => "Please enter a valid email."];
        echo json_encode($response);
        exit();
    }

    try { // Wrap the whole DB and mail process in a try block
        $stmt = $conn->prepare("SELECT id FROM admin_signup WHERE email = ? LIMIT 1");
        if (!$stmt) { throw new Exception("Database prepare error: " . $conn->error); }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);

            // NOTE: Add a `token_expiry` DATETIME column for better security.

            $update_stmt = $conn->prepare("UPDATE admin_signup SET token = ? WHERE id = ?");
            if (!$update_stmt) { throw new Exception("Database prepare error (update): " . $conn->error); }

            $update_stmt->bind_param("si", $token_hash, $user_id);

            if ($update_stmt->execute()) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $reset_link = "{$protocol}://{$host}/reset-password-admin.php?token=" . $token;

                $mail = new PHPMailer(true); // Enable exceptions

                // --- Email Sending ---
                // Server settings
                // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // UNCOMMENT ONLY FOR DEBUGGING
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'alex1925tan@gmail.com'; // Your Gmail address
                $mail->Password   = 'REDACTED_SMTP_PASSWORD';   // Your Google App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('alex1925tan@gmail.com', 'BigFun Security');
                $mail->addAddress($email); // Send to the admin's email

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Admin Password Reset Request';

                // --- NEW: Define Email Body Directly Here ---
                $style = "
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 20px; line-height: 1.5; }
                    .wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); overflow: hidden; }
                    .header { background-color: #FFEFFC; text-align: center; padding: 25px 20px; }
                    .header img { max-height: 50px; }
                    .content { padding: 30px; }
                    .content h1 { color: #802080; font-size: 26px; font-weight: 600; margin: 0 0 10px; }
                    .content p { color: #333; margin: 0 0 20px; }
                    .footer { text-align: center; margin-top: 30px; padding: 0 20px 20px; font-size: 0.85em; color: #888888; }
                    .cta-button { display: inline-block; padding: 12px 25px; margin-top: 10px; background-color: #802080; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
                ";

                $mail->Body = <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Admin Password Reset</title>
                    <style>{$style}</style>
                </head>
                <body>
                <div class='wrapper'>
                    <div class='header'>
                        <img src='https://www.yourwebsite.com/images/bgfunlogo.png' alt='BigFun Logo'>
                    </div>
                    <div class='content'>
                        <h1>Admin Password Reset Request</h1>
                        <p>You are receiving this email because a password reset request was initiated for your admin account.</p>
                        <p>If you did not request this, you can safely ignore this email.</p>
                        <p>To reset your password, click the button below:</p>
                        <a href='{$reset_link}' class='cta-button'>Reset Your Password</a>
                        <p style='font-size: 0.9em; color: #777; margin-top: 25px;'>This link will expire in 1 hour.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2025 BigFun. All rights reserved.</p>
                    </div>
                </div>
                </body>
                </html>
                HTML;
                // --- End of Inline Email Body ---

                $mail->send();
                // If no exception, email sent successfully
                $response = ["status" => "success", "message" => "If an account with that email exists, a reset link has been sent."];

            } else {
                // error_log("Database update error for admin token: " . $update_stmt->error);
                $response = ["status" => "error", "message" => "Database error [Code U]. Could not initiate reset."];
            }
            if(isset($update_stmt)) $update_stmt->close();

        } else {
            // Email not found - Send generic success for security
            $response = ["status" => "success", "message" => "If an account with that email exists, a reset link has been sent."];
        }
        if(isset($stmt)) $stmt->close();

    } catch (Exception $e) {
        // Log detailed error
        // error_log("Admin Password Reset Error for {$email}: " . $e->getMessage());

        // Generic error for user
        if (isset($mail) && $e instanceof \PHPMailer\PHPMailer\Exception) {
             $response = ["status" => "error", "message" => "Failed to send reset email [Code M]. Ensure email is correct and try again."];
        } else {
             $response = ["status" => "error", "message" => "An unexpected error occurred [Code G]. Please try again later."];
        }
    }

} else {
    $response = ["status" => "error", "message" => "Invalid request method."];
}

if (isset($conn)) $conn->close();

echo json_encode($response);
exit();
?>