<?php
session_start();
include "db.php"; // Your database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ❗ FIXED: The query no longer tries to update the email column
    $stmt = $conn->prepare("UPDATE signup SET first_name=?, last_name=?, middle_initial=?, country=?, city=?, address=?, postal_code=? WHERE id=?");
    
    // ❗ FIXED: The bind_param no longer includes the email
    $stmt->bind_param("sssssssi", 
        $_POST['first_name'], 
        $_POST['last_name'], 
        $_POST['middle_initial'],
        $_POST['country'], 
        $_POST['city'], 
        $_POST['address'], 
        $_POST['postal_code'], 
        $user_id
    );
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = "Profile updated successfully!";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "Failed to update profile. Error: " . $stmt->error;
        $_SESSION['msg_type'] = "danger";
    }
    $stmt->close();
    
    header("Location: ../profile-manage.php");
    exit;
}
?>