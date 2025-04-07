<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getSalesRecord($_GET['id']);
        } else {
            getSalesRecords();
        }
        break;
    case 'POST':
        createSalesRecord();
        break;
    case 'PUT':
        updateSalesRecord();
        break;
    case 'DELETE':
        deleteSalesRecord();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getSalesRecords() {
    global $conn;
    $sql = "SELECT * FROM SalesRecords";
    $result = $conn->query($sql);
    $salesRecords = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $salesRecords[] = $row;
        }
    }
    echo json_encode($salesRecords);
}

function getSalesRecord($id) {
    global $conn;
    $sql = "SELECT * FROM SalesRecords WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Sales record not found']);
    }
}

function createSalesRecord() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $sale_date = $data->sale_date;
    $total_price = $data->total_price ?? 0.00;

    $sql = "INSERT INTO SalesRecords (sale_date, total_price) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $sale_date, $total_price);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Sales record created successfully', 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating sales record: ' . $stmt->error]);
    }
}

function updateSalesRecord() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;
    $sale_date = $data->sale_date ?? null;
    $total_price = $data->total_price ?? null;

    $sql = "UPDATE SalesRecords SET sale_date=?, total_price=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdi", $sale_date, $total_price, $id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Sales record updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating sales record: ' . $stmt->error]);
    }
}

function deleteSalesRecord() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;

    $sql = "DELETE FROM SalesRecords WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Sales record deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting sales record: ' . $stmt->error]);
    }
}

$conn->close();
?>