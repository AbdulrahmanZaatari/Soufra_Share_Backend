<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getOrder($_GET['id']);
        } else if (isset($_GET['buyer_id'])) {
            getOrdersByBuyer($_GET['buyer_id']);
        } else if (isset($_GET['seller_id'])) {
            getOrdersBySeller($_GET['seller_id']);
        } else {
            getOrders();
        }
        break;
    case 'POST':
        createOrder();
        break;
    case 'PUT':
        updateOrder();
        break;
    case 'DELETE':
        deleteOrder();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getOrders() {
    global $conn;
    $sql = "SELECT * FROM Orders";
    $result = $conn->query($sql);
    $orders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    echo json_encode($orders);
}

function getOrdersByBuyer($buyer_id) {
    global $conn;
    $sql = "SELECT * FROM Orders WHERE buyer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $buyer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    echo json_encode($orders);
}

function getOrdersBySeller($seller_id) {
    global $conn;
    $sql = "SELECT * FROM Orders WHERE seller_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    echo json_encode($orders);
}

function getOrder($id) {
    global $conn;
    $sql = "SELECT * FROM Orders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Order not found']);
    }
}

function createOrder() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $buyer_id = $data->buyer_id;
    $seller_id = $data->seller_id;
    $total_price = $data->total_price;
    $order_date = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Orders (buyer_id, seller_id, total_price, order_date) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iids", $buyer_id, $seller_id, $total_price, $order_date);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Order created successfully', 'order_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating order: ' . $stmt->error]);
    }
}

function updateOrder() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $order_id = $data->order_id;
    $buyer_id = $data->buyer_id ?? null;
    $seller_id = $data->seller_id ?? null;
    $total_price = $data->total_price ?? null;
    $order_date = $data->order_date ?? null;

    $sql = "UPDATE Orders SET buyer_id=?, seller_id=?, total_price=?, order_date=? WHERE order_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iidsi", $buyer_id, $seller_id, $total_price, $order_date, $order_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Order updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating order: ' . $stmt->error]);
    }
}

function deleteOrder() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $order_id = $data->order_id;

    $sql = "DELETE FROM Orders WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Order deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting order: ' . $stmt->error]);
    }
}

$conn->close();
?>