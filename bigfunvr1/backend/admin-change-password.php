<?php
session_start();
include "db.php";

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['msg'] = "Unauthorized access.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../admin-manage.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Collect form inputs
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate new passwords
if ($new_password !== $confirm_password) {
    $_SESSION['msg'] = "New passwords do not match.";
    $_SESSION['msg_type'] = "error";
    header("Location: ../admin-manage.php");
    exit();
}

// Fetch current password hash
$stmt = $conn->prepare("SELECT password FROM admin_signup WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (!password_verify($current_password, $row['password'])) {
        $_SESSION['msg'] = "Current password is incorrect.";
        $_SESSION['msg_type'] = "error";
        header("Location: ../admin-manage.php");
        exit();
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $update = $conn->prepare("UPDATE admin_signup SET password=? WHERE id=?");
    $update->bind_param("si", $hashed_password, $admin_id);

    if ($update->execute()) {
        $_SESSION['msg'] = "Password updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "Error updating password. Please try again.";
        $_SESSION['msg_type'] = "error";
    }
    $update->close();
}

$stmt->close();
$conn->close();

header("Location: ../admin-manage.php");
exit();
?>
