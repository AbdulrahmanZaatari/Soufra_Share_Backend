<?php
require 'connection.php';
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$uploadDirectory = 'uploads/';
$response = array();

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getMeal($_GET['id']);
        } else if (isset($_GET['user_id'])) {
            getMealsByUser($_GET['user_id']); // Original function remains
        } else if (isset($_GET['action']) && $_GET['action'] == 'getUserMealsWithDetails') { // New action
            getUserMealsWithUserDetails($_GET['user_id'] ?? -1); // Get user_id for the new function
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

function getUserMealsWithUserDetails($user_id) { // New function
    global $conn;
    if ($user_id == -1) {
        echo json_encode([]); // Or handle the error as needed
        return;
    }
    $sql = "SELECT m.*, u.username, u.profile_picture, u.rating
            FROM Meals m
            JOIN Users u ON m.user_id = u.user_id
            WHERE m.user_id = ?";
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
    global $conn, $uploadDirectory, $response;
    error_log("createMeal() function called");
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("\$_FILES contents: " . print_r($_FILES, true));
    error_log("\$_POST contents: " . print_r($_POST, true));

    $mealImagePaths = array();
    // Handle meal image upload
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['meal_image'];
        $filename = basename($file['name']);
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFilename = md5(uniqid()) . "." . $fileExtension;
            $destinationPath = $uploadDirectory . $newFilename;
            if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                $mealImagePaths[] = $newFilename; // Store just the filename in the array
                error_log("Meal image uploaded successfully to: " . $destinationPath);
            } else {
                $response['error'] = true;
                $response['message'] = 'Error moving the uploaded meal image.';
                echo json_encode($response);
                exit;
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Invalid meal image file type.';
            echo json_encode($response);
            exit;
        }
    }

    // Handle other meal details from $_POST
    $user_id = $_POST['user_id'] ?? null;
    $name = $_POST['name'] ?? null;
    $price = $_POST['price'] ?? null;
    $quantity = $_POST['quantity'] ?? null;
    $location = $_POST['location'] ?? null;
    $delivery_option = $_POST['delivery_option'] ?? null;
    $description = $_POST['description'] ?? null;

    if ($user_id !== null && $name !== null && $price !== null && $quantity !== null && $location !== null && $delivery_option !== null && $description !== null) {
        $image_paths_json = !empty($mealImagePaths) ? json_encode($mealImagePaths) : '[]';

        $sql = "INSERT INTO Meals (user_id, name, price, quantity, location, delivery_option, description, image_paths, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdissss", $user_id, $name, $price, $quantity, $location, $delivery_option, $description, $image_paths_json);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Meal created successfully.';
            $response['meal_id'] = $conn->insert_id;
            $response['image_paths'] = $mealImagePaths; // Return the uploaded image paths
        } else {
            $response['error'] = true;
            $response['message'] = 'Error creating meal: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['error'] = true;
        $response['message'] = 'Missing required fields.';
    }
    echo json_encode($response);
}

function updateMeal() {
    global $conn;
    $raw_input = file_get_contents("php://input");
    error_log("PHP updateMeal Raw Input: " . $raw_input);
    $data = json_decode($raw_input, true);

    error_log("PHP updateMeal Decoded Data (print_r): " . print_r($data, true));
    error_log("PHP updateMeal Decoded Data (var_dump): " . var_export($data, true)); 

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("PHP updateMeal JSON Decode Error: " . json_last_error_msg());
        http_response_code(400); 
        echo json_encode(['message' => 'Invalid JSON data received']);
        return; 
    }
    $meal_id = $data['meal_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $name = $data['name'] ?? null;
    $price = $data['price'] ?? null;
    $quantity = $data['quantity'] ?? null;
    $location = $data['location'] ?? null;
    $delivery_option = $data['delivery_option'] ?? null;
    $description = $data['description'] ?? null;
    error_log("PHP updateMeal Retrieved Variables: " .
              "meal_id=" . ($meal_id === null ? 'NULL' : $meal_id) .
              ", user_id=" . ($user_id === null ? 'NULL' : $user_id) .
              ", name=" . ($name === null ? 'NULL' : '"' . $name . '"') . 
              ", price=" . ($price === null ? 'NULL' : $price) .
              ", quantity=" . ($quantity === null ? 'NULL' : $quantity) .
              ", location=" . ($location === null ? 'NULL' : '"' . $location . '"') . 
              ", delivery_option=" . ($delivery_option === null ? 'NULL' : $delivery_option) .
              ", description=" . ($description === null ? 'NULL' : '"' . $description . '"')); 

    if ($meal_id !== null && $user_id !== null && $name !== null && $price !== null && $quantity !== null && $location !== null && $delivery_option !== null && $description !== null) {
        error_log("PHP updateMeal: All required fields are present. Proceeding with SQL update.");
        $sql = "UPDATE Meals SET user_id=?, name=?, price=?, quantity=?, location=?, delivery_option=?, description=? WHERE meal_id=?";
        $stmt = $conn->prepare($sql);
        $bind_result = $stmt->bind_param("isdiissi", $user_id, $name, $price, $quantity, $location, $delivery_option, $description, $meal_id);
        if ($bind_result === false) {
            error_log("PHP updateMeal bind_param failed: " . $stmt->error);
        } else {
            error_log("PHP updateMeal bind_param successful.");
        }
        if ($stmt->execute()) {
            error_log("PHP updateMeal SQL Execute successful.");
            echo json_encode(['message' => 'Meal updated successfully']);
        } else {
            http_response_code(500); 
            error_log("PHP updateMeal SQL Execution Error: " . $stmt->error); 
            echo json_encode(['message' => 'Error updating meal: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        error_log("PHP updateMeal: Validation failed. Missing required fields.");
        http_response_code(400); 
        echo json_encode(['message' => 'Missing required fields for update']);
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
            JOIN Users u ON m.user_id = u.user_id
            WHERE m.quantity > 0"; 
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