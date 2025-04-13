<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require 'connection.php';
    header('Content-Type: application/json');

    $buyer_id = $_POST['buyer_id'];
    $seller_id = $_POST['seller_id'];
    $total_price = $_POST['total_price'];
    $order_items_json = $_POST['order_items'];
    $order_date = date("Y-m-d");

    if (empty($buyer_id) || empty($seller_id) || empty($total_price) || empty($order_items_json)) {
        $response = array("status" => "error", "message" => "Missing required order details.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $order_items = json_decode($order_items_json, true);
    if ($order_items === null || !is_array($order_items)) {
        $response = array("status" => "error", "message" => "Invalid order items format.");
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $conn->begin_transaction();
    $order_id = null;

    try {
        // 1. Insert into the orders table
        $sql_insert_order = "INSERT INTO orders (buyer_id, seller_id, total_price, order_date) VALUES (?, ?, ?, ?)";
        $stmt_insert_order = $conn->prepare($sql_insert_order);
        $stmt_insert_order->bind_param("iids", $buyer_id, $seller_id, $total_price, $order_date);
        if ($stmt_insert_order->execute()) {
            $order_id = $conn->insert_id;
        } else {
            throw new Exception("Error creating order: " . $stmt_insert_order->error);
        }
        $stmt_insert_order->close();

        // 2. Insert into the orderitems table
        $sql_insert_order_item = "INSERT INTO orderitems (order_id, meal_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt_insert_order_item = $conn->prepare($sql_insert_order_item);
        foreach ($order_items as $item) {
            // Debugging Test 2: Assign to local variables
            $mealId = $item['meal_id'];
            $quantity = $item['quantity'];
            $priceValue = (double) $item['price'];
            error_log("Price before bind (local var): " . $priceValue, 0);
            $stmt_insert_order_item->bind_param("iiid", $order_id, $mealId, $quantity, $priceValue);

            if (!$stmt_insert_order_item->execute()) {
                throw new Exception("Error adding item to order: " . $stmt_insert_order_item->error);
            }
        }
        $stmt_insert_order_item->close();

        // 3. Update daily_sales table
        $sql_select_daily_sales = "SELECT id, total_sales, total_orders FROM dailysales WHERE seller_id = ? AND sale_date = ?";
        $stmt_select_daily_sales = $conn->prepare($sql_select_daily_sales);
        $stmt_select_daily_sales->bind_param("is", $seller_id, $order_date);
        $stmt_select_daily_sales->execute();
        $result_daily_sales = $stmt_select_daily_sales->get_result();

        if ($result_daily_sales->num_rows > 0) {
            $row_daily_sales = $result_daily_sales->fetch_assoc();
            $new_total_sales = $row_daily_sales['total_sales'] + $total_price;
            $new_total_orders = $row_daily_sales['total_orders'] + 1;
            $sql_update_daily_sales = "UPDATE dailysales SET total_sales = ?, total_orders = ? WHERE id = ?";
            $stmt_update_daily_sales = $conn->prepare($sql_update_daily_sales);
            $stmt_update_daily_sales->bind_param("ddi", $new_total_sales, $new_total_orders, $row_daily_sales['id']);
            $stmt_update_daily_sales->execute();
            $stmt_update_daily_sales->close();
        } else {
            $sql_insert_daily_sales = "INSERT INTO dailysales (seller_id, sale_date, total_sales, total_orders) VALUES (?, ?, ?, ?)";
            $stmt_insert_daily_sales = $conn->prepare($sql_insert_daily_sales);
            $stmt_insert_daily_sales->bind_param("isdi", $seller_id, $order_date, $total_price, 1);
            $stmt_insert_daily_sales->execute();
            $stmt_insert_daily_sales->close();
        }
        $stmt_select_daily_sales->close();

        // 4. Update sales_records table (similar logic to daily_sales)
        $sql_select_sales_records = "SELECT id, total_price FROM salesrecords WHERE seller_id = ? AND sale_date = ?";
        $stmt_select_sales_records = $conn->prepare($sql_select_sales_records);
        $stmt_select_sales_records->bind_param("is", $seller_id, $order_date);
        $stmt_select_sales_records->execute();
        $result_sales_records = $stmt_select_sales_records->get_result();

        $sales_record_id = null; // You might need to fetch the actual sales record ID here
        if ($result_sales_records->num_rows > 0) {
            $row_sales_records = $result_sales_records->fetch_assoc();
            $new_total_price_sales_records = $row_sales_records['total_price'] + $total_price;
            $sql_update_sales_records = "UPDATE salesrecords SET total_price = ? WHERE id = ?";
            $stmt_update_sales_records = $conn->prepare($sql_update_sales_records);
            $stmt_update_sales_records->bind_param("di", $new_total_price_sales_records, $row_sales_records['id']);
            $stmt_update_sales_records->execute();
            $stmt_update_sales_records->close();
            $sales_record_id = $row_sales_records['id']; 
        } else {
            $sql_insert_sales_records = "INSERT INTO salesrecords (seller_id, sale_date, total_price) VALUES (?, ?, ?)";
            $stmt_insert_sales_records = $conn->prepare($sql_insert_sales_records);
            $stmt_insert_sales_records->bind_param("isd", $seller_id, $order_date, $total_price);
            $stmt_insert_sales_records->execute();
            $sales_record_id = $conn->insert_id; // Get the ID of the newly inserted sales record
            $stmt_insert_sales_records->close();
        }
        $stmt_select_sales_records->close();

        // Insert into sales_records_orders table
        $insert_sales_records_orders_query = "INSERT INTO sales_records_orders (sales_record_id, order_id, order_date, order_total)
                                             VALUES (?, ?, ?, ?)";
        $stmt_insert_sales_records_orders = $conn->prepare($insert_sales_records_orders_query);
        $stmt_insert_sales_records_orders->bind_param('iisd', $sales_record_id, $order_id, $order_date, $total_price);
        $stmt_insert_sales_records_orders->execute();
        $stmt_insert_sales_records_orders->close();

        $daily_sales_query = "SELECT id FROM dailysales WHERE sale_date = ?";
        $stmt_daily_sales = $conn->prepare($daily_sales_query);
        $stmt_daily_sales->bind_param('s', $order_date);
        $stmt_daily_sales->execute();
        $daily_sales_result = $stmt_daily_sales->get_result();
        $stmt_daily_sales->close();

        $conn->commit();
        $response = array("status" => "success", "message" => "Order placed successfully!");

    } catch (Exception $e) {
        $conn->rollback();
        $response = array("status" => "error", "message" => "Order placement failed: " . $e->getMessage());
    }

    echo json_encode($response);
    $conn->close();
?>