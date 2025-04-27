<?php
session_start();
include('connection.php');

$logFile = 'sales_dates_log.txt';


$logMessage = date("Y-m-d H:i:s") . " - Request received for sales dates.\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

if ($conn->connect_error) {
    $errorMessage = "Database Connection Failed: " . $conn->connect_error . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    die(json_encode(array("error" => "Database connection error")));
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); 
    $errorMessage = "Invalid request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    echo json_encode(array("error" => "Invalid request method"));
    exit();
}


$seller_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$logMessage = date("Y-m-d H:i:s") . " - Received user_id: " . $seller_id . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

if ($seller_id <= 0) {
    http_response_code(400); 
    $errorMessage = "Invalid user ID: " . $seller_id . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    echo json_encode(array("error" => "Invalid user ID"));
    exit();
}

$sql = "SELECT id, sale_date, total_sales FROM dailysales WHERE seller_id = ? ORDER BY sale_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
if (!$stmt->execute()) {
    $errorMessage = "SQL Error: " . $stmt->error . "\n";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - ERROR: " . $errorMessage, FILE_APPEND);
    echo json_encode(array("error" => "Error fetching sales data from database"));
    $stmt->close();
    $conn->close();
    exit();
}
$result = $stmt->get_result();

$salesDates = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $salesDates[] = $row;
    }
}

header('Content-Type: application/json');
$jsonResponse = json_encode($salesDates);
$logMessage = date("Y-m-d H:i:s") . " - JSON Response: " . $jsonResponse . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);
echo $jsonResponse;

$stmt->close();
$conn->close();
?>