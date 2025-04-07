<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getSalesRecordOrder($_GET['id']);
        } elseif (isset($_GET['sales_record_id'])) {
            getSalesRecordOrdersBySalesRecord($_GET['sales_record_id']);
        } elseif (isset($_GET['order_id'])) {
            getSalesRecordOrdersByOrder($_GET['order_id']);
        } else {
            getSalesRecordOrders();
        }
        break;
    case 'POST':
        createSalesRecordOrder();
        break;
    case 'PUT':
        updateSalesRecordOrder();
        break;
    case 'DELETE':
        deleteSalesRecordOrder();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getSalesRecordOrders() {
    global $conn;
    $sql = "SELECT * FROM SalesRecordsOrders";
    $result = $conn->query($sql);
    $salesRecordOrders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $salesRecordOrders[] = $row;
        }
    }
    echo json_encode($salesRecordOrders);
}

function getSalesRecordOrdersBySalesRecord($sales_record_id) {
    global $conn;
    $sql = "SELECT * FROM SalesRecordsOrders WHERE sales_record_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sales_record_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $salesRecordOrders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $salesRecordOrders[] = $row;
        }
    }
    echo json_encode($salesRecordOrders);
}

function getSalesRecordOrdersByOrder($order_id) {
    global $conn;
    $sql = "SELECT * FROM SalesRecordsOrders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $salesRecordOrders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $salesRecordOrders[] = $row;
        }
    }
    echo json_encode($salesRecordOrders);
}

function getSalesRecordOrder($id) {
    global $conn;
    $sql = "SELECT * FROM SalesRecordsOrders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Sales record order not found']);
    }
}

function createSalesRecordOrder() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $sales_record_id = $data->sales_record_id;
    $order_id = $data->order_id;
    $order_date = $data->order_date;
    $order_total = $data->order_total;

    $sql = "INSERT INTO SalesRecordsOrders (sales_record_id, order_id, order_date, order_total) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $sales_record_id, $order_id, $order_date, $order_total);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Sales record order created successfully', 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating sales record order: ' . $stmt->error]);
    }
}

function updateSalesRecordOrder() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;
    $sales_record_id = $data->sales_record_id ?? null;
    $order_id = $data->order_id ?? null;
    $order_date = $data->order_date ?? null;
    $order_total = $data->order_total ?? null;

    $sql = "UPDATE SalesRecordsOrders SET sales_record_id=?, order_id=?, order_date=?, order_total=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissi", $sales_record_id, $order_id, $order_date, $order_total, $id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Sales record order updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating sales record order: ' . $stmt->error]);
    }
}

function deleteSalesRecordOrder() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $id = $data->id;

    $sql = "DELETE FROM SalesRecordsOrders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Sales record order deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting sales record order: ' . $stmt->error]);
    }
}

$conn->close();
?>