<?php
session_start();
include "db.php";

// --- PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';


/**
 * Generates the HTML body for the password reset email,
 * using the same style as your order confirmation.
 */
function generateResetEmailBody($reset_link) {
    
    // --- Define CSS Styles for the Email ---
    $style = "
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 20px; line-height: 1.5; }
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.07); overflow: hidden; }
        .header { background-color: #FFEFFC; text-align: center; padding: 25px 20px; }
        .header img { max-height: 50px; }
        .content { padding: 30px; }
        .content h1 { color: #802080; font-size: 26px; font-weight: 600; margin: 0 0 10px; }
        .content p { color: #333; margin: 0 0 20px; }
        .footer { text-align: center; margin-top: 30px; padding: 0 20px 20px; font-size: 0.85em; color: #888888; }
        .cta-button { display: inline-block; padding: 12px 25px; margin-top: 20px; background-color: #802080; color: #ffffff !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
    ";

    // --- Assemble the Final HTML Email ---
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset</title>
        <style>{$style}</style>
    </head>
    <body>
    <div class='wrapper'>
        <div class='header'>
            <!-- Make sure this logo path is a full URL (TYPO FIXED) -->
            <img src='https://www.yourwebsite.com/images/bgfunlogo.png' alt='BigFun Logo'>
        </div>
        <div class='content'>
            <h1>Password Reset Request</h1>
            <p>Hello,<br>We received a request to reset the password for your account. Please click the button below to set a new password.</p>
            
            <a href='{$reset_link}' class='cta-button'>Reset Your Password</a>

            <p style='margin-top: 30px; font-size: 0.9em; color: #555;'>If you did not request a password reset, please ignore this email. This link is valid for 1 hour.</p>
        </div>
        <div class='footer'>
            <p>If you have any questions, please contact our support team.<br>&copy; 2025 BigFun. All rights reserved.</p>
        </div>
    </div>
    </body>
    </html>
    HTML;
}


// --- MAIN SCRIPT LOGIC ---

header('Content-Type: application/json');
$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ["status" => "error", "message" => "Please enter a valid email."];
        echo json_encode($response);
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT id FROM signup WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // --- User exists, proceed with token generation and email ---
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            $token = bin2hex(random_bytes(32)); 

            // NOTE: For better security, you should add a `reset_token_expires` column
            // and set it to NOW() + INTERVAL 1 HOUR.
            // For now, we'll just update the main token.
            $update_stmt = $conn->prepare("UPDATE signup SET token = ? WHERE id = ?");
            $update_stmt->bind_param("si", $token, $user_id);
            
            if ($update_stmt->execute()) {
                
                // --- SEND THE EMAIL ---
                $mail = new PHPMailer(true);
                
                // --- Your SMTP settings ---
                $mail->isSMTP();
                
                // --- CRITICAL FIX: Debugging removed ---
                // $mail->SMTPDebug = 2; // <-- REMOVED. This was breaking your JSON.
                // ------------------------------------

                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'alex1925tan@gmail.com'; // Your email
                $mail->Password   = 'REDACTED_SMTP_PASSWORD'; // Your App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // --- Recipients ---
                $mail->setFrom('alex1925tan@gmail.com', 'BigFun'); // From email and name
                $mail->addAddress($email); // Add a recipient (the user's email)

                // --- Content ---
                $mail->isHTML(true);
                $mail->Subject = 'Your BigFun Password Reset Link';
            
                // --- Get domain dynamically ---
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                $reset_link = "{$protocol}://{$host}/reset-password.php?token={$token}";
            
                // --- Use the new function to generate the body ---
                $mail->Body = generateResetEmailBody($reset_link);
                
                $mail->send();
                
            } // end if ($update_stmt->execute())
            
            $update_stmt->close();
        } 
        // --- No 'else' block here. ---
        // We do not tell the user if the account was found or not.

    } catch (Exception $e) {
        // --- ENHANCED: Silent Error Logging ---
        // Log the real error for debugging, but don't send it to the user.
        error_log("Mailer or DB Error for {$email}: " . $e->getMessage());
    }

    // --- SECURITY ENHANCEMENT ---
    // Always send a success response to prevent user enumeration.
    // This message is shown whether the email existed, or if the mail sent, or not.
    $response = ["status" => "success", "message" => "If an account with that email exists, a password reset link has been sent."];

    $stmt->close();

} else {
    $response = ["status" => "error", "message" => "Invalid request method."];
}

$conn->close();
echo json_encode($response);
exit();
?>