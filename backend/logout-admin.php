<?php
session_start();
include "db.php"; // Your database connection

// Check if a login session history ID exists (using the corrected variable name)
if (isset($_SESSION['admin_login_history_id'])) {
    $login_history_id = $_SESSION['admin_login_history_id'];

    // Update the logout_time for the current session record
    $stmt = $conn->prepare("UPDATE admin_login_history SET logout_time = NOW() WHERE id = ?");
    $stmt->bind_param("i", $login_history_id);
    $stmt->execute();
    $stmt->close();
}

// Destroy all admin session data
$_SESSION = [];
session_unset();
session_destroy();

// Prevent browser from caching the page
header("Cache-control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0");      // Proxies

// Redirect to admin login
header("Location: ../login.php");
exit();
?>