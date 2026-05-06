<?php
session_start();
include "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in."]);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$email = trim($_POST['email']);
$password = trim($_POST['password']);

$response = [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email format."]);
    exit();
}

if (!empty($password)) {
    // update email + password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admin_signup SET email = ?, password = ? WHERE id = ?");
    $stmt->bind_param("ssi", $email, $hashedPassword, $admin_id);
} else {
    // update only email
    $stmt = $conn->prepare("UPDATE admin_signup SET email = ? WHERE id = ?");
    $stmt->bind_param("si", $email, $admin_id);
}

if ($stmt->execute()) {
    $response = ["status" => "success", "message" => "Profile updated successfully."];
} else {
    $response = ["status" => "error", "message" => "Update failed. Please try again."];
}
$stmt->close();

echo json_encode($response);
