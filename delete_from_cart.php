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

    // First, get the meal_id and quantity of the item being deleted
    $sql_select = "SELECT meal_id, quantity FROM cart WHERE cart_id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $cart_id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();

    if ($result_select->num_rows > 0) {
        $row = $result_select->fetch_assoc();
        $meal_id_to_update = $row['meal_id'];
        $quantity_to_increase = $row['quantity'];

        // Delete the item from the cart
        $sql_delete = "DELETE FROM cart WHERE cart_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $cart_id);

        if ($stmt_delete->execute()) {
            // Optionally, increase the quantity of the meal in the meals table
            $sql_update_meal = "UPDATE meals SET quantity = quantity + ? WHERE meal_id = ?";
            $stmt_update_meal = $conn->prepare($sql_update_meal);
            $stmt_update_meal->bind_param("ii", $quantity_to_increase, $meal_id_to_update);
            $stmt_update_meal->execute();
            $stmt_update_meal->close();

            $response = array("status" => "success", "message" => "Item deleted from cart.");
            echo json_encode($response);
        } else {
            $response = array("status" => "error", "message" => "Error deleting item from cart: " . $stmt_delete->error);
            echo json_encode($response);
        }
        $stmt_delete->close();
    } else {
        $response = array("status" => "error", "message" => "Cart item not found.");
        echo json_encode($response);
    }

    $stmt_select->close();
    $conn->close();
?>