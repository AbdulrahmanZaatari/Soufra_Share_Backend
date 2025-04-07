<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getOrderItem($_GET['id']);
        } elseif (isset($_GET['order_id'])) {
            getOrderItemsByOrder($_GET['order_id']);
        } else {
            getOrderItems();
        }
        break;
    case 'POST':
        createOrderItem();
        break;
    case 'PUT':
        updateOrderItem();
        break;
    case 'DELETE':
        deleteOrderItem();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getOrderItems() {
    global $conn;
    $sql = "SELECT * FROM OrderItems";
    $result = $conn->query($sql);
    $orderItems = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orderItems[] = $row;
        }
    }
    echo json_encode($orderItems);
}

function getOrderItemsByOrder($order_id) {
    global $conn;
    $sql = "SELECT * FROM OrderItems WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orderItems = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orderItems[] = $row;
        }
    }
    echo json_encode($orderItems);
}

function getOrderItem($id) {
    global $conn;
    $sql = "SELECT * FROM OrderItems WHERE order_item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Order item not found']);
    }
}

function createOrderItem() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $order_id = $data->order_id;
    $meal_id = $data->meal_id;
    $quantity = $data->quantity;
    $price = $data->price;

    $sql = "INSERT INTO OrderItems (order_id, meal_id, quantity, price) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiid", $order_id, $meal_id, $quantity, $price);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Order item created successfully', 'order_item_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating order item: ' . $stmt->error]);
    }
}

function updateOrderItem() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $order_item_id = $data->order_item_id;
    $order_id = $data->order_id ?? null;
    $meal_id = $data->meal_id ?? null;
    $quantity = $data->quantity ?? null;
    $price = $data->price ?? null;

    $sql = "UPDATE OrderItems SET order_id=?, meal_id=?, quantity=?, price=? WHERE order_item_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiidi", $order_id, $meal_id, $quantity, $price, $order_item_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Order item updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating order item: ' . $stmt->error]);
    }
}

function deleteOrderItem() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $order_item_id = $data->order_item_id;

    $sql = "DELETE FROM OrderItems WHERE order_item_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_item_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Order item deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting order item: ' . $stmt->error]);
    }
}

$conn->close();
?>