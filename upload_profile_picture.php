<?php
require 'connection.php'; 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$uploadDirectory = 'uploads/';
$response = array('success' => false, 'message' => '');

error_log("PHP Script Started");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("\$_FILES contents: " . print_r($_FILES, true));
error_log("\$_POST contents: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $user_id = $_POST['user_id'];

        if (isset($_FILES['profile_image'])) {
            $file = $_FILES['profile_image'];
            error_log("\$_FILES['profile_image'] is set");
            error_log("File details: " . print_r($file, true));

            // Check for upload errors
            if ($file['error'] === UPLOAD_ERR_OK) {
                error_log("File upload error is OK");
                $filename = basename($file['name']);
                $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');

                if (in_array($fileExtension, $allowedExtensions)) {
                    $newFilename = md5(uniqid()) . "." . $fileExtension;
                    $destinationPath = $uploadDirectory . $newFilename;
                    error_log("Destination Path: " . $destinationPath);

                    // Move the uploaded file to the destination directory
                    if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                        error_log("File moved successfully to: " . $destinationPath);

                        // Update the user's profile picture path in the database
                        $sql = "UPDATE Users SET profile_picture = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $destinationPath, $user_id);

                        if ($stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = 'Profile picture uploaded and updated successfully.';
                            $response['file_path'] = $destinationPath;
                            error_log("Database updated successfully for user ID: " . $user_id);
                        } else {
                            $response['success'] = false;
                            $response['message'] = 'Error updating database: ' . $stmt->error;
                            error_log("Error updating database for user ID " . $user_id . ": " . $stmt->error);
                            // Optionally, you might want to delete the uploaded file if the database update fails
                            unlink($destinationPath);
                        }
                        $stmt->close();

                    } else {
                        $response['success'] = false;
                        $response['message'] = 'Error moving the uploaded file.';
                        error_log("Error moving the uploaded file. Temporary path: " . $file['tmp_name']);
                    }
                } else {
                    $response['success'] = false;
                    $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                    error_log("Invalid file type: " . $fileExtension);
                }
            } else {
                $response['success'] = false;
                $response['message'] = 'Error during file upload: ' . $file['error'];
                error_log("Error during file upload (UPLOAD_ERR): " . $file['error']);
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'No profile picture file was uploaded.';
            error_log("\$_FILES['profile_image'] is NOT set.");
        }
    } else {
        $response['success'] = false;
        $response['message'] = 'User ID is missing or empty.';
        error_log("User ID is missing in the POST request.");
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
}

echo json_encode($response);
$conn->close();
?>