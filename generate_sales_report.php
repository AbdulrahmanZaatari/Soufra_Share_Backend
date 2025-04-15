<?php
session_start();
require('fpdf186/fpdf.php');
include('connection.php');

$logFile = 'generate_report_log.txt';

// Log the request
$logMessage = date("Y-m-d H:i:s") . " - Request received to generate sales report.\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

if ($conn->connect_error) {
    $errorMessage = "Database Connection Failed: " . $conn->connect_error . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    die("Database connection failed.");
}

// Get the sales date from query parameters
$sale_date = isset($_GET['date']) ? $_GET['date'] : null;
$logMessage = date("Y-m-d H:i:s") . " - Received sale_date: " . $sale_date . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Get the seller ID from the session (assuming user is logged in)
$seller_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$logMessage = date("Y-m-d H:i:s") . " - Received seller_id (from session): " . $seller_id . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Ensure both sale_date and seller_id are available
if (!$sale_date || $seller_id <= 0) {
    $errorMessage = "Sales date or seller ID missing. Date: " . $sale_date . ", Seller ID: " . $seller_id . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    die("Sales date and seller ID are required.");
}

// Fetch sales summary for the given date and seller
$sql_summary = "
    SELECT
        total_sales,
        total_orders
    FROM dailysales
    WHERE sale_date = ? AND seller_id = ?";
$stmt_summary = $conn->prepare($sql_summary);
$stmt_summary->bind_param("si", $sale_date, $seller_id);
if (!$stmt_summary->execute()) {
    $errorMessage = "SQL Error (summary): " . $stmt_summary->error . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    die("Error fetching sales summary.");
}
$result_summary = $stmt_summary->get_result();
$summary = $result_summary->fetch_assoc();

$total_sales = $summary['total_sales'] ?? 0;
$total_orders = $summary['total_orders'] ?? 0;
$logMessage = date("Y-m-d H:i:s") . " - Sales Summary: Total Sales: " . $total_sales . ", Total Orders: " . $total_orders . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Fetch top 3 products (meals) sold by the seller on the day
$sql_top_meals = "
    SELECT
        m.name AS meal_name,
        SUM(oi.quantity) AS total_quantity
    FROM orderitems oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN meals m ON oi.meal_id = m.meal_id
    WHERE DATE(o.order_date) = ? AND o.seller_id = ?
    GROUP BY oi.meal_id
    ORDER BY total_quantity DESC
    LIMIT 3";
$stmt_top_meals = $conn->prepare($sql_top_meals);
$stmt_top_meals->bind_param("si", $sale_date, $seller_id);
if (!$stmt_top_meals->execute()) {
    $errorMessage = "SQL Error (top meals): " . $stmt_top_meals->error . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    die("Error fetching top meals.");
}
$result_top_meals = $stmt_top_meals->get_result();
$logMessage = date("Y-m-d H:i:s") . " - Top Meals fetched.\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Fetch individual order details for the given date and seller
$sql_details = "
    SELECT
        order_id,
        order_date,
        total_price
    FROM orders
    WHERE DATE(order_date) = ? AND seller_id = ?";
$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("si", $sale_date, $seller_id);
if (!$stmt_details->execute()) {
    $errorMessage = "SQL Error (order details): " . $stmt_details->error . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    die("Error fetching order details.");
}
$result_details = $stmt_details->get_result();
$logMessage = date("Y-m-d H:i:s") . " - Order details fetched.\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);


$pdf->Cell(0, 10, 'Soufra Share - Sales Report', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Ln(5);


$pdf->Cell(0, 10, 'Sales Date: ' . $sale_date, 0, 1);
$pdf->Cell(0, 10, 'Total Sales: $' . number_format($total_sales, 2), 0, 1);
$pdf->Cell(0, 10, 'Total Orders: ' . $total_orders, 0, 1);
$pdf->Ln(10);

// Top 3 Products (Meals) Sold Table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Top 3 Meals Sold:', 0, 1);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(80, 10, 'Meal Name', 1);
$pdf->Cell(40, 10, 'Quantity Sold', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);
while ($row = $result_top_meals->fetch_assoc()) {
    $pdf->Cell(80, 10, $row['meal_name'], 1);
    $pdf->Cell(40, 10, $row['total_quantity'], 1);
    $pdf->Ln();
}
$pdf->Ln(10);


$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Order ID', 1);
$pdf->Cell(60, 10, 'Order Date', 1);
$pdf->Cell(50, 10, 'Order Total', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);
while ($order_row = $result_details->fetch_assoc()) {
    $pdf->Cell(40, 10, $order_row['order_id'], 1);
    $pdf->Cell(60, 10, $order_row['order_date'], 1);
    $pdf->Cell(50, 10, '$' . number_format($order_row['total_price'], 2), 1);
    $pdf->Ln();


    $sql_order_items = "
        SELECT
            m.name AS meal_name,
            oi.quantity
        FROM orderitems oi
        JOIN meals m ON oi.meal_id = m.meal_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.order_id = ? AND o.seller_id = ?";
    $stmt_order_items = $conn->prepare($sql_order_items);
    $stmt_order_items->bind_param("ii", $order_row['order_id'], $seller_id);
    if (!$stmt_order_items->execute()) {
        $errorMessage = "SQL Error (order items): " . $stmt_order_items->error . "\n";
        file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
        die("Error fetching order items.");
    }
    $result_order_items = $stmt_order_items->get_result();


    $pdf->SetFont('Arial', 'I', 10);
    while ($item_row = $result_order_items->fetch_assoc()) {
        $pdf->Cell(40, 10, '', 0);
        $pdf->Cell(60, 10, $item_row['meal_name'], 1);
        $pdf->Cell(50, 10, 'Quantity: ' . $item_row['quantity'], 1);
        $pdf->Ln();
    }
    $pdf->Ln(5);
}


$file_name = "Sales_Report_$sale_date.pdf";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
$pdf->Output();
$logMessage = date("Y-m-d H:i:s") . " - Sales report PDF generated successfully.\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

$stmt_summary->close();
$stmt_top_meals->close();
$stmt_details->close();
$conn->close();
?>