<?php
/**
 * AMC Database Schema Setup Script
 * Creates all required amc_* tables and initial seeds without affecting existing tables.
 */

if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = 'localhost';
if (!isset($_SERVER['DOCUMENT_ROOT'])) $_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

try {
    // 1. Dynamic AMC Product Types Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default dynamic AMC product types if empty
    $resProd = $conn->query("SELECT COUNT(*) as cnt FROM amc_products");
    $rowProd = $resProd->fetch_assoc();
    if ($rowProd['cnt'] == 0) {
        $conn->query("INSERT INTO amc_products (name, description) VALUES
            ('CCTV', 'CCTV Cameras and DVR/NVR Surveillance Systems'),
            ('Printer', 'Printers, Scanners and All-In-One Inkjet/Laser Devices'),
            ('Desktop', 'Desktop PCs, Workstations and Peripherals'),
            ('Internet', 'Internet Routers, Switches, Cable Infrastructure & Network Setup')");
    }

    // 2. AMC Contracts Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        amc_number VARCHAR(50) NOT NULL UNIQUE,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_email VARCHAR(255) DEFAULT NULL,
        customer_address TEXT NOT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        visit_count INT NOT NULL DEFAULT 4,
        visit_frequency VARCHAR(50) DEFAULT 'Quarterly',
        status ENUM('Upcoming', 'Active', 'Completed', 'Expired', 'Suspended', 'Cancelled') DEFAULT 'Active',
        assigned_engineers TEXT DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(amc_number),
        INDEX(customer_phone),
        INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. AMC Contract Products Mapping Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_contract_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contract_id INT NOT NULL,
        product_id INT DEFAULT NULL,
        product_name VARCHAR(100) NOT NULL,
        quantity INT DEFAULT 1,
        serial_number VARCHAR(100) DEFAULT NULL,
        location_details VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(contract_id),
        FOREIGN KEY (contract_id) REFERENCES amc_contracts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. AMC Visits Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contract_id INT NOT NULL,
        visit_number INT NOT NULL,
        scheduled_date DATE NOT NULL,
        due_date DATE DEFAULT NULL,
        assigned_engineer VARCHAR(100) DEFAULT NULL,
        status ENUM('ASSIGNED', 'ACCEPTED', 'REACHED', 'INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED', 'OVERDUE', 'CANCELLED') DEFAULT 'ASSIGNED',
        priority ENUM('Normal', 'High', 'Urgent') DEFAULT 'Normal',
        assignment_timestamp TIMESTAMP NULL DEFAULT NULL,
        last_activity_timestamp TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        arrival_timestamp TIMESTAMP NULL DEFAULT NULL,
        completion_timestamp TIMESTAMP NULL DEFAULT NULL,
        departure_timestamp TIMESTAMP NULL DEFAULT NULL,
        arrival_lat VARCHAR(50) DEFAULT NULL,
        arrival_lng VARCHAR(50) DEFAULT NULL,
        departure_lat VARCHAR(50) DEFAULT NULL,
        departure_lng VARCHAR(50) DEFAULT NULL,
        product_condition ENUM('Normal', 'Minor Issue', 'Major Issue', 'Not Working') DEFAULT 'Normal',
        inspection_result TEXT DEFAULT NULL,
        service_performed TEXT DEFAULT NULL,
        arrival_remark TEXT DEFAULT NULL,
        final_remark TEXT DEFAULT NULL,
        departure_remark TEXT DEFAULT NULL,
        follow_up_notes TEXT DEFAULT NULL,
        escalation_level INT DEFAULT 0,
        is_inactive_reassigned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(contract_id),
        INDEX(assigned_engineer),
        INDEX(scheduled_date),
        INDEX(status),
        FOREIGN KEY (contract_id) REFERENCES amc_contracts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. AMC Engineer Assignments Audit & History Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        contract_id INT NOT NULL,
        engineer_name VARCHAR(100) NOT NULL,
        previous_engineer VARCHAR(100) DEFAULT NULL,
        assigned_by VARCHAR(100) NOT NULL,
        assignment_reason VARCHAR(255) DEFAULT 'Scheduled Visit',
        status ENUM('Active', 'Reassigned', 'Escalated', 'Completed') DEFAULT 'Active',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expired_at TIMESTAMP NULL DEFAULT NULL,
        INDEX(visit_id),
        INDEX(contract_id),
        INDEX(engineer_name),
        FOREIGN KEY (visit_id) REFERENCES amc_visits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. AMC Visit Photos Table (Categorized & GPS Watermarked)
    $conn->query("CREATE TABLE IF NOT EXISTS amc_visit_photos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        contract_id INT NOT NULL,
        engineer_name VARCHAR(100) NOT NULL,
        photo_type ENUM('ARRIVAL', 'BEFORE_SERVICE', 'DURING_SERVICE', 'ISSUE', 'PART_REPLACEMENT', 'AFTER_SERVICE', 'DEPARTURE') NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        latitude VARCHAR(50) DEFAULT NULL,
        longitude VARCHAR(50) DEFAULT NULL,
        captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        remark TEXT DEFAULT NULL,
        INDEX(visit_id),
        INDEX(contract_id),
        INDEX(photo_type),
        FOREIGN KEY (visit_id) REFERENCES amc_visits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. AMC Visit Issues & Maintenance Requirement Tracking Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_visit_issues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        contract_id INT NOT NULL,
        product_name VARCHAR(100) NOT NULL,
        issue_title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        severity ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
        issue_photo VARCHAR(255) DEFAULT NULL,
        required_action TEXT DEFAULT NULL,
        part_required VARCHAR(255) DEFAULT NULL,
        quantity INT DEFAULT 1,
        engineer_remark TEXT DEFAULT NULL,
        status ENUM('Open', 'In Progress', 'Resolved', 'Follow-up Required') DEFAULT 'Open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(visit_id),
        INDEX(contract_id),
        INDEX(status),
        FOREIGN KEY (visit_id) REFERENCES amc_visits(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. AMC Visit Remarks History Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_visit_remarks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        stage VARCHAR(50) NOT NULL,
        engineer_name VARCHAR(100) NOT NULL,
        remark TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(visit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 9. AMC Audit Trail Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contract_id INT DEFAULT NULL,
        visit_id INT DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        performed_by VARCHAR(100) NOT NULL,
        role VARCHAR(50) DEFAULT NULL,
        details TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(contract_id),
        INDEX(visit_id),
        INDEX(action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 10. AMC Dynamic System Settings Table
    $conn->query("CREATE TABLE IF NOT EXISTS amc_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default 48h reassignment config if not set
    $conn->query("INSERT IGNORE INTO amc_settings (setting_key, setting_value) VALUES ('reassignment_hours', '48')");

    echo json_encode([
        'status' => 'success',
        'message' => 'AMC Database Schema created and verified successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'AMC Schema Migration Error: ' . $e->getMessage()
    ]);
}
?>
