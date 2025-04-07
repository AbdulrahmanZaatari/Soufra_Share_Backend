<?php
require 'connection.php';
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
        createUser();
        break;
    case 'PUT': 
        updateUser();
        break;
    case 'DELETE': 
        deleteUser();
        break;
    default:
        http_response_code(405); 
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
    $password = password_hash($data->password, PASSWORD_DEFAULT); // Hash the password
    $profile_picture = $data->profile_picture ?? null;
    $location = $data->location ?? null;
    $national_id = $data->national_id ?? null;
    $is_cook = $data->is_cook ?? 0;

    $sql = "INSERT INTO Users (username, full_name, email, password, profile_picture, location, national_id, is_cook) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $username, $full_name, $email, $password, $profile_picture, $location, $national_id, $is_cook);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'User created successfully', 'user_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating user: ' . $stmt->error]);
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

    $sql = "UPDATE Users SET username=?, full_name=?, email=?, password=?, profile_picture=?, location=?, national_id=?, rating=?, is_cook=? WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssidi", $username, $full_name, $email, $password, $profile_picture, $location, $national_id, $rating, $is_cook, $user_id);

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