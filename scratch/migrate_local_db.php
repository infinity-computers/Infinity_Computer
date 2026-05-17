<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = '';
$old_db = 'infinity_computer';
$new_db = 'u211084505_infinity';

try {
    // 1. Connect without selecting database
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // 2. Create the new database locally if not exists
    echo "Creating new database '$new_db' if it doesn't exist...\n";
    $conn->query("CREATE DATABASE IF NOT EXISTS `$new_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    // Check if the old database exists
    $db_check = $conn->query("SHOW DATABASES LIKE '$old_db'");
    if ($db_check->num_rows === 0) {
        echo "Old database '$old_db' does not exist locally. We will just initialize '$new_db' with all core tables.\n";
        $conn->select_db($new_db);
        initialize_empty_db($conn);
        exit;
    }

    // 3. Migrate tables from old to new
    echo "Reading tables from '$old_db'...\n";
    $conn->select_db($old_db);
    $tables_res = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $tables_res->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        echo "Migrating table '$table'...\n";
        
        // Get Create Table statement
        $create_res = $conn->query("SHOW CREATE TABLE `$table`");
        $create_row = $create_res->fetch_row();
        $create_sql = $create_row[1];

        // Connect to new database
        $new_conn = new mysqli($host, $user, $pass, $new_db);
        $new_conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Drop existing table in new DB if exists to make a fresh clone
        $new_conn->query("DROP TABLE IF EXISTS `$table`");
        
        // Create table in new DB
        if (!$new_conn->query($create_sql)) {
            throw new Exception("Failed to create table '$table' in new database: " . $new_conn->error);
        }

        // Copy all data
        echo "Copying data for '$table'...\n";
        $data_res = $conn->query("SELECT * FROM `$table`");
        while ($data_row = $data_res->fetch_assoc()) {
            if (empty($data_row)) continue;
            
            $keys = array_map(function($k) { return "`$k`"; }, array_keys($data_row));
            $values = [];
            
            foreach ($data_row as $val) {
                if ($val === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $new_conn->real_escape_string($val) . "'";
                }
            }
            
            $insert_sql = "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ")";
            $new_conn->query($insert_sql);
        }
        $new_conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $new_conn->close();
    }

    echo "SUCCESS: All tables and data migrated to '$new_db' successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

function initialize_empty_db($conn) {
    // If the old DB didn't exist locally, initialize standard schemas directly
    $schemas = [
        "CREATE TABLE IF NOT EXISTS `otp_codes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `code` varchar(6) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `expires_at` timestamp NOT NULL,
            `verified` tinyint(1) DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `user_service_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `service_id` varchar(50) UNIQUE NOT NULL,
            `name` varchar(100) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `email` varchar(100) NOT NULL,
            `service_type` varchar(100) NOT NULL,
            `device_name` varchar(255) NOT NULL,
            `company` varchar(255) NOT NULL,
            `problem` text NOT NULL,
            `status` varchar(50) DEFAULT 'Pending',
            `date_received` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `home_service_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `service_id` varchar(50) UNIQUE NOT NULL,
            `name` varchar(100) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `email` varchar(100) NOT NULL,
            `service_type` varchar(100) NOT NULL,
            `device_name` varchar(255) NOT NULL,
            `company` varchar(255) NOT NULL,
            `problem` text NOT NULL,
            `status` varchar(50) DEFAULT 'Pending',
            `date_received` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `services` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `service_id` varchar(50) UNIQUE NOT NULL,
            `name` varchar(100) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `email` varchar(100) NOT NULL,
            `service_type` varchar(100) NOT NULL,
            `device_name` varchar(255) NOT NULL,
            `company` varchar(255) NOT NULL,
            `problem` text NOT NULL,
            `status` varchar(50) DEFAULT 'Pending',
            `date_received` timestamp DEFAULT CURRENT_TIMESTAMP,
            `assigned_engineer` varchar(100) DEFAULT 'Suraj',
            `estimated_cost` decimal(10,2) DEFAULT 0.00,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS `status_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `service_id` varchar(50) NOT NULL,
            `status` varchar(50) NOT NULL,
            `updated_by` varchar(100) NOT NULL,
            `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
            `remarks` text DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($schemas as $sql) {
        $conn->query($sql);
    }
    echo "Initialized default Infinity Computer tables.\n";
}
?>
