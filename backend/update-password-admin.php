<?php
session_start();
// --- ENABLE ERROR DISPLAY FOR DEBUGGING ONLY ---
// --- !!! COMMENT THESE OUT OR REMOVE IN PRODUCTION !!! ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------------------

// --- Database Connection ---
$dbFile = __DIR__ . '/db.php'; // Assumes db.php is in the same 'backend' directory
if (!file_exists($dbFile)) {
    error_log("FATAL: db.php not found at " . $dbFile . " in update-password-admin.php");
    // Attempt to send JSON error, but headers might already be sent if display_errors is on
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500); // Internal Server Error
        echo json_encode(["status" => "error", "message" => "Server configuration error [Code DB]."]);
    }
    exit();
}
include $dbFile; // Include the database connection file

// --- Check DB Connection Object ---
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
     $error_msg = isset($conn->connect_error) ? $conn->connect_error : 'Database connection object not created or invalid.';
     error_log("FATAL: DB Connection failed in update-password-admin.php - " . $error_msg);
     if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database connection failed [Code CON]."]);
     }
     exit();
}

// --- Set Header EARLY, before potential errors ---
// It's crucial this happens before ANY output if display_errors is off
if (!headers_sent()) {
    header('Content-Type: application/json');
}
$response = []; // Initialize response array


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Use null coalescing ?? '' to avoid undefined index warnings if fields are missing
    $token = trim($_POST['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // --- Basic Input Validation ---
    if (empty($token) || empty($password) || empty($confirm_password)) {
        $response = ["status" => "error", "message" => "All fields are required."];
        echo json_encode($response);
        exit();
    }

    // --- Password Complexity Check ---
    $specialCharRegex = '/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/';
    $upperCaseRegex = '/[A-Z]/';
    $numberRegex = '/[0-9]/';

    if (strlen($password) < 8 || !preg_match($specialCharRegex, $password) || !preg_match($upperCaseRegex, $password) || !preg_match($numberRegex, $password)) {
        $response = ["status" => "error", "message" => "Password does not meet requirements (8+ chars, 1 uppercase, 1 number, 1 special char)."];
        echo json_encode($response);
        exit();
    }

    if ($password !== $confirm_password) {
        $response = ["status" => "error", "message" => "Passwords do not match."];
        echo json_encode($response);
        exit();
    }

    // --- Token Processing ---
    // 1. HASH the plain token received from the form using SHA-256
    //    (Ensure this matches the hashing used when *saving* the token in forgot-password-admin-request.php)
    $token_hash = hash('sha256', $token);

    $stmt = null; // Initialize statement variables
    $update_stmt = null;

    try {
        // 2. Prepare SELECT statement to find user by the HASHED token
        //    *** VERIFY YOUR TABLE NAME IS EXACTLY `admin_signup` ***
        $stmt = $conn->prepare("SELECT id FROM `admin_signup` WHERE `token` = ? LIMIT 1");
        if (!$stmt) {
            // Throw exception if prepare fails
            throw new Exception("Database Prepare Error (Select): " . $conn->error);
        }

        // Bind the HASHED token to the placeholder
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // --- Token Found (Valid) ---
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            // Hash the NEW password securely using BCRYPT
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            // Check if hashing was successful
            if ($hashed_password === false) {
                throw new Exception("Password hashing failed unexpectedly.");
            }

            // --- Prepare the UPDATE statement ---
            //    *** VERIFY YOUR TABLE NAME IS EXACTLY `admin_signup` ***
            //    Updates password, clears token, resets lock status and attempts, sets as verified
            $update_stmt = $conn->prepare(
                "UPDATE `admin_signup` SET
                    `password` = ?,
                    `token` = NULL,
                    `is_locked` = 0,
                    `login_attempts` = 0,
                    `is_verified` = 1
                 WHERE `id` = ?"
            );
            if (!$update_stmt) {
                // Throw exception if prepare fails
                throw new Exception("Database Prepare Error (Update): " . $conn->error);
            }

            // Bind the new hashed password and user ID
            $update_stmt->bind_param("si", $hashed_password, $user_id);

            // Execute the update
            if ($update_stmt->execute()) {
                // --- Success! Set the success response ---
                $response = ["status" => "success", "message" => "Password updated successfully! Redirecting to login..."];
            } else {
                // Throw exception if execution fails
                 throw new Exception("Database Update Execution Error: " . $update_stmt->error);
            }
             // Close the update statement now that it's done
             $update_stmt->close();
             $update_stmt = null; // Prevent closing again in finally block

        } else {
            // --- Token Not Found (Invalid or Expired) ---
            // Log this attempt for debugging purposes
            error_log("Admin password reset failed: Invalid/Expired token provided (Plain Token: {$token}, Hash Attempted: {$token_hash})");
            $response = ["status" => "error", "message" => "Invalid or expired reset token."];
        }
         // Close the select statement now that it's done
         $stmt->close();
         $stmt = null; // Prevent closing again in finally block

    } catch (\Throwable $e) { // Catch any type of error or exception (PHP 7+)
        // Log the detailed error to the server's PHP error log
        error_log("Admin Update Password CRITICAL Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

        // Set a generic error response for the user
        // Avoid echoing the specific error message to the user for security
        $response = ["status" => "error", "message" => "An error occurred while updating the password. Please try again or contact support."];

        // If error display is ON for debugging, the PHP error might be shown directly in the browser
        // which will break the JSON response anyway.

    } finally {
        // --- Ensure statements are closed if they were opened and not already closed ---
        if (isset($update_stmt) && $update_stmt instanceof mysqli_stmt) {
            $update_stmt->close();
        }
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
    }

} else {
    // If the request method is not POST
    $response = ["status" => "error", "message" => "Invalid request method."];
}

// --- Close Database Connection ---
// Check if $conn is a valid mysqli object before closing
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// --- Final JSON Output ---
// Check if headers have already been sent (e.g., by PHP error output when display_errors is on)
// Also check if $response is actually set (it might not be if an error happened before assignment)
if (!headers_sent() && !empty($response)) {
    // If headers are okay and response is set, send the JSON
    echo json_encode($response);
} elseif (empty($response) && !headers_sent() && $_SERVER["REQUEST_METHOD"] === "POST") {
    // If it was a POST, but $response is empty and no headers sent, something went wrong
     error_log("Admin Update Password finished POST without setting a response array.");
     // Send a fallback error JSON
     echo json_encode(["status" => "error", "message" => "Unknown server error occurred."]);
}
// If headers were already sent (likely due to display_errors being ON and a PHP error occurring),
// we can't send JSON, and the raw PHP error should be visible in the browser network response.

exit(); // Terminate script execution
?>