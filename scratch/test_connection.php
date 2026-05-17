<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock server name for CLI mode so db.php matches local environment
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';

try {
    echo "Loading config/db.php...\n";
    require_once __DIR__ . '/../config/db.php';
    
    echo "DB Host: " . DB_HOST . "\n";
    echo "DB User: " . DB_USER . "\n";
    echo "DB Name: " . DB_NAME . "\n\n";

    echo "Fetching all tables in the active database:\n";
    $res = $conn->query("SHOW TABLES");
    if (!$res) {
        throw new Exception("Query failed: " . $conn->error);
    }

    while ($row = $res->fetch_row()) {
        echo " - " . $row[0] . "\n";
    }

    echo "\nConnection and table verification: SUCCESSFUL!\n";

} catch (Exception $e) {
    echo "\nCONNECTION ERROR: " . $e->getMessage() . "\n";
}
?>
