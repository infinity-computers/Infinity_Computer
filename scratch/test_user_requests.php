<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/../service-panel/config/db.php';

try {
    echo "Checking engineers table...\n";
    $res = $conn->query("SELECT * FROM engineers");
    if (!$res) {
        echo "Error querying table: " . $conn->error . "\n";
    } else {
        echo "Row count: " . $res->num_rows . "\n";
        while ($row = $res->fetch_assoc()) {
            print_r($row);
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
