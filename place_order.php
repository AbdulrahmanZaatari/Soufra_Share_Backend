<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('log_errors', 1);

require 'connection.php'; 
header('Content-Type: application/json');

$buyer_id = $_POST['buyer_id'] ?? null;
$seller_id = $_POST['seller_id'] ?? null;
$total_price = $_POST['total_price'] ?? null;
$order_items_json = $_POST['order_items'] ?? null;
$order_date = date("Y-m-d");

if (empty($buyer_id) || empty($seller_id) || $total_price === null || empty($order_items_json)) {
    $response = array("status" => "error", "message" => "Missing required order details.");
    echo json_encode($response);
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    exit();
}

$order_items = json_decode($order_items_json, true);
if ($order_items === null || !is_array($order_items) || empty($order_items)) {
    $response = array("status" => "error", "message" => "Invalid or empty order items format.");
    echo json_encode($response);
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
    exit();
}

$order_id = null;
$buyer_email = null;
$order_summary = "Order Summary:\n\n";

$conn->begin_transaction();

try {
    $sql_fetch_buyer_email = "SELECT email FROM users WHERE user_id = ?";
    $stmt_fetch_buyer_email = $conn->prepare($sql_fetch_buyer_email);
    if ($stmt_fetch_buyer_email) {
        $stmt_fetch_buyer_email->bind_param("i", $buyer_id);
        if ($stmt_fetch_buyer_email->execute()) {
            $result_buyer_email = $stmt_fetch_buyer_email->get_result();
            if ($result_buyer_email->num_rows > 0) {
                $row_buyer_email = $result_buyer_email->fetch_assoc();
                $buyer_email = $row_buyer_email['email'];
                error_log("Buyer email found: " . $buyer_email);
            } else {
                error_log("Buyer email not found for buyer ID: " . $buyer_id);
            }
        } else {
            error_log("Error executing fetch buyer email query: " . $stmt_fetch_buyer_email->error);
        }
        $stmt_fetch_buyer_email->close();
    } else {
         error_log("Error preparing fetch buyer email statement: " . $conn->error);
    }

    $sql_insert_order = "INSERT INTO orders (buyer_id, seller_id, total_price, order_date) VALUES (?, ?, ?, ?)";
    $stmt_insert_order = $conn->prepare($sql_insert_order);
    if (!$stmt_insert_order) throw new Exception("Prepare failed (orders): " . $conn->error);
    $stmt_insert_order->bind_param("iids", $buyer_id, $seller_id, $total_price, $order_date);
    if ($stmt_insert_order->execute()) {
        $order_id = $conn->insert_id;
        error_log("Order created successfully. Order ID: " . $order_id);
    } else {
        throw new Exception("Error creating order: " . $stmt_insert_order->error);
    }
    $stmt_insert_order->close();

    $sql_insert_order_item = "INSERT INTO orderitems (order_id, meal_id, quantity, price) VALUES (?, ?, ?, ?)";
    $stmt_insert_order_item = $conn->prepare($sql_insert_order_item);
    if (!$stmt_insert_order_item) throw new Exception("Prepare failed (orderitems): " . $conn->error);

    $sql_fetch_meal_name = "SELECT name FROM meals WHERE meal_id = ?";
    $stmt_fetch_meal_name = $conn->prepare($sql_fetch_meal_name);
    if (!$stmt_fetch_meal_name) throw new Exception("Prepare failed (meals name): " . $conn->error);

    foreach ($order_items as $item) {
        $mealId = $item['meal_id'];
        $quantity = $item['quantity'];
        $priceValue = (double) $item['price'];

        $stmt_insert_order_item->bind_param("iiid", $order_id, $mealId, $quantity, $priceValue);
        if (!$stmt_insert_order_item->execute()) {
            throw new Exception("Error adding item (MealID: $mealId) to order: " . $stmt_insert_order_item->error);
        }

        $meal_name = "Meal ID: " . $mealId;
        $stmt_fetch_meal_name->bind_param("i", $mealId);
        if ($stmt_fetch_meal_name->execute()) {
            $result_meal_name = $stmt_fetch_meal_name->get_result();
            if ($result_meal_name->num_rows > 0) {
                $row_meal_name = $result_meal_name->fetch_assoc();
                $meal_name = $row_meal_name['name'];
            }
        } else {
             error_log("Error fetching meal name for MealID $mealId: " . $stmt_fetch_meal_name->error);
        }
        $order_summary .= $quantity . " x " . $meal_name . " (@ $" . number_format($priceValue, 2) . " each)\n";
    }
    $stmt_insert_order_item->close();
    $stmt_fetch_meal_name->close();
    $order_summary .= "\nTotal Price: $" . number_format($total_price, 2);

    $sql_select_daily_sales = "SELECT id, total_sales, total_orders FROM dailysales WHERE seller_id = ? AND sale_date = ?";
    $stmt_select_daily_sales = $conn->prepare($sql_select_daily_sales);
    if (!$stmt_select_daily_sales) throw new Exception("Prepare failed (select dailysales): " . $conn->error);
    $stmt_select_daily_sales->bind_param("is", $seller_id, $order_date);
    $stmt_select_daily_sales->execute();
    $result_daily_sales = $stmt_select_daily_sales->get_result();

    if ($result_daily_sales && $result_daily_sales->num_rows > 0) {
        $row_daily_sales = $result_daily_sales->fetch_assoc();
        $daily_sales_id = $row_daily_sales['id'];
        $new_total_sales = $row_daily_sales['total_sales'] + $total_price;
        $new_total_orders = $row_daily_sales['total_orders'] + 1;
        $sql_update_daily_sales = "UPDATE dailysales SET total_sales = ?, total_orders = ? WHERE id = ?";
        $stmt_update_daily_sales = $conn->prepare($sql_update_daily_sales);
        if (!$stmt_update_daily_sales) throw new Exception("Prepare failed (update dailysales): " . $conn->error);
        error_log("Attempting to UPDATE dailysales: ID=" . $daily_sales_id . ", NewTotalSales=" . $new_total_sales . ", NewTotalOrders=" . $new_total_orders);
        $stmt_update_daily_sales->bind_param("ddi", $new_total_sales, $new_total_orders, $daily_sales_id);
        if (!$stmt_update_daily_sales->execute()) {
             error_log("ERROR executing dailysales UPDATE for ID " . $daily_sales_id . ": " . $stmt_update_daily_sales->error);
             throw new Exception("Error updating daily sales: " . $stmt_update_daily_sales->error);
        } else {
             error_log("SUCCESS updating dailysales for ID " . $daily_sales_id);
        }
        $stmt_update_daily_sales->close();
    } else {
        $sql_insert_daily_sales = "INSERT INTO dailysales (seller_id, sale_date, total_sales, total_orders) VALUES (?, ?, ?, ?)";
        $stmt_insert_daily_sales = $conn->prepare($sql_insert_daily_sales);
          if (!$stmt_insert_daily_sales) throw new Exception("Prepare failed (insert dailysales): " . $conn->error);
        $initial_order_count = 1;
        $stmt_insert_daily_sales->bind_param("isdi", $seller_id, $order_date, $total_price, $initial_order_count);
        if (!$stmt_insert_daily_sales->execute()) {
            throw new Exception("Error inserting daily sales: " . $stmt_insert_daily_sales->error);
        } else {
             error_log("SUCCESS inserting new dailysales record for SellerID " . $seller_id . " Date " . $order_date);
        }
        $stmt_insert_daily_sales->close();
    }
    $stmt_select_daily_sales->close();


    $sql_select_sales_records = "SELECT id, total_price FROM salesrecords WHERE seller_id = ? AND sale_date = ?";
    $stmt_select_sales_records = $conn->prepare($sql_select_sales_records);
    if (!$stmt_select_sales_records) throw new Exception("Prepare failed (select salesrecords): " . $conn->error);
    $stmt_select_sales_records->bind_param("is", $seller_id, $order_date);
    $stmt_select_sales_records->execute();
    $result_sales_records = $stmt_select_sales_records->get_result();

    $sales_record_id = null;
    if ($result_sales_records && $result_sales_records->num_rows > 0) {
        $row_sales_records = $result_sales_records->fetch_assoc();
        $sales_record_id = $row_sales_records['id'];
        $new_total_price_sales_records = $row_sales_records['total_price'] + $total_price;
        $sql_update_sales_records = "UPDATE salesrecords SET total_price = ? WHERE id = ?";
        $stmt_update_sales_records = $conn->prepare($sql_update_sales_records);
          if (!$stmt_update_sales_records) throw new Exception("Prepare failed (update salesrecords): " . $conn->error);
        error_log("Attempting to UPDATE salesrecords: ID=" . $sales_record_id . ", NewTotalPrice=" . $new_total_price_sales_records);
        $stmt_update_sales_records->bind_param("di", $new_total_price_sales_records, $sales_record_id);
        if (!$stmt_update_sales_records->execute()) {
            error_log("ERROR executing salesrecords UPDATE for ID " . $sales_record_id . ": " . $stmt_update_sales_records->error);
            throw new Exception("Error updating sales records: " . $stmt_update_sales_records->error);
        } else {
            error_log("SUCCESS updating salesrecords for ID " . $sales_record_id);
        }
        $stmt_update_sales_records->close();
    } else {
        $sql_insert_sales_records = "INSERT INTO salesrecords (seller_id, sale_date, total_price) VALUES (?, ?, ?)";
        $stmt_insert_sales_records = $conn->prepare($sql_insert_sales_records);
        if (!$stmt_insert_sales_records) throw new Exception("Prepare failed (insert salesrecords): " . $conn->error);
        $stmt_insert_sales_records->bind_param("isd", $seller_id, $order_date, $total_price);
        if (!$stmt_insert_sales_records->execute()) {
            throw new Exception("Error inserting sales records: " . $stmt_insert_sales_records->error);
        } else {
             $sales_record_id = $conn->insert_id;
             error_log("SUCCESS inserting new salesrecords record. ID: " . $sales_record_id);
        }
        $stmt_insert_sales_records->close();
    }
    $stmt_select_sales_records->close();

    if ($sales_record_id === null) {
         throw new Exception("Failed to determine sales_record_id.");
    }

    $insert_sales_records_orders_query = "INSERT INTO salesrecordsorders (sales_record_id, order_id, order_date, order_total) VALUES (?, ?, ?, ?)";
    $stmt_insert_sales_records_orders = $conn->prepare($insert_sales_records_orders_query);
    if (!$stmt_insert_sales_records_orders) throw new Exception("Prepare failed (salesrecordsorders): " . $conn->error);
    $stmt_insert_sales_records_orders->bind_param('iisd', $sales_record_id, $order_id, $order_date, $total_price);
    if (!$stmt_insert_sales_records_orders->execute()) {
         throw new Exception("Error inserting into salesrecordsorders: " . $stmt_insert_sales_records_orders->error);
    }
    $stmt_insert_sales_records_orders->close();


    $sql_delete_cart_items = "DELETE FROM cart WHERE user_id = ?";
    $stmt_delete_cart_items = $conn->prepare($sql_delete_cart_items);
      if (!$stmt_delete_cart_items) throw new Exception("Prepare failed (delete cart): " . $conn->error);
    $stmt_delete_cart_items->bind_param("i", $buyer_id);
    if (!$stmt_delete_cart_items->execute()) {
         error_log("Warning: Failed to delete cart items for user ID " . $buyer_id . ": " . $stmt_delete_cart_items->error);
    } else {
         error_log("Deleted cart items for User ID: " . $buyer_id);
    }
    $stmt_delete_cart_items->close();


    $conn->commit();
    $response = array("status" => "success", "message" => "Order placed successfully! Order ID: " . $order_id);
    error_log("Transaction committed successfully for Order ID: " . $order_id);

    if ($buyer_email) {
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USERNAME']; 
            $mail->Password   = $_ENV['SMTP_PASSWORD']; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($_ENV['SMTP_USERNAME'], 'Soufra Share Orders'); 
            $mail->addAddress($buyer_email);

            $mail->isHTML(false);
            $mail->Subject = "Your Soufra Share Order Confirmation (ID: " . $order_id . ")";
            $mail->Body    = "Dear Customer,\n\nThank you for your order (ID: " . $order_id . ")!\n\n"
                                   . $order_summary
                                   . "\n\nYour order will be processed shortly.\n\nBest regards,\nThe Soufra Share Team";

            $mail->send();
            error_log('PHPMailer: Confirmation email sent successfully to: ' . $buyer_email . ' for Order ID: ' . $order_id);
        } catch (Exception $e) {
            error_log("PHPMailer: Message could not be sent. Mailer Error: {$mail->ErrorInfo} for Order ID: " . $order_id);
        }
    } else {
         error_log("No buyer email found, skipping email confirmation for Order ID: " . $order_id);
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction rolled back due to error: " . $e->getMessage());
    $response = array("status" => "error", "message" => "Order placement failed: " . $e->getMessage());

} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($response);
?>