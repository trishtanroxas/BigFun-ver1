<?php
// Start the session to access session variables
session_start();

// 1. --- SECURITY CHECK ---
// Check if the admin is logged in. If not, redirect to the login page.
if (!isset($_SESSION['admin_id'])) {
    // The user is not logged in, so we send them away.
    header("Location: ../login.php");
    exit();
}

// Include the database connection file
include "db.php";

// 2. --- VALIDATE REQUEST ---
// Ensure the script is accessed via a POST request and that the necessary data is provided.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if the service ID is set and not empty
    if (isset($_POST['delete_service_id']) && !empty($_POST['delete_service_id'])) {
        
        $service_id = $_POST['delete_service_id'];
        $image_filename = $_POST['delete_image_filename'];

        // 3. --- DELETE THE IMAGE FILE ---
        // It's good practice to delete the file from the server to prevent orphaned files.
        if (!empty($image_filename)) {
            // Construct the full path to the image.
            // We use '../uploads/' because this script is in the 'backend' folder,
            // and we need to go up one level to find the 'uploads' folder.
            $image_path = '../uploads/' . $image_filename;

            // Check if the file actually exists before trying to delete it
            if (file_exists($image_path)) {
                unlink($image_path); // The unlink() function deletes the file
            }
        }

        // 4. --- DELETE THE DATABASE RECORD ---
        // Use a prepared statement to prevent SQL injection attacks.
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        
        // Bind the service ID to the placeholder. 'i' specifies the variable type is an integer.
        $stmt->bind_param("i", $service_id);

        // Execute the statement and check for success
        if ($stmt->execute()) {
            // If deletion was successful, set a success message.
            $_SESSION['message'] = "Service '" . htmlspecialchars($_POST['delete_service_name']) . "' was deleted successfully.";
        } else {
            // If there was an error, set an error message.
            $_SESSION['message'] = "Error: Could not delete the service. Please try again. " . $stmt->error;
        }

        // Close the prepared statement
        $stmt->close();

    } else {
        // If the service ID was not provided in the POST request.
        $_SESSION['message'] = "Error: Invalid request. No service ID provided.";
    }

} else {
    // If the page was accessed directly via URL (GET request) instead of the form.
    $_SESSION['message'] = "Error: Invalid request method.";
}

// Close the database connection
if ($conn) {
    $conn->close();
}

// 5. --- REDIRECT BACK ---
// Redirect the admin back to the services page to see the result.
header("Location: ../admin-services.php");
exit(); // Always call exit() after a header redirect
?>