<?php
/**
 * Creates a new notification in the database for a specific user.
 *
 * @param mysqli $conn The database connection.
 * @param int $user_id The ID of the user who will RECEIVE the notification.
 * @param string $title The bolded title of the notification.
 * @param string $message The main text of the notification.
 * @param string|null $link (Optional) The page to go to when clicked (e.g., "invoices.php").
 */
function create_notification($conn, $user_id, $title, $message, $link = null) {
    try {
        // Ensure link is null if it's an empty string
        $link = empty($link) ? null : $link;
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        // You can log this error if you want, but we don't want to break the user's action
        // error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}
?>

