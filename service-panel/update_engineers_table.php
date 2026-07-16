<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/config/db.php';

$query = "ALTER TABLE engineers ADD COLUMN position VARCHAR(50) DEFAULT 'staff' AFTER email";
if ($conn->query($query) === TRUE) {
    echo "Column 'position' added successfully.";
} else {
    if ($conn->errno == 1060) {
        echo "Column 'position' already exists.";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>
