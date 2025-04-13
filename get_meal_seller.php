<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $meal_id = $_GET['meal_id'];

    if (empty($meal_id)) {
        $response = array("status" => "error", "message" => "Missing meal ID.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $sql = "SELECT user_id AS seller_id FROM meals WHERE meal_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meal_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode($row); 
        } else {
            $response = array("status" => "error", "message" => "Meal not found.");
            echo json_encode($response);
        }
    } else {
        $response = array("status" => "error", "message" => "Error fetching seller ID: " . $stmt->error);
        echo json_encode($response);
    }

    $stmt->close();
    $conn->close();
?>