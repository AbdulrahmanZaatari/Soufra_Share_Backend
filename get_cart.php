<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $user_id = $_GET['user_id'];

    if (empty($user_id)) {
        $response = array("status" => "error", "message" => "Missing user ID.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $sql = "SELECT
                c.cart_id,
                c.meal_id,
                c.quantity AS cart_quantity,
                m.name AS meal_name,
                m.image_url AS meal_image_url,
                m.price AS meal_price
            FROM cart c
            INNER JOIN meals m ON c.meal_id = m.meal_id
            WHERE c.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $cart_items = array();
        while ($row = $result->fetch_assoc()) {
            $cart_items[] = array(
                "cartId" => $row['cart_id'],
                "mealId" => $row['meal_id'],
                "mealName" => $row['meal_name'],
                "imageUrl" => $row['meal_image_url'],
                "quantity" => $row['cart_quantity'],
                "price" => $row['meal_price']
            );
        }
        echo json_encode($cart_items);
    } else {
        $response = array("status" => "error", "message" => "Error fetching cart data: " . $stmt->error);
        echo json_encode($response);
    }

    $stmt->close();
    $conn->close();
?>