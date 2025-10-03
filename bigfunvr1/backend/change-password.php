<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $conn->prepare("SELECT password FROM signup WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($_POST['current_password'], $hashed)) {
        $_SESSION['msg'] = "Current password is incorrect.";
        $_SESSION['msg_type'] = "error";
        header("Location: ../profile-manage.php");
        exit;
    }

    if ($_POST['new_password'] !== $_POST['confirm_password']) {
        $_SESSION['msg'] = "Passwords do not match.";
        $_SESSION['msg_type'] = "error";
        header("Location: ../profile-manage.php");
        exit;
    }

    $new_hashed = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE signup SET password=? WHERE id=?");
    $stmt->bind_param("si", $new_hashed, $user_id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Password updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "Failed to update password.";
        $_SESSION['msg_type'] = "error";
    }
    $stmt->close();
    header("Location: ../profile-manage.php");
    exit;
}
?>
