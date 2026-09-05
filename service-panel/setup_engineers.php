<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/config/db.php';

// Create table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS engineers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL,
    position VARCHAR(50) DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

function addColumnIfNotExistsSetup($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $res->num_rows == 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        $conn->query($sql);
    }
}

addColumnIfNotExistsSetup($conn, 'engineers', 'position', "VARCHAR(50) DEFAULT 'staff'");
addColumnIfNotExistsSetup($conn, 'engineers', 'role', "ENUM('Super Admin', 'Admin/Accounts', 'Engineer') DEFAULT 'Engineer'");
addColumnIfNotExistsSetup($conn, 'engineers', 'status', "ENUM('Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty') DEFAULT 'Active'");
addColumnIfNotExistsSetup($conn, 'engineers', 'current_ticket', "VARCHAR(50) DEFAULT NULL");
addColumnIfNotExistsSetup($conn, 'engineers', 'phone', "VARCHAR(20) DEFAULT NULL");
addColumnIfNotExistsSetup($conn, 'engineers', 'last_activity_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

$initialEngineers = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh', 'Om', 'Jatin'];

foreach ($initialEngineers as $engName) {
    $stmt = $conn->prepare("INSERT IGNORE INTO engineers (name) VALUES (?)");
    if ($stmt) {
        $stmt->bind_param("s", $engName);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Error preparing statement for $engName: " . $conn->error . "\n<br>";
    }
}

echo "Initial engineers have been seeded into the database.\n";
?>
