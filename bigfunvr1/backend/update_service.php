<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic validation
    if (!isset($_POST['service_id']) || empty($_POST['service_name'])) {
        $_SESSION['message'] = "Error: Missing required fields.";
        header("Location: ../admin-services.php");
        exit();
    }

    $service_id = $_POST['service_id'];
    $service_category = $_POST['service_category'];
    $service_name = $_POST['service_name'];
    $price = $_POST['price'];
    $service_description = $_POST['service_description'];
    $service_specification = $_POST['service_specification'];
    $service_additional_info = $_POST['service_additional_info'];
    
    $new_image_filename = null;

    // Check if a new image was uploaded
    if (isset($_FILES["edit_service_image"]) && $_FILES["edit_service_image"]["error"] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/";
        $original_filename = basename($_FILES["edit_service_image"]["name"]);
        $imageFileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $unique_filename = time() . '_' . uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $unique_filename;

        // Simple validation for new image
        if (getimagesize($_FILES["edit_service_image"]["tmp_name"]) && move_uploaded_file($_FILES["edit_service_image"]["tmp_name"], $target_file)) {
            $new_image_filename = $unique_filename;
            
            // Optional: Delete the old image
            $old_image_path = $target_dir . $_POST['current_image_filename'];
            if (file_exists($old_image_path) && !empty($_POST['current_image_filename'])) {
                unlink($old_image_path);
            }
        } else {
            $_SESSION['message'] = "Error uploading new image.";
            header("Location: ../admin-services.php");
            exit();
        }
    }

    // Prepare the SQL statement
    if ($new_image_filename) {
        // If new image, update the image column
        $stmt = $conn->prepare("UPDATE services SET service_image=?, service_category=?, service_name=?, price=?, service_description=?, service_specification=?, service_additional_info=? WHERE id=?");
        $stmt->bind_param("sssdsssi", $new_image_filename, $service_category, $service_name, $price, $service_description, $service_specification, $service_additional_info, $service_id);
    } else {
        // If no new image, don't update the image column
        $stmt = $conn->prepare("UPDATE services SET service_category=?, service_name=?, price=?, service_description=?, service_specification=?, service_additional_info=? WHERE id=?");
        $stmt->bind_param("ssdsssi", $service_category, $service_name, $price, $service_description, $service_specification, $service_additional_info, $service_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Service updated successfully!";
    } else {
        $_SESSION['message'] = "Error updating service: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();
header("Location: ../admin-services.php");
exit();
?>