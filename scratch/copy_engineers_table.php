<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = '';
$source_db = 'infinity_students';
$target_db = 'u211084505_infinity';

try {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // 1. Fetch table schema from source
    $conn->select_db($source_db);
    $create_res = $conn->query("SHOW CREATE TABLE `engineers`");
    if (!$create_res) {
        throw new Exception("Could not find 'engineers' table in '$source_db' database.");
    }
    $create_row = $create_res->fetch_row();
    $create_sql = $create_row[1];

    // 2. Select target database
    $conn->select_db($target_db);
    
    // Create the engineers table in target database if it doesn't exist
    echo "Creating 'engineers' table in '$target_db'...\n";
    if (!$conn->query($create_sql)) {
        throw new Exception("Failed to create 'engineers' table in '$target_db': " . $conn->error);
    }

    // 3. Copy existing records from source database
    $source_conn = new mysqli($host, $user, $pass, $source_db);
    $data_res = $source_conn->query("SELECT * FROM `engineers`");
    
    while ($row = $data_res->fetch_assoc()) {
        $name = $conn->real_escape_string($row['name']);
        $email = $conn->real_escape_string($row['email']);
        $is_active = intval($row['is_active']);
        
        // Insert if not exists in target
        $check = $conn->query("SELECT id FROM `engineers` WHERE `name` = '$name' OR `email` = '$email'");
        if ($check->num_rows === 0) {
            echo "Copying engineer: $name ($email)\n";
            $conn->query("INSERT INTO `engineers` (`name`, `email`, `is_active`) VALUES ('$name', '$email', $is_active)");
        } else {
            echo "Engineer already exists: $name ($email)\n";
        }
    }
    $source_conn->close();

    // 4. Add the two new engineers (Om and Jatin) into this table!
    $new_engineers = [
        [
            'name' => 'Om',
            'email' => 'om@dev.infinitycomputer.in',
            'is_active' => 1
        ],
        [
            'name' => 'Jatin',
            'email' => 'jatin@dev.infinitycomputer.in',
            'is_active' => 1
        ]
    ];

    foreach ($new_engineers as $eng) {
        $name = $conn->real_escape_string($eng['name']);
        $email = $conn->real_escape_string($eng['email']);
        $is_active = $eng['is_active'];
        
        $check = $conn->query("SELECT id FROM `engineers` WHERE `name` = '$name' OR `email` = '$email'");
        if ($check->num_rows === 0) {
            echo "Adding new engineer to system: $name ($email)\n";
            $conn->query("INSERT INTO `engineers` (`name`, `email`, `is_active`) VALUES ('$name', '$email', $is_active)");
        } else {
            echo "New engineer already whitelisted: $name ($email)\n";
        }
    }

    echo "\nSUCCESS: The 'engineers' table was copied and fully updated in '$target_db' database!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
