<?php
session_start();
include "db.php";

header('Content-Type: application/json');
$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = trim($_POST['token']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($token) || empty($password) || empty($confirm_password)) {
        $response = ["status" => "error", "message" => "All fields are required."];
        echo json_encode($response);
        exit();
    }

    // --- You should add your full password validation rules here ---
    // (This should match what's in your reset-password.php Javascript)
    $specialCharRegex = '/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/';
    $upperCaseRegex = '/[A-Z]/';
    $numberRegex = '/[0-9]/';

    if (strlen($password) < 8 || !preg_match($specialCharRegex, $password) || !preg_match($upperCaseRegex, $password) || !preg_match($numberRegex, $password)) {
        $response = ["status" => "error", "message" => "Password does not meet all requirements."];
        echo json_encode($response);
        exit();
    }

    if ($password !== $confirm_password) {
        $response = ["status" => "error", "message" => "Passwords do not match."];
        echo json_encode($response);
        exit();
    }

    // Check if token is valid
    $stmt = $conn->prepare("SELECT id FROM signup WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // --- THIS IS THE MODIFIED LINE ---
        // It now updates password, clears token, unlocks account, and verifies account
        $update_stmt = $conn->prepare("UPDATE signup SET password = ?, token = NULL, is_locked = 0, login_attempts = 0, is_verified = 1 WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            $response = ["status" => "success", "message" => "Password updated successfully! Redirecting to login..."];
        } else {
            $response = ["status" => "error", "message" => "Database error. Could not update password."];
        }
        $update_stmt->close();
    } else {
        $response = ["status" => "error", "message" => "Invalid or expired reset token."];
    }
    $stmt->close();
} else {
    $response = ["status" => "error", "message" => "Invalid request method."];
}

$conn->close();
echo json_encode($response);
exit();
?>