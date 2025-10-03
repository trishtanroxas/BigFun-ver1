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

// Collect POST data
$first_name     = trim($_POST['first_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');
$middle_initial = trim($_POST['middle_initial'] ?? '');
$country        = trim($_POST['country'] ?? '');
$city           = trim($_POST['city'] ?? '');
$postal_code    = trim($_POST['postal_code'] ?? '');
$address        = trim($_POST['address'] ?? '');

// Update query
$stmt = $conn->prepare("UPDATE admin_signup 
                        SET first_name=?, last_name=?, middle_initial=?, country=?, city=?, postal_code=?, address=? 
                        WHERE id=?");
$stmt->bind_param("sssssssi", $first_name, $last_name, $middle_initial, $country, $city, $postal_code, $address, $admin_id);

if ($stmt->execute()) {
    $_SESSION['msg'] = "Profile updated successfully!";
    $_SESSION['msg_type'] = "success";
} else {
    $_SESSION['msg'] = "Error updating profile. Please try again.";
    $_SESSION['msg_type'] = "error";
}

$stmt->close();
$conn->close();

header("Location: ../admin-manage.php");
exit();
?>
