<?php
// Check if we are running on local XAMPP or production
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1' || strpos($_SERVER['DOCUMENT_ROOT'], 'xampp') !== false) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'infinity_computer');
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u211084505_infinity');
    define('DB_PASS', 'Host@5341');
    define('DB_NAME', 'u211084505_infinity');
}

// Create connection without DB first to auto-create it if it doesn't exist
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    throw new Exception("Database Connection failed: " . $conn->connect_error);
}

// Auto-create database if running locally
if (DB_USER === 'root') {
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
}

// Select the database
if (!$conn->select_db(DB_NAME)) {
    throw new Exception("Failed to select database: " . $conn->error);
}

// Set charset to utf8mb4 for proper character handling
$conn->set_charset("utf8mb4");
