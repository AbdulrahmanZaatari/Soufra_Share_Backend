<?php
require('fpdf186/fpdf.php'); 
include('connection.php');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    die("Error: Order ID not provided.");
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$sql_order = "SELECT o.order_date, o.total_price, o.buyer_id
              FROM orders o
              WHERE o.order_id = ?";
$stmt_order = $conn->prepare($sql_order);
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();
$order = $result_order->fetch_assoc();

if (!$order) {
    die("Error: Order not found.");
}

// Fetch buyer's name from the users table
$buyer_id = $order['buyer_id'];
$sql_buyer = "SELECT full_name FROM Users WHERE user_id = ?";
$stmt_buyer = $conn->prepare($sql_buyer);
$stmt_buyer->bind_param("i", $buyer_id);
$stmt_buyer->execute();
$result_buyer = $stmt_buyer->get_result();
$buyer = $result_buyer->fetch_assoc();
$buyer_name = $buyer ? $buyer['full_name'] : 'N/A';

// Fetch order items
$sql_items = "
    SELECT m.name AS meal_name, oi.quantity, oi.price
    FROM orderitems oi
    JOIN meals m ON oi.meal_id = m.meal_id
    WHERE oi.order_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

// Generate the PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Header
$pdf->Cell(0, 10, 'Order Receipt', 0, 1, 'C');
$pdf->Ln(10);

// Order Information
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Order ID:', 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $order_id, 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Order Date:', 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $order['order_date'], 0, 1);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 10, 'Buyer Name:', 0);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, $buyer_name, 0, 1);
$pdf->Ln(10);

// Order Items Table Header
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(70, 10, 'Meal Name', 1);
$pdf->Cell(30, 10, 'Quantity', 1, 0, 'C');
$pdf->Cell(40, 10, 'Price', 1, 0, 'R');
$pdf->Cell(40, 10, 'Total', 1, 1, 'R');

$pdf->SetFont('Arial', '', 12);
while ($item = $result_items->fetch_assoc()) {
    $pdf->Cell(70, 10, $item['meal_name'], 1);
    $pdf->Cell(30, 10, $item['quantity'], 1, 0, 'C');
    $pdf->Cell(40, 10, '$' . number_format($item['price'], 2), 1, 0, 'R');
    $pdf->Cell(40, 10, '$' . number_format($item['quantity'] * $item['price'], 2), 1, 1, 'R');
}
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 10, 'Total Amount:', 0, 0, 'R');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, '$' . number_format($order['total_price'], 2), 0, 1, 'R');

$pdf->SetY(-15);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 10, 'Receipt generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');


header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="receipt_order_' . $order_id . '.pdf"');
$pdf->Output();

$stmt_order->close();
$stmt_items->close();
$stmt_buyer->close();
$conn->close();
?>