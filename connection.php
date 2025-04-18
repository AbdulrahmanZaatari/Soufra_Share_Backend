<?php
$servername = "localhost";
$username = "root"; 
$password = "Abedamal123!"; 
$dbname = "soufra_share";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>