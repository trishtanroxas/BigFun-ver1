<?php
session_start();
include "db.php"; // Include your database connection

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Initialize attempts if not set
if (!isset($_SESSION['change_password_attempts'])) {
    $_SESSION['change_password_attempts'] = 0;
}

$max_attempts = 3;
$user_id = $_SESSION['user_id'];
$redirect_url = "../profile-manage.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // --- 1. Check if this form is already locked ---
    if ($_SESSION['change_password_attempts'] >= $max_attempts) {
        $_SESSION['msg'] = "Too many incorrect attempts. For security, please use the 'Forgot Password' feature.";
        $_SESSION['msg_type'] = 'danger';
        header("Location: $redirect_url");
        exit;
    }

    // --- 2. Get form data ---
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // --- 3. Validate input ---
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['msg'] = "All password fields are required.";
        $_SESSION['msg_type'] = 'danger';
        header("Location: $redirect_url");
        exit;
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['msg'] = "New passwords do not match.";
        $_SESSION['msg_type'] = 'danger';
        header("Location: $redirect_url");
        exit;
    }

    // --- 4. Check password complexity (same as your other scripts) ---
    $specialCharRegex = '/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/';
    $upperCaseRegex = '/[A-Z]/';
    $numberRegex = '/[0-9]/';

    if (strlen($new_password) < 8 || !preg_match($specialCharRegex, $new_password) || !preg_match($upperCaseRegex, $new_password) || !preg_match($numberRegex, $new_password)) {
        $_SESSION['msg'] = "New password does not meet all requirements (8+ chars, 1 uppercase, 1 number, 1 special char).";
        $_SESSION['msg_type'] = 'danger';
        header("Location: $redirect_url");
        exit;
    }

    // --- 5. Main Logic: Verify Current Password ---
    $stmt = $conn->prepare("SELECT password FROM signup WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($current_password, $user['password'])) {
        // --- SUCCESS ---
        // Current password is correct. Update to the new one.
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $update_stmt = $conn->prepare("UPDATE signup SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        $update_stmt->execute();
        $update_stmt->close();

        // Reset attempts
        $_SESSION['change_password_attempts'] = 0; 
        
        $_SESSION['msg'] = "Password updated successfully!";
        $_SESSION['msg_type'] = 'success';
    } else {
        // --- FAILURE ---
        // Current password was incorrect.
        $_SESSION['change_password_attempts']++;
        $remaining = $max_attempts - $_SESSION['change_password_attempts'];

        if ($remaining > 0) {
            $_SESSION['msg'] = "Incorrect current password. You have $remaining attempt(s) remaining.";
        } else {
            $_SESSION['msg'] = "Incorrect current password. This form is now locked. Please use the 'Forgot Password' feature.";
        }
        $_SESSION['msg_type'] = 'danger';
    }

    $conn->close();
    header("Location: $redirect_url");
    exit;

} else {
    // Not a POST request
    header("Location: $redirect_url");
    exit;
}
?>