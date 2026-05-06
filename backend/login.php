<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- MODIFIED: Include DB, PHPMailer, and new helper file ---
include "db.php"; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // --- Make sure this path is correct ---
require_once 'attemptlogin_notify.php'; // --- NOW THIS FILE EXISTS ---


// --- MODIFIED: Helper function to get attacker IP/Location/UserAgent ---
function getAttackerInfo() {
    $ip = $_SERVER['REMOTE_ADDR'];
    // --- MODIFIED: Added user_agent to the base info ---
    $info = [
        'ip' => $ip, 
        'location' => 'N/A', 
        'isp' => 'N/A', 
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
    ];

    if ($ip == '127.0.0.1' || $ip == '::1') {
        $info['location'] = 'Localhost';
        $info['isp'] = 'Local Network';
        return $info; // User agent is already set
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
    } catch (Exception $e) {
        // API call failed, but we still have IP and User Agent
    }
    return $info;
}

// --- Helper function to send the lockout email ---
// --- THIS FUNCTION REMAINS UNCHANGED ---
function sendAccountLockoutEmail($email, $attacker_info) {
    $mail = new PHPMailer(true);
    try {
        // --- IMPORTANT: For debugging, uncomment the next line ---
        // $mail->SMTPDebug = 2; 

        // Your SMTP settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alex1925tan@gmail.com'; // Your email
        $mail->Password   = 'REDACTED_SMTP_PASSWORD'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('alex1925tan@gmail.com', 'BigFun Security');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Security Alert: Your BigFun Account is Locked';
        // --- THIS CALL WILL NOW WORK ---
        $mail->Body    = generateLockoutEmailBody($attacker_info); 
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // --- IMPORTANT: Log the error to help debug SMTP issues ---
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // --- THIS PART REMAINS UNCHANGED, IT'S ALREADY CORRECT ---
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $max_attempts = 3;
    $response = [];

    $stmt = $conn->prepare("SELECT id, email, password, is_verified, login_attempts, is_locked FROM signup WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        if ($user['is_locked'] == 1) {
            $response = ["status" => "error", "message" => "Your account is locked. Please use the 'Forgot Password' link to unlock it."];
        
        } else if (password_verify($password, $user['password'])) {
            
            if ($user['login_attempts'] > 0) {
                $update_stmt = $conn->prepare("UPDATE signup SET login_attempts = 0, is_locked = 0 WHERE id = ?");
                $update_stmt->bind_param("i", $user_id);
                $update_stmt->execute();
                $update_stmt->close();
            }

            if ($user['is_verified'] == 1) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['logged_in'] = true;

                $ip = $_SERVER['REMOTE_ADDR'];
                $login_time = date("Y-m-d H:i:s");
                $insert = $conn->prepare("INSERT INTO login_history (user_id, login_time, ip_address) VALUES (?, ?, ?)");
                $insert->bind_param("iss", $user['id'], $login_time, $ip);
                $insert->execute();
                $_SESSION['login_history_id'] = $insert->insert_id;
                $insert->close();

                $response = ["status" => "success", "redirect" => "../profile-manage.php"];
            } else {
                $response = ["status" => "error", "message" => "Please verify your email before logging in."];
            }
        
        } else {
            $new_attempts = $user['login_attempts'] + 1;

            if ($new_attempts >= $max_attempts) {
                $lock_stmt = $conn->prepare("UPDATE signup SET login_attempts = ?, is_locked = 1 WHERE id = ?");
                $lock_stmt->bind_param("ii", $new_attempts, $user_id);
                $lock_stmt->execute();
                $lock_stmt->close();

                // This will now get IP, Location, ISP, AND User Agent
                $attacker_info = getAttackerInfo();
                
                // This will now call the function in the new file
                sendAccountLockoutEmail($email, $attacker_info);

                $response = ["status" => "error", "message" => "Your account has been locked after 3 failed attempts. Please check your email and use the 'Forgot Password' link to unlock it."];
            } else {
                $attempt_stmt = $conn->prepare("UPDATE signup SET login_attempts = ? WHERE id = ?");
                $attempt_stmt->bind_param("ii", $new_attempts, $user_id);
                $attempt_stmt->execute();
                $attempt_stmt->close();
                
                $remaining = $max_attempts - $new_attempts;
                $response = ["status" => "error", "message" => "Invalid email or password. You have $remaining attempt(s) remaining."];
            }
        }
    } else {
        $response = ["status" => "error", "message" => "Invalid email or password."];
    }

    $stmt->close();

    header("Content-Type: application/json");
    echo json_encode($response);
    exit();
}
?>

