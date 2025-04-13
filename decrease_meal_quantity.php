<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $meal_id = $_GET['meal_id'];
    $quantity_ordered = $_GET['quantity'];

    if (empty($meal_id) || empty($quantity_ordered)) {
        $response = array("status" => "error", "message" => "Missing meal ID or quantity.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $sql = "UPDATE meals SET quantity = quantity - ? WHERE meal_id = ? AND quantity >= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $quantity_ordered, $meal_id, $quantity_ordered);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response = array("status" => "success", "message" => "Quantity decreased successfully.");
        } else {
            $response = array("status" => "error", "message" => "Could not decrease quantity. Either meal not found or insufficient quantity.");
        }
        echo json_encode($response);
    } else {
        $response = array("status" => "error", "message" => "Error decreasing quantity: " . $stmt->error);
        echo json_encode($response);
    }

    $stmt->close();
    $conn->close();
?>