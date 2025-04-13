<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $user_id = $_POST['user_id'];
    $meal_id = $_POST['meal_id'];
    $quantity_to_add = $_POST['quantity'];

    error_log("cart.php: Received user_id = " . $user_id . ", meal_id = " . $meal_id . ", quantity = " . $quantity_to_add);

    if (empty($user_id) || empty($meal_id) || empty($quantity_to_add)) {
        $response = array("status" => "error", "message" => "Missing required parameters.");
        error_log("cart.php: Missing parameters - " . json_encode($response));
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Check the current quantity of the meal
    $sql_check_meal_qty = "SELECT quantity FROM meals WHERE meal_id = ?";
    $stmt_check_meal_qty = $conn->prepare($sql_check_meal_qty);
    $stmt_check_meal_qty->bind_param("i", $meal_id);
    $stmt_check_meal_qty->execute();
    $result_meal_qty = $stmt_check_meal_qty->get_result();

    if ($result_meal_qty->num_rows > 0) {
        $row_meal_qty = $result_meal_qty->fetch_assoc();
        $available_quantity = $row_meal_qty['quantity'];

        if ($quantity_to_add > $available_quantity) {
            $response = array("status" => "error", "message" => "Insufficient stock for this meal.");
            error_log("cart.php: Insufficient stock - Requested: " . $quantity_to_add . ", Available: " . $available_quantity);
            echo json_encode($response);
            $conn->close();
            exit();
        }
    } else {
        $response = array("status" => "error", "message" => "Meal not found.");
        error_log("cart.php: Meal not found - meal_id: " . $meal_id);
        echo json_encode($response);
        $conn->close();
        exit();
    }
    $stmt_check_meal_qty->close();

    // Check if the meal is already in the user's cart
    $sql_check_cart = "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND meal_id = ?";
    $stmt_check_cart = $conn->prepare($sql_check_cart);
    $stmt_check_cart->bind_param("ii", $user_id, $meal_id);
    error_log("cart.php: Check SQL: " . $sql_check_cart . " (user_id: " . $user_id . ", meal_id: " . $meal_id . ")");
    if (!$stmt_check_cart->execute()) {
        error_log("cart.php: Error executing check query - " . $stmt_check_cart->error);
    }
    $result_check_cart = $stmt_check_cart->get_result();
    error_log("cart.php: Number of rows in cart check result: " . $result_check_cart->num_rows);

    if ($result_check_cart->num_rows > 0) {
        // Meal is already in the cart, update the quantity
        $row = $result_check_cart->fetch_assoc();
        $existing_quantity = $row['quantity'];
        $cart_id = $row['cart_id'];
        $new_quantity = $existing_quantity + $quantity_to_add;

        // Re-check if the new quantity exceeds available stock
        if ($new_quantity > $available_quantity) {
            $response = array("status" => "error", "message" => "Insufficient stock for the updated quantity.");
            error_log("cart.php: Insufficient stock for updated quantity - Requested: " . $new_quantity . ", Available: " . $available_quantity);
            echo json_encode($response);
            $stmt_check_cart->close();
            $conn->close();
            exit();
        }

        $sql_update = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $new_quantity, $cart_id);
        error_log("cart.php: Update SQL: " . $sql_update . " (new_quantity: " . $new_quantity . ", cart_id: " . $cart_id . ")");

        if ($stmt_update->execute()) {
            error_log("cart.php: Cart updated successfully.");
            $response = array("status" => "success", "message" => "Cart updated successfully.");
            error_log("cart.php: Response (Success - Updated): " . json_encode($response));
        } else {
            $response = array("status" => "error", "message" => "Error updating cart: " . $stmt_update->error);
            error_log("cart.php: Error updating cart - " . $stmt_update->error . " SQL: " . $sql_update);
        }
        $stmt_update->close();

    } else {
        // Meal is not in the cart, insert a new item
        $sql_insert = "INSERT INTO cart (user_id, meal_id, quantity, added_at) VALUES (?, ?, ?, NOW())";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("iii", $user_id, $meal_id, $quantity_to_add);
        error_log("cart.php: Insert SQL: " . $sql_insert . " (user_id: " . $user_id . ", meal_id: " . $meal_id . ", quantity_to_add: " . $quantity_to_add . ")");

        if ($stmt_insert->execute()) {
            error_log("cart.php: Meal added to cart successfully.");
            $response = array("status" => "success", "message" => "Meal added to cart successfully.");
            error_log("cart.php: Response (Success - Inserted): " . json_encode($response));
        } else {
            $response = array("status" => "error", "message" => "Error adding meal to cart: " . $stmt_insert->error);
            error_log("cart.php: Error adding meal to cart - " . $stmt_insert->error . " SQL: " . $sql_insert);
        }
        $stmt_insert->close();
    }

    $stmt_check_cart->close();
    echo json_encode($response);
    $conn->close();
?>