<?php
// backend/checkout_process.php
session_start();
include "db.php";
include "notification_helper.php"; // <-- 1. Include the helper

// ... your code to validate and insert the order ...

if ($success) {
    $conn->commit();
    $new_order_id = $conn->insert_id;
    $customer_id = $_SESSION['user_id'];

    // 2. Create notification FOR THE CUSTOMER
    create_notification(
        $conn,
        $customer_id,
        "Order Received!",
        "Your order #{$new_order_id} has been successfully placed. We will notify you once it's confirmed.",
        "invoices.php"
    );

    // ... redirect to success page ...
}
?>


