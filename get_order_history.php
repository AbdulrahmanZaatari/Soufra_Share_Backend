<?php
session_start();
include("./connection.php");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    echo json_encode(array("error" => "Invalid request method"));
    exit();
}

// Get the user ID from the POST request
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400); 
    echo json_encode(array("error" => "Invalid user ID"));
    exit();
}

// Fetch order history for the given buyer ID
$sql_orders = "SELECT order_id, total_price, order_date FROM orders WHERE buyer_id = ?";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();

$orders = array();
while ($row = $result_orders->fetch_assoc()) {
    $orders[] = $row;
}

// Set response content type to JSON
header('Content-Type: application/json');
echo json_encode($orders);

$stmt_orders->close();
$conn->close();
?>