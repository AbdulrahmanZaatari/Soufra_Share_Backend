<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$uploadDirectory = 'uploads/';
$response = array();

error_log("PHP Script Started");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("\$_FILES contents: " . print_r($_FILES, true));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                    $response['success'] = true;
                    $response['message'] = 'Profile picture uploaded successfully.';
                    $response['file_path'] = $destinationPath;
                    error_log("File moved successfully to: " . $destinationPath);
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
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
}

echo json_encode($response);
?>
