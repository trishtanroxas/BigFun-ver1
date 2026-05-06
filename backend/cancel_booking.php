<?php
session_start();

// Set the content type to JSON, as the frontend expects a JSON response.
header('Content-Type: application/json');

// Include the database connection.
include "db.php";

// --- 1. Security & Validation ---

// Check if the request method is POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Check if the user is logged in.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required. Please log in.']);
    exit;
}

// Check if the order_id was sent and is a valid number.
if (!isset($_POST['order_id']) || !filter_var($_POST['order_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing Order ID.']);
    exit;
}

// --- 2. Process the Cancellation ---

$order_id = $_POST['order_id'];
$user_id = $_SESSION['user_id'];
// Get the reason, providing a default value if it's empty.
$reason = !empty(trim($_POST['reason'])) ? trim($_POST['reason']) : 'No reason provided.';

// --- 3. Database Update ---

// Prepare the UPDATE statement. This is crucial for security.
// We are not deleting the record, just changing its status to 'Cancelled'.
// **IMPORTANT:** We also add a WHERE clause for user_id to ensure a user can only cancel THEIR OWN booking.
$stmt = $conn->prepare(
    "UPDATE orders 
     SET booking_status = 'Cancelled', cancellation_reason = ? 
     WHERE id = ? AND user_id = ?"
);

// Bind the parameters: 's' for string (reason), 'i' for integer (id, user_id)
$stmt->bind_param("sii", $reason, $order_id, $user_id);

// Execute the statement and check the result.
if ($stmt->execute()) {
    // Check if any row was actually affected.
    if ($stmt->affected_rows > 0) {
        // Success! The record was found and updated.
        echo json_encode(['status' => 'success', 'message' => 'Booking cancelled successfully.']);
    } else {
        // The query ran, but no rows were updated. This means either the order ID
        // was invalid or it didn't belong to the logged-in user.
        echo json_encode(['status' => 'error', 'message' => 'Could not find booking or you do not have permission to cancel it.']);
    }
} else {
    // The query itself failed to execute.
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred during cancellation.']);
}

// --- 4. Cleanup ---
$stmt->close();
$conn->close();
exit;