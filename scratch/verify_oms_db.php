<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/../service-panel/config/db.php';

$res = $conn->query("SHOW TABLES");
echo "TABLES IN DATABASE:\n";
while ($row = $res->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

echo "\nENGINEERS COLUMNS:\n";
$res = $conn->query("SHOW COLUMNS FROM engineers");
while ($row = $res->fetch_assoc()) {
    echo "  * {$row['Field']} ({$row['Type']})\n";
}

echo "\nSERVICES COLUMNS:\n";
$res = $conn->query("SHOW COLUMNS FROM services");
while ($row = $res->fetch_assoc()) {
    echo "  * {$row['Field']} ({$row['Type']})\n";
}
?>
