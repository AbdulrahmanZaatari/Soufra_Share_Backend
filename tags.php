<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getTag($_GET['id']);
        } else {
            getTags();
        }
        break;
    case 'POST':
        createTag();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getTags() {
    global $conn;
    $sql = "SELECT * FROM Tags";
    $result = $conn->query($sql);
    $tags = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
    }
    echo json_encode($tags);
}

function getTag($id) {
    global $conn;
    $sql = "SELECT * FROM Tags WHERE tag_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Tag not found']);
    }
}

function createTag() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $name = $data->name;

    $sql = "INSERT INTO Tags (name) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Tag created successfully', 'tag_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating tag: ' . $stmt->error]);
    }
}

$conn->close();
?>