<?php
session_start();
include "db.php"; // Your database connection

// Check if the form was submitted and a file was uploaded
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["service_image"])) {

    // --- Pre-Upload Checks ---
    $upload_error = $_FILES["service_image"]["error"];
    if ($upload_error !== UPLOAD_ERR_OK) {
        // Handle specific upload errors
        switch ($upload_error) {
            case UPLOAD_ERR_INI_SIZE:
                $_SESSION['message'] = "Error: The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $_SESSION['message'] = "Error: The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $_SESSION['message'] = "Error: The uploaded file was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $_SESSION['message'] = "Error: No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $_SESSION['message'] = "Error: Missing a temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $_SESSION['message'] = "Error: Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                $_SESSION['message'] = "Error: A PHP extension stopped the file upload.";
                break;
            default:
                $_SESSION['message'] = "An unknown upload error occurred.";
                break;
        }
        header("Location: ../admin-services.php");
        exit();
    }

    $target_dir = "../uploads/"; // The directory where images will be stored
    
    // --- Directory and Permissions Checks ---
    if (!is_dir($target_dir)) {
        $_SESSION['message'] = "Error: The upload directory does not exist. Please create the 'uploads' folder.";
        header("Location: ../admin-services.php");
        exit();
    }
    if (!is_writable($target_dir)) {
        $_SESSION['message'] = "Error: The upload directory is not writable. Please check the folder permissions.";
        header("Location: ../admin-services.php");
        exit();
    }

    // --- File Validation ---
    $original_filename = basename($_FILES["service_image"]["name"]);
    $imageFileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $unique_filename = time() . '_' . uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $unique_filename;

    // Check if image file is a actual image
    if (!getimagesize($_FILES["service_image"]["tmp_name"])) {
        $_SESSION['message'] = "Error: File is not a valid image.";
        header("Location: ../admin-services.php");
        exit();
    }

    // Check file size (e.g., 5MB limit)
    if ($_FILES["service_image"]["size"] > 5000000) {
        $_SESSION['message'] = "Error: Your file is too large (Max 5MB).";
        header("Location: ../admin-services.php");
        exit();
    }

    // Allow certain file formats
    $allowed_types = ["jpg", "png", "jpeg", "gif"];
    if (!in_array($imageFileType, $allowed_types)) {
        $_SESSION['message'] = "Error: Only JPG, JPEG, PNG & GIF files are allowed.";
        header("Location: ../admin-services.php");
        exit();
    }

    // --- Move the File and Insert into Database ---
    if (move_uploaded_file($_FILES["service_image"]["tmp_name"], $target_file)) {
        // File uploaded successfully, now insert data into database
        $service_category = $_POST['service_category'];
        $service_name = $_POST['service_name'];
        $price = $_POST['price'];
        $service_description = $_POST['service_description'];
        $service_specification = $_POST['service_specification'];
        $service_additional_info = $_POST['service_additional_info'];
        
        $stmt = $conn->prepare("INSERT INTO services (service_image, service_category, service_name, price, service_description, service_specification, service_additional_info) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdsss", $unique_filename, $service_category, $service_name, $price, $service_description, $service_specification, $service_additional_info);

        if ($stmt->execute()) {
            $_SESSION['message'] = "New service added successfully!";
        } else {
            $_SESSION['message'] = "Database Error: " . $stmt->error;
        }
        $stmt->close();

    } else {
        // This message will now only show if move_uploaded_file fails for a rare reason
        $_SESSION['message'] = "Sorry, there was a critical error uploading your file.";
    }
}

$conn->close();
header("Location: ../admin-services.php");
exit();