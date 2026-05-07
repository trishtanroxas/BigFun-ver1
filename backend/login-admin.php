<?php
session_start();
// --- ENABLE ERROR DISPLAY FOR DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------------------

// --- Database Connection ---
$dbFile = __DIR__ . '/db.php';
if (!file_exists($dbFile)) {
    error_log("FATAL: db.php not found in login-admin.php");
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(["status" => "error", "message" => "Server config error [DB]."]);
    exit();
}
include $dbFile;
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     $error_msg = isset($conn->connect_error) ? $conn->connect_error : 'DB object invalid.';
     error_log("FATAL: DB Connection failed in login-admin.php - " . $error_msg);
     if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
     echo json_encode(["status" => "error", "message" => "DB connection failed [CON]."]);
     exit();
}

// --- PHPMailer & Autoload ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    error_log("FATAL: vendor/autoload.php not found at " . $vendorAutoload . " in login-admin.php");
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(["status" => "error", "message" => "Server config error [V]."]);
    exit();
}
require $vendorAutoload;

// --- Email Helper File ---
$emailHelpersFile = __DIR__ . '/attemptlogin-admin_notify.php'; // Your renamed helper file
if (!file_exists($emailHelpersFile)) {
    error_log("FATAL: Email helper file not found at " . $emailHelpersFile . " in login-admin.php");
    if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
    echo json_encode(["status" => "error", "message" => "Server config error [H]."]);
    exit();
}
require_once $emailHelpersFile;
// --- CRITICAL CHECK: Does the required function exist? ---
if (!function_exists('generateLockoutEmailBody')) {
     error_log("FATAL: Function 'generateLockoutEmailBody' not found in " . $emailHelpersFile);
     if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
     echo json_encode(["status" => "error", "message" => "Server config error [F]. Function missing."]);
     exit();
}
// --- Also check for the helper function used by generateLockoutEmailBody ---
if (!function_exists('format_detail_row')) {
     error_log("FATAL: Function 'format_detail_row' not found in " . $emailHelpersFile);
      if (!headers_sent()) { header('Content-Type: application/json'); http_response_code(500); }
     echo json_encode(["status" => "error", "message" => "Server config error [F2]. Function missing."]);
     exit();
}


// --- Set Header EARLY ---
if (!headers_sent()) {
    header('Content-Type: application/json');
}
$response = [];


// --- Helper function for login history logging ---
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) { return $_SERVER['HTTP_CLIENT_IP']; }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { return $_SERVER['HTTP_X_FORWARDED_FOR']; }
    return $_SERVER['REMOTE_ADDR'];
}

// --- Helper function for security email ---
function getAttackerInfo() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $info = ['ip' => $ip, 'location' => 'N/A', 'isp' => 'N/A'];
    if ($ip == '127.0.0.1' || $ip == '::1') {
        $info['location'] = 'Localhost'; $info['isp'] = 'Local Network'; return $info;
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
    } catch (\Throwable $e) { error_log("IP API Error in login-admin: " . $e->getMessage()); }
    return $info;
}

// --- Helper function to send lockout email ---
function sendAccountLockoutEmail($email, $attacker_info) {
    global $response; // Allow modifying the main response on mail error
    $mail = new PHPMailer(true);
    try {
        // Your SMTP settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Uncomment ONLY for detailed email debugging
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alex1925tan@gmail.com'; // Your Gmail
        $mail->Password   = 'REDACTED_SMTP_PASSWORD';   // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('alex1925tan@gmail.com', 'BigFun Security');
        $mail->addAddress($email); // Send to the admin being locked
        $mail->isHTML(true);
        $mail->Subject = 'Security Alert: Your ADMIN Account is Locked';

        // --- Call the function from the included file ---
        $mail->Body = generateLockoutEmailBody($attacker_info);

        $mail->send();
        return true; // Indicate email sending was attempted/successful
    } catch (Exception $e) {
         error_log("Admin Lockout Mailer Error for {$email}: " . $mail->ErrorInfo);
         // Modify the main $response to indicate mail failure *in addition* to lockout
         $response['mail_error'] = "Account locked, but notification email failed. Error: " . $mail->ErrorInfo;
         return false; // Indicate email sending failed
    }
}


// --- Main Login Logic ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $max_attempts = 3;
    // $response = []; // Already initialized

    $stmt = null;
    $update_stmt = null;
    $lock_stmt = null;
    $attempt_stmt = null;
    $insert = null;

    try {
        // --- Select user data ---
        $stmt = $conn->prepare("SELECT id, email, password, is_verified, login_attempts, is_locked FROM admin_signup WHERE email = ? LIMIT 1");
        if (!$stmt) { throw new Exception("DB Prepare Error (Select): " . $conn->error); }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            $admin_id = $admin['id'];

            // --- CHECK 1: Is account ALREADY locked? ---
            if ($admin['is_locked'] == 1) {
                $response = ["status" => "error", "message" => "Your account is locked. Use 'Forgot Password'."];

            // --- CHECK 2: Is password correct? ---
            } else if (password_verify($password, $admin['password'])) {

                // --- CHECK 3: Is account verified? ---
                if ($admin['is_verified'] == 1) {

                    // --- Reset attempts on success ---
                    if ($admin['login_attempts'] > 0) {
                        $update_stmt = $conn->prepare("UPDATE admin_signup SET login_attempts = 0, is_locked = 0 WHERE id = ?");
                        if (!$update_stmt) { throw new Exception("DB Prepare Error (Reset Attempts): ".$conn->error); }
                        $update_stmt->bind_param("i", $admin_id);
                        if (!$update_stmt->execute()) { throw new Exception("DB Execute Error (Reset Attempts): ".$update_stmt->error); }
                        $update_stmt->close();
                        $update_stmt = null;
                    }

                    // --- Start session and log history ---
                    $_SESSION['admin_id']    = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_logged_in'] = true;

                    $ip = getUserIP();
                    $login_time = date("Y-m-d H:i:s");
                    $insert = $conn->prepare("INSERT INTO admin_login_history (admin_id, login_time, ip_address) VALUES (?, ?, ?)");
                    if (!$insert) { throw new Exception("DB Prepare Error (Log History): ".$conn->error); }
                    $insert->bind_param("iss", $admin['id'], $login_time, $ip);
                    if (!$insert->execute()) { throw new Exception("DB Execute Error (Log History): ".$insert->error); }
                    $_SESSION['admin_login_history_id'] = $insert->insert_id;
                    $insert->close();
                    $insert = null;

                    // --- Success Response ---
                    $response = ["status" => "success", "redirect" => "index.php?route=admin-dashboard"]; // Ensure this redirect path is correct
                } else {
                    $response = ["status" => "error", "message" => "Account not verified. Check email."];
                }

            // --- CHECK 4: Password is INCORRECT ---
            } else {
                $new_attempts = $admin['login_attempts'] + 1;

                if ($new_attempts >= $max_attempts) {
                    // Lock the account
                    $lock_stmt = $conn->prepare("UPDATE admin_signup SET login_attempts = ?, is_locked = 1 WHERE id = ?");
                    if (!$lock_stmt) { throw new Exception("DB Prepare Error (Lock Account): ".$conn->error); }
                    $lock_stmt->bind_param("ii", $new_attempts, $admin_id);
                    if (!$lock_stmt->execute()) { throw new Exception("DB Execute Error (Lock Account): ".$lock_stmt->error); }
                    $lock_stmt->close();
                    $lock_stmt = null;

                    // Send security email (function handles errors internally for mail)
                    $attacker_info = getAttackerInfo();
                    sendAccountLockoutEmail($email, $attacker_info); // $response might be modified here if mail fails

                    // Set primary error message about lockout
                    $response['status'] = "error";
                    $response['message'] = "Account locked after 3 attempts. Check email / use 'Forgot Password'.";

                } else {
                    // Increment attempts
                    $attempt_stmt = $conn->prepare("UPDATE admin_signup SET login_attempts = ? WHERE id = ?");
                    if (!$attempt_stmt) { throw new Exception("DB Prepare Error (Increment Attempt): ".$conn->error); }
                    $attempt_stmt->bind_param("ii", $new_attempts, $admin_id);
                    if (!$attempt_stmt->execute()) { throw new Exception("DB Execute Error (Increment Attempt): ".$attempt_stmt->error); }
                    $attempt_stmt->close();
                    $attempt_stmt = null;

                    $remaining = $max_attempts - $new_attempts;
                    $response = ["status" => "error", "message" => "Invalid password. {$remaining} attempt(s) left."];
                }
            }
        } else {
            // Email not found
            $response = ["status" => "error", "message" => "Invalid email or password."];
        }

    } catch (\Throwable $e) {
        error_log("Admin Login CRITICAL Error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
        if (!headers_sent() && empty($response)) { // Avoid setting response if already set (e.g., mail error)
            $response = ["status" => "error", "message" => "A server error occurred. Please try again."];
        }
        // If display_errors is on, PHP error output might take over
    } finally {
        // Ensure all statements are closed
        if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
        if (isset($update_stmt) && $update_stmt instanceof mysqli_stmt) $update_stmt->close();
        if (isset($lock_stmt) && $lock_stmt instanceof mysqli_stmt) $lock_stmt->close();
        if (isset($attempt_stmt) && $attempt_stmt instanceof mysqli_stmt) $attempt_stmt->close();
        if (isset($insert) && $insert instanceof mysqli_stmt) $insert->close();
    }

} else {
    $response = ["status" => "error", "message" => "Invalid request method."];
}

if (isset($conn) && $conn instanceof mysqli) $conn->close();

// --- Final JSON Output ---
if (!headers_sent() && !empty($response)) {
    echo json_encode($response);
} elseif (empty($response) && !headers_sent() && $_SERVER["REQUEST_METHOD"] === "POST") {
    error_log("Admin Login finished POST without setting a response array.");
    echo json_encode(["status" => "error", "message" => "Unknown server error."]);
}
// If headers were sent (due to display_errors=on + PHP error), this echo won't happen.

exit();
?>