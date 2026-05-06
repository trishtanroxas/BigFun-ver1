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

    // --- 1. Fetch current user data BEFORE making changes ---
    $stmt_current = $conn->prepare("SELECT * FROM signup WHERE id = ? LIMIT 1");
    $stmt_current->bind_param("i", $user_id);
    $stmt_current->execute();
    $current_user_data = $stmt_current->get_result()->fetch_assoc();
    $stmt_current->close();

    // --- 2. Define submitted data and track changes ---
    $submitted_data = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'middle_initial' => $_POST['middle_initial'] ?? '',
        'country' => $_POST['country'] ?? '',
        'city' => $_POST['city'] ?? '',
        'address' => $_POST['address'] ?? '',
        'postal_code' => $_POST['postal_code'] ?? '',
        'contact_number' => $_POST['contact_number'] ?? '' // <-- The missing field
    ];
    
    $changes = [];
    $field_names = [ // User-friendly names
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'middle_initial' => 'Middle Initial',
        'country' => 'Country',
        'city' => 'City',
        'address' => 'Address',
        'postal_code' => 'Postal Code',
        'contact_number' => 'Contact Number'
    ];

    foreach ($submitted_data as $key => $value) {
        // Check if the submitted value is different from the one in the database
        if ($value != $current_user_data[$key]) {
            $changes[] = $field_names[$key];
        }
    }

    // --- 3. Handle based on whether changes were detected ---
    if (count($changes) == 0) {
        // NO CHANGES: Send a "modify first" message
        $_SESSION['msg'] = "Please modify your information first. No changes were saved.";
        $_SESSION['msg_type'] = "info"; // Use 'info' for a blue alert
    } else {
        // CHANGES DETECTED: Update the database
        
        // --- FIX: Added 'contact_number = ?' to the query ---
        $stmt_update = $conn->prepare("UPDATE signup SET 
            first_name = ?, last_name = ?, middle_initial = ?, 
            country = ?, city = ?, address = ?, postal_code = ?, 
            contact_number = ? 
            WHERE id = ?");
        
        // --- FIX: Added contact_number and user_id to bind_param ---
        $stmt_update->bind_param("ssssssssi", 
            $submitted_data['first_name'], 
            $submitted_data['last_name'], 
            $submitted_data['middle_initial'],
            $submitted_data['country'], 
            $submitted_data['city'], 
            $submitted_data['address'], 
            $submitted_data['postal_code'],
            $submitted_data['contact_number'], // <-- Added this
            $user_id // <-- Added this
        );
        
        if ($stmt_update->execute()) {
            // --- 4. Build the dynamic success message ---
            if (count($changes) > 3) {
                $_SESSION['msg'] = "Profile updated successfully!";
            } elseif (count($changes) == 1) {
                $_SESSION['msg'] = $changes[0] . " updated successfully!";
            } elseif (count($changes) == 2) {
                $_SESSION['msg'] = $changes[0] . " and " . $changes[1] . " updated successfully!";
            } else {
                // For 3 changes
                $_SESSION['msg'] = $changes[0] . ", " . $changes[1] . ", and " . $changes[2] . " updated successfully!";
            }
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "Failed to update profile. Error: " . $stmt_update->error;
            $_SESSION['msg_type'] = "danger";
        }
        $stmt_update->close();
    }
    
    header("Location: ../profile-manage.php");
    exit;
}
?>
