<?php
// Configuration for local database
define('DB_HOST', 'localhost');
define('DB_USER', 'u211084505_infinity');
define('DB_PASS', 'Host@5341');
define('DB_NAME', 'u211084505_infinity');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper character handling
$conn->set_charset("utf8mb4");
?>
