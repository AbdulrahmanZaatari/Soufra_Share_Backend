<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['meal_id'])) {
            getTagsForMeal($_GET['meal_id']);
        } elseif (isset($_GET['tag_id'])) {
            getMealsForTag($_GET['tag_id']);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Missing meal_id or tag_id parameter']);
        }
        break;
    case 'POST':
        addTagToMeal();
        break;
    case 'DELETE':
        removeTagFromMeal();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getTagsForMeal($meal_id) {
    global $conn;
    $sql = "SELECT t.tag_id, t.name FROM MealTags mt JOIN Tags t ON mt.tag_id = t.tag_id WHERE mt.meal_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tags = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
    }
    echo json_encode($tags);
}

function getMealsForTag($tag_id) {
    global $conn;
    $sql = "SELECT m.* FROM MealTags mt JOIN Meals m ON mt.meal_id = m.meal_id WHERE mt.tag_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tag_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meals = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $meals[] = $row;
        }
    }
    echo json_encode($meals);
}

function addTagToMeal() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $meal_id = $data->meal_id;
    $tag_id = $data->tag_id;

    $sql = "INSERT INTO MealTags (meal_id, tag_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $meal_id, $tag_id);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Tag added to meal successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error adding tag to meal: ' . $stmt->error]);
    }
}

function removeTagFromMeal() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $meal_id = $data->meal_id;
    $tag_id = $data->tag_id;

    $sql = "DELETE FROM MealTags WHERE meal_id = ? AND tag_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $meal_id, $tag_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Tag removed from meal successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error removing tag from meal: ' . $stmt->error]);
    }
}

$conn->close();
?>