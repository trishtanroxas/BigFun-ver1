<?php
session_start();
include "db.php";

// If user is logged in, record logout_time
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Find the most recent login for this user
    $stmt = $conn->prepare("SELECT id FROM login_history WHERE user_id = ? ORDER BY login_time DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $history_id = $row['id'];

        // Update logout_time
        $logout_time = date("Y-m-d H:i:s");
        $update = $conn->prepare("UPDATE login_history SET logout_time = ? WHERE id = ?");
        $update->bind_param("si", $logout_time, $history_id);
        $update->execute();
        $update->close();
    }
    $stmt->close();
}

// ✅ Destroy all session data securely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 🚫 Prevent going back after logout (disable cache)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page
header("Location: ../login.php");
exit;
