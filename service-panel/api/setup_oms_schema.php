<?php
if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = 'localhost';
if (!isset($_SERVER['DOCUMENT_ROOT'])) $_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

function addColumnIfNotExists($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($res && $res->num_rows == 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if (!$conn->query($sql)) {
            throw new Exception("Failed adding column $column to $table: " . $conn->error);
        }
    }
}

try {
    // 1. Ensure primary base tables exist first
    $conn->query("CREATE TABLE IF NOT EXISTS engineers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        email VARCHAR(150) DEFAULT NULL,
        position VARCHAR(50) DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL UNIQUE,
        email VARCHAR(255) DEFAULT NULL,
        company VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL UNIQUE,
        customer_id INT NOT NULL,
        service_type VARCHAR(100) NOT NULL,
        device_name VARCHAR(255) NOT NULL,
        problem TEXT NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        assigned_engineer VARCHAR(255) DEFAULT NULL,
        assigned_at TIMESTAMP NULL DEFAULT NULL,
        date_received DATE NOT NULL,
        date_completed DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS user_service_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(255) NOT NULL,
        address TEXT NOT NULL,
        device_type VARCHAR(100) NOT NULL,
        brand VARCHAR(100) DEFAULT NULL,
        model VARCHAR(100) DEFAULT NULL,
        company VARCHAR(255) DEFAULT NULL,
        problem TEXT NOT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending Approval',
        device_received BOOLEAN DEFAULT 0,
        assigned_engineer VARCHAR(100) DEFAULT 'Suraj',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS service_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        source_table VARCHAR(50) DEFAULT 'services',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Extend engineers table for OMS Roles and Live Status
    addColumnIfNotExists($conn, 'engineers', 'role', "ENUM('Super Admin', 'Admin/Accounts', 'Engineer') DEFAULT 'Engineer'");
    addColumnIfNotExists($conn, 'engineers', 'status', "ENUM('Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty') DEFAULT 'Active'");
    addColumnIfNotExists($conn, 'engineers', 'current_ticket', "VARCHAR(50) DEFAULT NULL");
    addColumnIfNotExists($conn, 'engineers', 'phone', "VARCHAR(20) DEFAULT NULL");
    addColumnIfNotExists($conn, 'engineers', 'last_activity_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

    // Seed default Super Admin role for specific emails/users if present
    $conn->query("UPDATE engineers SET role = 'Super Admin' WHERE name IN ('Suraj', 'icc') OR email IN ('suraj@staff.infinitycomputer.in', 'icc@infinitycomputer.in')");

    // 3. Extend services & user_service_requests tables for Device History, Timeline Metrics, and Internal Revenue
    $targetServiceTables = ['services', 'user_service_requests'];
    foreach ($targetServiceTables as $tbl) {
        addColumnIfNotExists($conn, $tbl, 'serial_number', "VARCHAR(100) DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'first_response_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'travel_started_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'reached_customer_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'work_started_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'engineer_submitted_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'admin_approved_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'billing_completed_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'closed_at', "TIMESTAMP NULL DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'engineer_submitted', "TINYINT(1) DEFAULT 0");
        addColumnIfNotExists($conn, $tbl, 'verified_by_admin', "VARCHAR(100) DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'billing_verified_by', "VARCHAR(100) DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'billing_status', "ENUM('Billing Pending', 'Invoice Generated', 'Payment Pending', 'Payment Received', 'Cash Collected', 'Credit Customer', 'AMC', 'Warranty') DEFAULT 'Billing Pending'");
        addColumnIfNotExists($conn, $tbl, 'invoice_number', "VARCHAR(100) DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'payment_mode', "VARCHAR(50) DEFAULT NULL");
        addColumnIfNotExists($conn, $tbl, 'service_value_internal', "INT DEFAULT 0"); // Stores ₹ value / 100
        addColumnIfNotExists($conn, $tbl, 'sales_value_internal', "INT DEFAULT 0");   // Stores ₹ value / 1000
    }

    // 4. Extend service_images for categorized photo proof
    addColumnIfNotExists($conn, 'service_images', 'category', "ENUM('Customer Location', 'Before Repair', 'Device Condition', 'Open Device', 'Fault Found', 'Parts Removed', 'Parts Installed', 'After Repair', 'Testing', 'Final Delivery', 'General') DEFAULT 'General'");

    // 5. Create OMS Table: Call Attempts
    $conn->query("CREATE TABLE IF NOT EXISTS call_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL,
        called_by VARCHAR(100) NOT NULL,
        call_status ENUM('Answered', 'No Answer', 'Busy', 'Wrong Number', 'Switched Off', 'Customer Requested Callback', 'Customer Busy', 'Customer Cancelled', 'Customer Will Visit Office') NOT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(service_id),
        INDEX(called_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Create OMS Table: Multi-Engineer Assignments (Primary vs Supporting)
    $conn->query("CREATE TABLE IF NOT EXISTS ticket_engineer_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL,
        engineer_name VARCHAR(100) NOT NULL,
        assignment_type ENUM('Primary', 'Supporting') DEFAULT 'Primary',
        assigned_by VARCHAR(100) NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_at TIMESTAMP NULL DEFAULT NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        status ENUM('Assigned', 'Active', 'Completed', 'Transferred') DEFAULT 'Assigned',
        INDEX(service_id),
        INDEX(engineer_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. Create OMS Table: Custody Transfers
    $conn->query("CREATE TABLE IF NOT EXISTS custody_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL,
        transfer_type ENUM('Customer -> Engineer', 'Engineer -> Workshop', 'Workshop -> Engineer', 'Engineer -> Customer') NOT NULL,
        from_user VARCHAR(100) NOT NULL,
        to_user VARCHAR(100) NOT NULL,
        photo_path VARCHAR(255) DEFAULT NULL,
        device_condition TEXT DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(service_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. Create OMS Table: Service Parts Replaced
    $conn->query("CREATE TABLE IF NOT EXISTS service_parts_replaced (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL,
        old_part_name VARCHAR(255) DEFAULT NULL,
        old_part_serial VARCHAR(100) DEFAULT NULL,
        new_part_name VARCHAR(255) NOT NULL,
        new_part_serial VARCHAR(100) DEFAULT NULL,
        quantity INT DEFAULT 1,
        warranty_period VARCHAR(100) DEFAULT NULL,
        cost_price DECIMAL(10,2) DEFAULT 0.00,
        selling_price DECIMAL(10,2) DEFAULT 0.00,
        photo_path VARCHAR(255) DEFAULT NULL,
        replaced_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(service_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 9. Create OMS Table: Service Timeline Events (Immutable History)
    $conn->query("CREATE TABLE IF NOT EXISTS service_timeline_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id VARCHAR(50) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        performed_by VARCHAR(100) NOT NULL,
        event_data TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(service_id),
        INDEX(event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo json_encode([
        'status' => 'success',
        'message' => 'OMS database tables and non-breaking column extensions have been successfully applied!'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'OMS Schema Migration Failed: ' . $e->getMessage()
    ]);
}
?>
