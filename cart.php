<?php
    require 'connection.php';
    header('Content-Type: application/json');
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $user_id = $_POST['user_id'];
    $meal_id = $_POST['meal_id'];
    $quantity_to_add = $_POST['quantity'];

    if (empty($user_id) || empty($meal_id) || empty($quantity_to_add)) {
        $response = array("status" => "error", "message" => "Missing required parameters.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Check if the meal is already in the user's cart
    $sql_check = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND meal_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ii", $user_id, $meal_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Meal is already in the cart, update the quantity
        $row = $result_check->fetch_assoc();
        $existing_quantity = $row['quantity'];
        $cart_id = $row['cart_id'];
        $new_quantity = $existing_quantity + $quantity_to_add;

        $sql_update = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $new_quantity, $cart_id);

        if ($stmt_update->execute()) {
            // Quantity in cart updated successfully, now decrease meal quantity
            $sql_decrease_meal = "UPDATE meals SET quantity = quantity - ? WHERE meal_id = ? AND quantity >= ?";
            $stmt_decrease_meal = $conn->prepare($sql_decrease_meal);
            $stmt_decrease_meal->bind_param("iii", $quantity_to_add, $meal_id, $quantity_to_add);

            if ($stmt_decrease_meal->execute()) {
                if ($stmt_decrease_meal->affected_rows > 0) {
                    $response = array("status" => "success", "message" => "Cart updated and meal quantity decreased.");
                } else {
                    $response = array("status" => "error", "message" => "Error decreasing meal quantity or insufficient stock.");
                }
            } else {
                $response = array("status" => "error", "message" => "Error decreasing meal quantity: " . $stmt_decrease_meal->error);
            }
            $stmt_decrease_meal->close();

        } else {
            $response = array("status" => "error", "message" => "Error updating cart: " . $stmt_update->error);
        }
        $stmt_update->close();

    } else {
        $sql_insert = "INSERT INTO cart (user_id, meal_id, quantity, added_at) VALUES (?, ?, ?, NOW())";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iii", $user_id, $meal_id, $quantity_to_add);

        if ($stmt_insert->execute()) {
            $sql_decrease_meal = "UPDATE meals SET quantity = quantity - ? WHERE meal_id = ? AND quantity >= ?";
            $stmt_decrease_meal = $conn->prepare($sql_decrease_meal);
            $stmt_decrease_meal->bind_param("iii", $quantity_to_add, $meal_id, $quantity_to_add);

            if ($stmt_decrease_meal->execute()) {
                if ($stmt_decrease_meal->affected_rows > 0) {
                    $response = array("status" => "success", "message" => "Meal added to cart and meal quantity decreased.");
                } else {
                    $response = array("status" => "error", "message" => "Error decreasing meal quantity or insufficient stock.");
                }
            } else {
                $response = array("status" => "error", "message" => "Error decreasing meal quantity: " . $stmt_decrease_meal->error);
            }
            $stmt_decrease_meal->close();

        } else {
            $response = array("status" => "error", "message" => "Error adding meal to cart: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    }

    $stmt_check->close();
    $conn->close();
?>