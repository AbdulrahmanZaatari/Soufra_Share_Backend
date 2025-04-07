<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getMeal($_GET['id']);
        } else if (isset($_GET['user_id'])) {
            getMealsByUser($_GET['user_id']);
        } else {
            getMeals();
        }
        break;
    case 'POST':
        createMeal();
        break;
    case 'PUT':
        updateMeal();
        break;
    case 'DELETE':
        deleteMeal();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getMeals() {
    global $conn;
    $sql = "SELECT * FROM Meals";
    $result = $conn->query($sql);
    $meals = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $meals[] = $row;
        }
    }
    echo json_encode($meals);
}

function getMealsByUser($user_id) {
    global $conn;
    $sql = "SELECT * FROM Meals WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
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

function getMeal($id) {
    global $conn;
    $sql = "SELECT * FROM Meals WHERE meal_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Meal not found']);
    }
}

function createMeal() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $user_id = $data->user_id;
    $name = $data->name;
    $price = $data->price;
    $quantity = $data->quantity;
    $location = $data->location;
    $delivery_option = $data->delivery_option;
    $description = $data->description;
    $image_paths = $data->image_paths ?? null;
    $created_at = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Meals (user_id, name, price, quantity, location, delivery_option, description, image_paths, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdiissss", $user_id, $name, $price, $quantity, $location, $delivery_option, $description, $image_paths, $created_at);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Meal created successfully', 'meal_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating meal: ' . $stmt->error]);
    }
}

function updateMeal() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $meal_id = $data->meal_id;
    $user_id = $data->user_id ?? null;
    $name = $data->name ?? null;
    $price = $data->price ?? null;
    $quantity = $data->quantity ?? null;
    $location = $data->location ?? null;
    $delivery_option = $data->delivery_option ?? null;
    $description = $data->description ?? null;
    $image_paths = $data->image_paths ?? null;

    $sql = "UPDATE Meals SET user_id=?, name=?, price=?, quantity=?, location=?, delivery_option=?, description=?, image_paths=? WHERE meal_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdiisssi", $user_id, $name, $price, $quantity, $location, $delivery_option, $description, $image_paths, $meal_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Meal updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating meal: ' . $stmt->error]);
    }
}

function deleteMeal() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $meal_id = $data->meal_id;

    $sql = "DELETE FROM Meals WHERE meal_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meal_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Meal deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting meal: ' . $stmt->error]);
    }
}

$conn->close();
?>