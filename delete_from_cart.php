<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $cart_id = $_GET['cart_id'];

    if (empty($cart_id)) {
        $response = array("status" => "error", "message" => "Missing cart ID.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $sql_delete = "DELETE FROM cart WHERE cart_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $cart_id);

    if ($stmt_delete->execute()) {
        $response = array("status" => "success", "message" => "Item deleted from cart.");
        echo json_encode($response);
    } else {
        $response = array("status" => "error", "message" => "Error deleting item from cart: " . $stmt_delete->error);
        echo json_encode($response);
    }
    $stmt_delete->close();

    $conn->close();
?>