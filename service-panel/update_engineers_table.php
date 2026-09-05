<?php
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/config/db.php';

function addCol($conn, $col, $def) {
    $res = $conn->query("SHOW COLUMNS FROM `engineers` LIKE '$col'");
    if ($res && $res->num_rows == 0) {
        if ($conn->query("ALTER TABLE `engineers` ADD COLUMN `$col` $def")) {
            echo "Added column '$col'.<br>";
        } else {
            echo "Error adding '$col': " . $conn->error . "<br>";
        }
    } else {
        echo "Column '$col' already exists.<br>";
    }
}

addCol($conn, 'position', "VARCHAR(50) DEFAULT 'staff'");
addCol($conn, 'role', "ENUM('Super Admin', 'Admin/Accounts', 'Engineer') DEFAULT 'Engineer'");
addCol($conn, 'status', "ENUM('Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty') DEFAULT 'Active'");
addCol($conn, 'current_ticket', "VARCHAR(50) DEFAULT NULL");
addCol($conn, 'phone', "VARCHAR(20) DEFAULT NULL");
addCol($conn, 'last_activity_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
?>
