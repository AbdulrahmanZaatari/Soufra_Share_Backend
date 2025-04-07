<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getDailySale($_GET['id']);
        } elseif (isset($_GET['sale_date'])) {
            getDailySaleByDate($_GET['sale_date']);
        } else {
            getDailySales();
        }
        break;
    case 'POST':
        createDailySale();
        break;
    case 'PUT':
        updateDailySale();
        break;
    case 'DELETE':
        deleteDailySale();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getDailySales() {
    global $conn;
    $sql = "SELECT * FROM DailySales";
    $result = $conn->query($sql);
    $dailySales = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dailySales[] = $row;
        }
    }
    echo json_encode($dailySales);
}

function getDailySaleByDate($sale_date) {
    global $conn;
    $sql = "SELECT * FROM DailySales WHERE sale_date = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sale_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Daily sales for date not found']);
    }
}

function getDailySale($id) {
    global $conn;
    $sql = "SELECT * FROM DailySales WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Daily sale not found']);
    }
}

function createDailySale() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $sale_date = $data->sale_date;
    $total_sales = $data->total_sales ?? 0.00;
    $total_orders = $data->total_orders ?? 0;

    $sql = "INSERT INTO DailySales (sale_date, total_sales, total_orders) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdi", $sale_date, $total_sales, $total_orders);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Daily sale created successfully', 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating daily sale: ' . $stmt->error]);
    }
}

function updateDailySale() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;
    $sale_date = $data->sale_date ?? null;
    $total_sales = $data->total_sales ?? null;
    $total_orders = $data->total_orders ?? null;

    $sql = "UPDATE DailySales SET sale_date=?, total_sales=?, total_orders=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdii", $sale_date, $total_sales, $total_orders, $id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Daily sale updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating daily sale: ' . $stmt->error]);
    }
}

function deleteDailySale() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;

    $sql = "DELETE FROM DailySales WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Daily sale deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting daily sale: ' . $stmt->error]);
    }
}

$conn->close();
?>