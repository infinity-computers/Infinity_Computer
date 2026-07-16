<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/config/db.php';

// Create table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS staff_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
";

if ($conn->query($createTableQuery) === TRUE) {
    echo "Table 'staff_users' created or already exists.\n";
} else {
    die("Error creating table: " . $conn->error . "\n");
}

$allowedEmails = [
    'akshar@staff.infinitycomputer.in' => 'Akshar',
    'karan@staff.infinitycomputer.in' => 'Karan',
    'suraj@staff.infinitycomputer.in' => 'Suraj',
    'rahul@staff.infinitycomputer.in' => 'Rahul',
    'paresh@staff.infinitycomputer.in' => 'Paresh',
    'om@dev.infinitycomputer.in' => 'Om',
    'jatin@dev.infinitycomputer.in' => 'Jatin',
    'icc@infinitycomputer.in' => 'Icc',
    'pacifier2204@gmail.com' => 'Pacifier',
    'rathorjatin70@gmail.com' => 'Rathor Jatin'
];

$admins = ['icc@infinitycomputer.in', 'suraj@staff.infinitycomputer.in'];

foreach ($allowedEmails as $email => $name) {
    $role = in_array($email, $admins) ? 'admin' : 'staff';
    
    $stmt = $conn->prepare("INSERT IGNORE INTO staff_users (name, email, role) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sss", $name, $email, $role);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Error preparing statement for $email: " . $conn->error . "\n";
    }
}

echo "Initial staff users have been seeded into the database.\n";
?>
