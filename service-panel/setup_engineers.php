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

if ($conn->query($createTableQuery) === TRUE) {
    echo "Table 'engineers' created or already exists.\n<br>";
} else {
    die("Error creating table: " . $conn->error . "\n");
}

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
