<?php
require 'connection.php';
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getMeal($_GET['id']);
        } else if (isset($_GET['user_id'])) {
            getMealsByUser($_GET['user_id']);
        } else if (isset($_GET['action']) && $_GET['action'] == 'getAllWithUserDetails') {
            getAllMealsWithUserDetails();
        } else if (isset($_GET['action']) && $_GET['action'] == 'search') {
            searchMeals($_GET['query'] ?? '');
        } else if (isset($_GET['action']) && $_GET['action'] == 'filter') {
            filterMeals($_GET);
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
    $stmt->bind_param("isdisssss", $user_id, $name, $price, $quantity, $location, $delivery_option, $description, $image_paths, $created_at);

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

    if (isset($_GET['meal_id'])) {
        $meal_id = $_GET['meal_id'];

        $sql = "DELETE FROM Meals WHERE meal_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $meal_id);

        if ($stmt->execute()) {
            echo json_encode(['message' => 'Meal deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error deleting meal: ' . $stmt->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['message' => 'Meal ID not provided in the request.']);
    }
}

function getAllMealsWithUserDetails() {
    global $conn;
    $sql = "SELECT m.*, u.username, u.profile_picture, u.rating
            FROM Meals m
            JOIN Users u ON m.user_id = u.user_id";
    $result = $conn->query($sql);
    $mealsWithDetails = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $mealsWithDetails[] = $row;
        }
    }
    echo json_encode($mealsWithDetails);
}

function searchMeals($query) {
    global $conn;
    $searchTerm = "%" . $query . "%";
    $sql = "SELECT m.*, u.username, u.profile_picture, u.rating
            FROM Meals m
            JOIN Users u ON m.user_id = u.user_id
            WHERE m.name LIKE ? OR u.username LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    $searchResults = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
    }
    echo json_encode($searchResults);
}

function filterMeals($filters) {
    global $conn;
    $whereClauses = [];
    $bindParams = [];
    $paramTypes = "";

    if (isset($filters['query']) && !empty($filters['query'])) {
        $whereClauses[] = "(m.name LIKE ? OR u.username LIKE ? OR m.description LIKE ?)";
        $searchTerm = "%" . $filters['query'] . "%";
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
        $bindParams[] = $searchTerm;
        $paramTypes .= "sss";
    }

    if (isset($filters['minPrice']) && isset($filters['maxPrice'])) {
        $whereClauses[] = "m.price BETWEEN ? AND ?";
        $bindParams[] = $filters['minPrice'];
        $bindParams[] = $filters['maxPrice'];
        $paramTypes .= "dd";
    } elseif (isset($filters['minPrice'])) {
        $whereClauses[] = "m.price >= ?";
        $bindParams[] = $filters['minPrice'];
        $paramTypes .= "d";
    } elseif (isset($filters['maxPrice'])) {
        $whereClauses[] = "m.price <= ?";
        $bindParams[] = $filters['maxPrice'];
        $paramTypes .= "d";
    }

    if (isset($filters['location']) && !empty($filters['location'])) {
        $whereClauses[] = "m.location LIKE ?";
        $bindParams[] = "%" . $filters['location'] . "%";
        $paramTypes .= "s";
    }

    if (isset($filters['minRating'])) {
        $whereClauses[] = "u.rating >= ?";
        $bindParams[] = $filters['minRating'];
        $paramTypes .= "d";
    }

    if (isset($filters['deliveryOption']) && !empty($filters['deliveryOption'])) {
        $whereClauses[] = "m.delivery_option = ?";
        $bindParams[] = $filters['deliveryOption'];
        $paramTypes .= "s";
    }

    if (isset($filters['tags']) && !empty($filters['tags'])) {
        $tagIds = explode(',', $filters['tags']);
        $whereClauses[] = "m.meal_id IN (SELECT meal_id FROM mealtags WHERE tag_id IN (" . implode(',', array_fill(0, count($tagIds), '?')) . ") GROUP BY meal_id HAVING COUNT(DISTINCT tag_id) = ?)";
        foreach ($tagIds as $tagId) {
            $bindParams[] = trim($tagId);
            $paramTypes .= "i";
        }
        $bindParams[] = count($tagIds);
        $paramTypes .= "i";
    }

    $sql = "SELECT m.*, u.username, u.profile_picture, u.rating
            FROM Meals m
            JOIN Users u ON m.user_id = u.user_id";

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $stmt = $conn->prepare($sql);

    if (!empty($bindParams)) {
        $stmt->bind_param($paramTypes, ...$bindParams);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $filteredMeals = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $filteredMeals[] = $row;
        }
    }
    echo json_encode($filteredMeals);
}

$conn->close();
?>