<?php
include "db.php";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if token exists
    $stmt = $conn->prepare("SELECT * FROM signup WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['is_verified'] == 0) {
            // Update verified status and log verified time
            $update = $conn->prepare("UPDATE signup SET is_verified = 1, verified_at = NOW() WHERE token = ?");
            $update->bind_param("s", $token);
            $update->execute();

            header("Location: redirect-message.php?status=verified");
            exit;
        } else {
            header("Location: redirect-message.php?status=already_verified");
            exit;
        }
    } else {
        header("Location: redirect-message.php?status=invalid");
        exit;
    }

    $stmt->close();
} else {
    header("Location: redirect-message.php?status=invalid");
    exit;
}
?>
