<?php

require 'connection.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getUser($_GET['id']);
        } else {
            getUsers();
        }
        break;
    case 'POST':
        if (isset($_GET['action']) && $_GET['action'] == 'signin') {
            signInUser();
        } elseif (isset($_GET['action']) && $_GET['action'] == 'updateUserDetails') {
            updateUserDetails();
        } else {
            createUser();
        }
        break;
    case 'PUT':
        updateUser();
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getUsers() {
    global $conn;
    $sql = "SELECT * FROM Users";
    $result = $conn->query($sql);
    $users = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    echo json_encode($users);
}

function getUser($id) {
    global $conn;
    $sql = "SELECT * FROM Users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'User not found']);
    }
}

function createUser() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $username = $data->username;
    $full_name = $data->full_name;
    $email = $data->email;
    $password = password_hash($data->password, PASSWORD_DEFAULT);
    $profile_picture = $data->profile_picture ?? null;
    $location = $data->location ?? null;
    $national_id = $data->national_id ?? null;
    $is_cook = $data->is_cook ?? 0;
    $birth_data = $data->birth_data ?? null;
    $phone_num = $data->phone_num ?? null;

    $sql = "INSERT INTO Users (username, full_name, email, password, profile_picture, location, national_id, is_cook, birth_data, phone_num) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $username, $full_name, $email, $password, $profile_picture, $location, $national_id, $is_cook, $birth_data, $phone_num);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'User created successfully', 'user_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating user: ' . $stmt->error]);
    }
}

function signInUser() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $email = $data->email;
    $password = $data->password;

    $sql = "SELECT user_id, password, is_cook FROM Users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Instead of storing session variables, just return the data directly.
            echo json_encode([
                'success' => true,
                'message' => 'Sign in successful',
                'user_id' => $user['user_id'],
                'is_cook' => $user['is_cook']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
}

function updateUserDetails() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $user_id = $data->user_id;
    $username = $data->username ?? null;
    $full_name = $data->full_name ?? null;
    $email = $data->email ?? null;
    $phone_num = $data->phone_num ?? null;
    $location = $data->location ?? null;
    $profile_picture = $data->profile_picture ?? null; 
    $about = $data->about ?? null;

    $sql = "UPDATE Users SET username=?, full_name=?, email=?, phone_num=?, location=?, profile_picture=?, about=? WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $username, $full_name, $email, $phone_num, $location, $profile_picture, $about, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User details updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error updating user details: ' . $stmt->error]);
    }
}

function updateUser() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $user_id = $data->user_id;
    $username = $data->username ?? null;
    $full_name = $data->full_name ?? null;
    $email = $data->email ?? null;
    $password = isset($data->password) ? password_hash($data->password, PASSWORD_DEFAULT) : null;
    $profile_picture = $data->profile_picture ?? null;
    $location = $data->location ?? null;
    $national_id = $data->national_id ?? null;
    $rating = $data->rating ?? null;
    $is_cook = $data->is_cook ?? null;
    $birth_data = $data->birth_data ?? null; // Get birth_data for update
    $phone_num = $data->phone_num ?? null;   // Get phone_num for update

    $sql = "UPDATE Users SET username=?, full_name=?, email=?, password=?, profile_picture=?, location=?, national_id=?, rating=?, is_cook=?, birth_data=?, phone_num=? WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssiddssi", $username, $full_name, $email, $password, $profile_picture, $location, $national_id, $rating, $is_cook, $birth_data, $phone_num, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'User updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating user: ' . $stmt->error]);
    }
}

function deleteUser() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $user_id = $data->user_id;

    $sql = "DELETE FROM Users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'User deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting user: ' . $stmt->error]);
    }
}

$conn->close();
?>
