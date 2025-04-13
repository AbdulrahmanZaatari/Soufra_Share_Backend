<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $meal_id = $_GET['meal_id'];
    $quantity_ordered = $_GET['quantity'];

    if (empty($meal_id) || empty($quantity_ordered)) {
        $response = array("available" => false, "message" => "Missing meal ID or quantity.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $sql = "SELECT quantity FROM meals WHERE meal_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meal_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $quantity_available = $row['quantity'];
            if ($quantity_ordered <= $quantity_available) {
                $response = array("available" => true, "quantity_available" => $quantity_available); 
            } else {
                $response = array("available" => false, "message" => "Quantity not available for meal ID: " . $meal_id, "quantity_available" => $quantity_available); // Include available quantity
            }
            echo json_encode($response);
        } else {
            $response = array("available" => false, "message" => "Meal not found.");
            echo json_encode($response);
        }
    } else {
        $response = array("available" => false, "message" => "Error checking quantity: " . $stmt->error);
        echo json_encode($response);
    }

    $stmt->close();
    $conn->close();
?>