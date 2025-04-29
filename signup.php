<?php
require 'connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['username'], $_POST['full_name'], $_POST['email'], $_POST['password'], $_POST['location'], $_POST['national_id'], $_POST['birth_data'], $_POST['phone_num'])) {
        $response['message'] = 'Missing required fields.';
        echo json_encode($response);
        exit;
    }

    $username = $_POST['username'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $location = $_POST['location'];
    $national_id = $_POST['national_id'];
    $birth_data = $_POST['birth_data'];
    $phone_num = $_POST['phone_num'];
    $is_cook = isset($_POST['is_cook']) ? intval($_POST['is_cook']) : 0;

    $profile_picture_path = null; 


    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDirectory = 'uploads/';
        $file = $_FILES['profile_image'];

        $filename = basename($file['name']);
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFilename = md5(uniqid()) . "." . $fileExtension;
            $destinationPath = $uploadDirectory . $newFilename;

            if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                $profile_picture_path = $destinationPath; // Save path to database
            } else {
                $response['message'] = 'Error moving uploaded file.';
                echo json_encode($response);
                exit;
            }
        } else {
            $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF allowed.';
            echo json_encode($response);
            exit;
        }
    }

    $sql = "INSERT INTO Users (username, full_name, email, password, profile_picture, location, national_id, is_cook, birth_data, phone_num)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $username, $full_name, $email, $password, $profile_picture_path, $location, $national_id, $is_cook, $birth_data, $phone_num);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'User created successfully with profile picture.';
        $response['user_id'] = $conn->insert_id; 
        $response['profile_picture_path'] = $profile_picture_path;
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }

    $stmt->close();
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
$conn->close();
?>
