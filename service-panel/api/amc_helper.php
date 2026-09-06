<?php
/**
 * AMC Helper Functions Module
 * Contains business logic for AMC number generation, visit scheduling, fair engineer assignment,
 * audit logging, and automatic 48-hour inactivity reassignment & escalation.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_helper.php';

/**
 * Generate Next AMC Contract Number (AMC-YYYY-XXXX)
 */
function generateAmcNumber($conn) {
    $year = date('Y');
    $prefix = "AMC-{$year}-";
    $query = "SELECT amc_number FROM amc_contracts WHERE amc_number LIKE '$prefix%' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($query);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $lastNum = intval(substr($row['amc_number'], strrpos($row['amc_number'], '-') + 1));
        $nextNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextNum = '0001';
    }
    return $prefix . $nextNum;
}

/**
 * Get active engineers list for assignment
 */
function getActiveEngineers($conn) {
    $engineers = [];
    try {
        $res = $conn->query("SELECT * FROM engineers ORDER BY name ASC");
        if ($res && $res->num_rows > 0) {
                $engName = $row['name'] ?? '';
                if ($engName !== 'icc' && $engName !== 'Nikhil' && $engName !== 'Priyanka Patel') {
                    if (isset($row['status']) && $row['status'] === 'Off Duty') {
                        continue;
                    }
                    $engineers[] = $row;
                }
        }
    } catch (\Throwable $e) {
        // Silently ignore query issues
    }

    if (empty($engineers)) {
        $fallback = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh'];
        foreach ($fallback as $name) {
            $engineers[] = ['name' => $name, 'email' => '', 'position' => 'staff'];
        }
    }
    return $engineers;
}

/**
 * Audit Log Helper (Safe NULL handling)
 */
function logAmcAudit($conn, $contract_id, $visit_id, $action, $performed_by, $role = 'System', $details = '') {
    try {
        $cid = !empty($contract_id) ? intval($contract_id) : 0;
        $vid = !empty($visit_id) ? intval($visit_id) : 0;
        $stmt = $conn->prepare("INSERT INTO amc_audit_logs (contract_id, visit_id, action, performed_by, role, details) VALUES (NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iissss", $cid, $vid, $action, $performed_by, $role, $details);
            $stmt->execute();
        }
    } catch (\Throwable $e) {
        // Silently catch audit log issue so primary operation succeeds
    }
}

/**
 * Automatically schedule visits across 12-month AMC contract duration
 */
function generateAmcVisits($conn, $contract_id, $start_date, $end_date, $visit_count, $preferred_engineers = [], $created_by = 'Admin') {
    $startDateObj = new DateTime($start_date);
    $endDateObj = new DateTime($end_date);

    $intervalDays = floor(($endDateObj->getTimestamp() - $startDateObj->getTimestamp()) / (86400 * max(1, $visit_count)));
    
    // Get list of engineers to distribute workload
    $availableEngs = getActiveEngineers($conn);
    $engNames = !empty($preferred_engineers) ? $preferred_engineers : array_column($availableEngs, 'name');
    if (empty($engNames)) {
        $engNames = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh'];
    }

    $createdVisits = [];
    $currDate = clone $startDateObj;

    for ($i = 1; $i <= $visit_count; $i++) {
        // Fair distribution: Cycle through engineer list
        $assignedEng = $engNames[($i - 1) % count($engNames)];
        
        $schedDateStr = $currDate->format('Y-m-d');
        $dueDateObj = clone $currDate;
        $dueDateObj->modify('+7 days');
        $dueDateStr = $dueDateObj->format('Y-m-d');

        $stmt = $conn->prepare("INSERT INTO amc_visits (contract_id, visit_number, scheduled_date, due_date, assigned_engineer, status, assignment_timestamp, last_activity_timestamp) VALUES (?, ?, ?, ?, ?, 'ASSIGNED', NOW(), NOW())");
        $stmt->bind_param("iisss", $contract_id, $i, $schedDateStr, $dueDateStr, $assignedEng);
        $stmt->execute();
        $visit_id = $conn->insert_id;

        // Record initial assignment
        $stmtAss = $conn->prepare("INSERT INTO amc_assignments (visit_id, contract_id, engineer_name, assigned_by, assignment_reason, status) VALUES (?, ?, ?, ?, 'Initial Schedule', 'Active')");
        $stmtAss->bind_param("iiss", $visit_id, $contract_id, $assignedEng, $created_by);
        $stmtAss->execute();

        logAmcAudit($conn, $contract_id, $visit_id, 'Visit Scheduled', $created_by, 'Admin', "Visit #{$i} scheduled for {$schedDateStr}, assigned to {$assignedEng}");

        // Send assignment email to engineer
        sendAmcEngineerAssignmentEmail($conn, $visit_id, false);

        $createdVisits[] = [
            'visit_id' => $visit_id,
            'visit_number' => $i,
            'scheduled_date' => $schedDateStr,
            'assigned_engineer' => $assignedEng
        ];

        // Increment date for next visit
        $currDate->modify("+{$intervalDays} days");
    }

    return $createdVisits;
}

/**
 * Get Engineer Email Address by Name (DB + Fallback Map)
 */
function getEngineerEmailByName($conn, $engineer_name) {
    if (empty($engineer_name)) return 'icc@infinitycomputer.in';

    $fallbackMap = [
        'Suraj' => 'suraj@staff.infinitycomputer.in',
        'Akshar' => 'akshar@staff.infinitycomputer.in',
        'Karan' => 'karan@staff.infinitycomputer.in',
        'Rahul' => 'rahul@staff.infinitycomputer.in',
        'Paresh' => 'paresh@staff.infinitycomputer.in',
        'Om' => 'om@dev.infinitycomputer.in',
        'Jatin' => 'jatin@dev.infinitycomputer.in',
        'icc' => 'icc@infinitycomputer.in',
        'Admin' => 'icc@infinitycomputer.in'
    ];

    try {
        $eName = $conn->real_escape_string($engineer_name);
        $res = $conn->query("SELECT email FROM engineers WHERE name = '$eName' AND email IS NOT NULL AND email != '' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['email'])) {
                return $row['email'];
            }
        }
    } catch (\Throwable $e) {
        // Ignore DB lookup issues
    }

    if (isset($fallbackMap[$engineer_name])) {
        return $fallbackMap[$engineer_name];
    }

    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $engineer_name)) . '@staff.infinitycomputer.in';
}

/**
 * Send AMC Engineer Assignment Email
 */
function sendAmcEngineerAssignmentEmail($conn, $visit_id, $is_reassignment = false) {
    $stmt = $conn->prepare("
        SELECT v.*, c.amc_number, c.customer_name, c.customer_phone, c.customer_email, c.customer_address, c.company_name
        FROM amc_visits v
        JOIN amc_contracts c ON v.contract_id = c.id
        WHERE v.id = ?
    ");
    $stmt->bind_param("i", $visit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return false;
    $visit = $res->fetch_assoc();

    $engineer_name = $visit['assigned_engineer'];
    $engineer_email = getEngineerEmailByName($conn, $engineer_name);
    if (empty($engineer_email)) return false;

    // Fetch Products Covered
    $cpRes = $conn->query("SELECT product_name, quantity, serial_number FROM amc_contract_products WHERE contract_id = {$visit['contract_id']}");
    $prods = [];
    if ($cpRes) {
        while ($cpRow = $cpRes->fetch_assoc()) {
            $prods[] = $cpRow['product_name'] . ($cpRow['quantity'] > 1 ? " (x{$cpRow['quantity']})" : "") . ($cpRow['serial_number'] ? " [SN: {$cpRow['serial_number']}]" : "");
        }
    }
    $productsStr = !empty($prods) ? implode(', ', $prods) : 'General Computer/Network Equipment';

    $subject = $is_reassignment 
        ? "🔄 AMC Visit Re-assigned to You: Visit #{$visit['visit_number']} ({$visit['amc_number']})" 
        : "🔧 New AMC Service Visit Assigned: Visit #{$visit['visit_number']} ({$visit['amc_number']})";

    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";

    $title = $is_reassignment ? "AMC Visit Re-assigned to You" : "New AMC Visit Scheduled & Assigned";

    $msg = "
    <html>
    <head><title>{$title}</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 650px; margin: 20px auto; padding: 25px; border: 1px solid #3b82f6; border-top: 5px solid #1d4ed8; border-radius: 12px; background: #ffffff;'>
            <h2 style='color: #1d4ed8; text-align: center; margin-top: 0;'>🛡️ {$title}</h2>
            <p>Hello <strong>{$engineer_name}</strong>,</p>
            <p>You have been assigned an Annual Maintenance Contract (AMC) service visit. Below are the full details of the customer and maintenance tasks:</p>

            <div style='background: #eff6ff; padding: 18px; border-radius: 8px; border: 1px solid #bfdbfe; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr><td style='padding: 6px 0; font-weight: bold; width: 140px; color: #1e40af;'>AMC Number:</td><td style='padding: 6px 0; font-weight: bold; color: #1d4ed8;'>{$visit['amc_number']} (Visit #{$visit['visit_number']})</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #1e40af;'>Scheduled Date:</td><td style='padding: 6px 0; font-weight: bold; color: #dc2626;'>📅 {$visit['scheduled_date']}</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #1e40af;'>Due Date:</td><td style='padding: 6px 0;'>{$visit['due_date']}</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #1e40af;'>Customer Name:</td><td style='padding: 6px 0; font-weight: bold;'>{$visit['customer_name']} " . ($visit['company_name'] ? "({$visit['company_name']})" : "") . "</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #1e40af;'>Contact Phone:</td><td style='padding: 6px 0;'>📞 <a href='tel:{$visit['customer_phone']}'>{$visit['customer_phone']}</a></td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #1e40af;'>Site Address:</td><td style='padding: 6px 0;'>📍 {$visit['customer_address']}</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #1e40af;'>Covered Equipment:</td><td style='padding: 6px 0;'>📦 {$productsStr}</td></tr>
                </table>
            </div>

            <div style='background: #fff7ed; padding: 15px; border-radius: 8px; border: 1px solid #ffedd5; margin-bottom: 20px;'>
                <strong style='color: #c2410c; display: block; margin-bottom: 5px;'>📋 Maintenance Work Required:</strong>
                <p style='margin: 0; color: #9a3412; font-size: 0.95rem;'>
                    Perform routine maintenance inspection on all covered equipment, take arrival and departure photographs with GPS watermark, record any damaged/faulty parts, and submit final service remarks.
                </p>
            </div>

            <div style='background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 20px; color: #991b1b; font-size: 0.9rem;'>
                <strong>⚠️ Reassignment Policy Notice:</strong><br>
                If it is <strong>not possible</strong> for you to perform this visit on the scheduled date, please log in to the AMC Service Panel immediately to <strong>reassign this task to another engineer</strong>. Unattended visits will automatically reassign after 48 hours and escalate to Admin.
            </div>

            <div style='text-align: center; margin: 25px 0;'>
                <a href='https://infinitycomputer.in/service-panel/amc.php' style='background: #1d4ed8; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; box-shadow: 0 4px 10px rgba(29,78,216,0.2);'>Open AMC Service Panel</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 11px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. Automated Service Notification.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($engineer_email, $subject, $msg, $headers);
}

/**
 * Send Email Notification On The Scheduled Date ("Day when engineer needs to go")
 */
function sendAmcScheduledDayEmail($conn, $visit_id) {
    $stmt = $conn->prepare("
        SELECT v.*, c.amc_number, c.customer_name, c.customer_phone, c.customer_email, c.customer_address, c.company_name
        FROM amc_visits v
        JOIN amc_contracts c ON v.contract_id = c.id
        WHERE v.id = ?
    ");
    $stmt->bind_param("i", $visit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return false;
    $visit = $res->fetch_assoc();

    $engineer_name = $visit['assigned_engineer'];
    $engineer_email = getEngineerEmailByName($conn, $engineer_name);
    if (empty($engineer_email)) return false;

    // Fetch Products Covered
    $cpRes = $conn->query("SELECT product_name, quantity FROM amc_contract_products WHERE contract_id = {$visit['contract_id']}");
    $prods = [];
    if ($cpRes) {
        while ($cpRow = $cpRes->fetch_assoc()) $prods[] = $cpRow['product_name'] . ($cpRow['quantity'] > 1 ? " (x{$cpRow['quantity']})" : "");
    }
    $productsStr = !empty($prods) ? implode(', ', $prods) : 'General Equipment';

    $subject = "📅 TODAY'S SCHEDULED AMC VISIT: Visit #{$visit['visit_number']} ({$visit['amc_number']}) - {$visit['customer_name']}";
    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";

    $msg = "
    <html>
    <head><title>Scheduled AMC Visit Today</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 650px; margin: 20px auto; padding: 25px; border: 2px solid #0284c7; border-radius: 12px; background: #ffffff;'>
            <div style='background: #0284c7; color: #ffffff; padding: 12px; text-align: center; font-size: 1.2rem; font-weight: bold; border-radius: 8px; margin-bottom: 20px;'>
                📅 YOU HAVE A SCHEDULED AMC VISIT TODAY!
            </div>

            <p>Hello <strong>{$engineer_name}</strong>,</p>
            <p>This is a notification that you have an AMC service visit scheduled for <strong>TODAY ({$visit['scheduled_date']})</strong>.</p>

            <div style='background: #f0f9ff; padding: 18px; border-radius: 8px; border: 1px solid #bae6fd; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr><td style='padding: 6px 0; font-weight: bold; width: 140px; color: #0369a1;'>AMC Number:</td><td style='padding: 6px 0; font-weight: bold; color: #0284c7;'>{$visit['amc_number']} (Visit #{$visit['visit_number']})</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #0369a1;'>Scheduled Date:</td><td style='padding: 6px 0; font-weight: bold; color: #dc2626;'>{$visit['scheduled_date']}</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #0369a1;'>Customer Name:</td><td style='padding: 6px 0; font-weight: bold;'>{$visit['customer_name']} " . ($visit['company_name'] ? "({$visit['company_name']})" : "") . "</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #0369a1;'>Phone Number:</td><td style='padding: 6px 0;'>📞 <a href='tel:{$visit['customer_phone']}'>{$visit['customer_phone']}</a></td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #0369a1;'>Site Address:</td><td style='padding: 6px 0;'>📍 {$visit['customer_address']}</td></tr>
                    <tr><td style='padding: 6px 0; font-weight: bold; color: #0369a1;'>Products Covered:</td><td style='padding: 6px 0;'>📦 {$productsStr}</td></tr>
                </table>
            </div>

            <div style='background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 20px; color: #991b1b;'>
                <strong>📌 Action Required:</strong><br>
                Please reach the site, accept the assignment, upload arrival & departure photos with GPS watermark, and complete the inspection.<br><br>
                <em>If it is not possible for you to complete this visit today, please reassign this task to another engineer via the Service Panel.</em>
            </div>

            <div style='text-align: center; margin: 25px 0;'>
                <a href='https://infinitycomputer.in/service-panel/amc.php' style='background: #0284c7; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Open AMC Service Panel</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 11px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. System Notification.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($engineer_email, $subject, $msg, $headers);
}

/**
 * Send Periodic Reminder Email (Every few hours during 48-hour window)
 */
function sendAmcPeriodicReminderEmail($conn, $visit_id) {
    $stmt = $conn->prepare("
        SELECT v.*, c.amc_number, c.customer_name, c.customer_phone, c.customer_address
        FROM amc_visits v
        JOIN amc_contracts c ON v.contract_id = c.id
        WHERE v.id = ?
    ");
    $stmt->bind_param("i", $visit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return false;
    $visit = $res->fetch_assoc();

    $engineer_name = $visit['assigned_engineer'];
    $engineer_email = getEngineerEmailByName($conn, $engineer_name);
    if (empty($engineer_email)) return false;

    $remCount = intval($visit['reminder_count']) + 1;
    $subject = "⏰ REMINDER (#{$remCount}): Pending AMC Visit Maintenance - Visit #{$visit['visit_number']} ({$visit['amc_number']})";
    $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";

    $msg = "
    <html>
    <head><title>AMC Visit Maintenance Reminder</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 650px; margin: 20px auto; padding: 25px; border: 2px solid #f59e0b; border-radius: 12px; background: #ffffff;'>
            <div style='background: #f59e0b; color: #ffffff; padding: 12px; text-align: center; font-size: 1.1rem; font-weight: bold; border-radius: 8px; margin-bottom: 20px;'>
                ⏰ PERIODIC REMINDER: AMC VISIT PENDING
            </div>

            <p>Hello <strong>{$engineer_name}</strong>,</p>
            <p>This is reminder <strong>#{$remCount}</strong> regarding your assigned AMC service visit that requires maintenance action.</p>

            <div style='background: #fffbeb; padding: 18px; border-radius: 8px; border: 1px solid #fef3c7; margin: 20px 0;'>
                <p style='margin: 5px 0;'><strong>AMC Number:</strong> <span style='color: #d97706; font-weight: bold;'>{$visit['amc_number']} (Visit #{$visit['visit_number']})</span></p>
                <p style='margin: 5px 0;'><strong>Scheduled Date:</strong> {$visit['scheduled_date']}</p>
                <p style='margin: 5px 0;'><strong>Customer Name:</strong> {$visit['customer_name']}</p>
                <p style='margin: 5px 0;'><strong>Phone:</strong> 📞 {$visit['customer_phone']}</p>
                <p style='margin: 5px 0;'><strong>Address:</strong> 📍 {$visit['customer_address']}</p>
                <p style='margin: 5px 0;'><strong>Current Status:</strong> <span style='font-weight: bold; color: #b45309;'>{$visit['status']}</span></p>
            </div>

            <div style='background: #fef2f2; padding: 15px; border-radius: 8px; border: 1px solid #fee2e2; margin-bottom: 20px; color: #991b1b;'>
                <strong>⚠️ Auto-Reassignment Warning:</strong><br>
                If no progress or changes are recorded within 48 hours of assignment, this task will <strong>automatically be reassigned to another engineer</strong>. If still unattended, it will escalate to Admin.<br><br>
                <em>If you are unable to perform this maintenance visit, please log in and reassign it to another engineer immediately.</em>
            </div>

            <div style='text-align: center; margin: 25px 0;'>
                <a href='https://infinitycomputer.in/service-panel/amc.php' style='background: #d97706; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Open AMC Service Panel &amp; Update Visit</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 11px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. Automated Reminder Worker.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($engineer_email, $subject, $msg, $headers);
}

/**
 * Ensure AMC schema tables and columns exist in database (self-healing migration)
 */
function ensureAmcSchemaColumns($conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        // 1. Ensure base tables exist if missing
        $conn->query("CREATE TABLE IF NOT EXISTS engineers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(150) DEFAULT NULL,
            position VARCHAR(50) DEFAULT 'staff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS amc_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            INDEX(contract_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            scheduled_day_email_sent TINYINT(1) DEFAULT 0,
            last_reminder_sent_at TIMESTAMP NULL DEFAULT NULL,
            reminder_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(contract_id),
            INDEX(assigned_engineer),
            INDEX(scheduled_date),
            INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            INDEX(engineer_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

        $conn->query("CREATE TABLE IF NOT EXISTS amc_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2. Ensure columns if tables existed beforehand
        $colsToAdd = [
            'amc_visits' => [
                'scheduled_day_email_sent' => "TINYINT(1) DEFAULT 0",
                'last_reminder_sent_at' => "TIMESTAMP NULL DEFAULT NULL",
                'reminder_count' => "INT DEFAULT 0",
                'escalation_level' => "INT DEFAULT 0",
                'is_inactive_reassigned' => "TINYINT(1) DEFAULT 0",
                'assignment_timestamp' => "TIMESTAMP NULL DEFAULT NULL",
                'last_activity_timestamp' => "TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
            ],
            'engineers' => [
                'position' => "VARCHAR(50) DEFAULT 'staff'",
                'role' => "ENUM('Super Admin', 'Admin/Accounts', 'Engineer') DEFAULT 'Engineer'",
                'status' => "ENUM('Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty') DEFAULT 'Active'",
                'current_ticket' => "VARCHAR(50) DEFAULT NULL",
                'phone' => "VARCHAR(20) DEFAULT NULL"
            ]
        ];

        foreach ($colsToAdd as $table => $cols) {
            $tCheck = $conn->query("SHOW TABLES LIKE '$table'");
            if (!$tCheck || $tCheck->num_rows === 0) continue;

            foreach ($cols as $col => $def) {
                $cCheck = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($cCheck && $cCheck->num_rows === 0) {
                    $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                }
            }
        }
    } catch (\Throwable $e) {
        // Silently catch schema check issues
    }
}

/**
 * Process Scheduled Day Notifications and Periodic Reminders
 */
function processAmcScheduledDayAndReminderEmails($conn) {
    ensureAmcSchemaColumns($conn);
    $todayStr = date('Y-m-d');
    $scheduledSent = 0;
    $remindersSent = 0;

    // 1. Send Scheduled Day Emails for visits scheduled on or before today that haven't received it
    $qSched = "
        SELECT id FROM amc_visits
        WHERE scheduled_date <= '$todayStr'
        AND scheduled_day_email_sent = 0
        AND status NOT IN ('COMPLETED', 'CANCELLED')
    ";
    $rSched = $conn->query($qSched);
    if ($rSched) {
        while ($row = $rSched->fetch_assoc()) {
            $vId = intval($row['id']);
            if (sendAmcScheduledDayEmail($conn, $vId)) {
                $conn->query("UPDATE amc_visits SET scheduled_day_email_sent = 1 WHERE id = {$vId}");
                $scheduledSent++;
            }
        }
    }

    // 2. Send Periodic Reminders Every 6 Hours during the 48-hour window for pending visits
    $qRem = "
        SELECT id, reminder_count FROM amc_visits
        WHERE scheduled_date <= '$todayStr'
        AND status IN ('ASSIGNED', 'ACCEPTED')
        AND (
            last_reminder_sent_at IS NULL 
            OR last_reminder_sent_at <= NOW() - INTERVAL 6 HOUR
        )
        AND (
            assignment_timestamp IS NULL
            OR assignment_timestamp >= NOW() - INTERVAL 48 HOUR
        )
    ";
    $rRem = $conn->query($qRem);
    if ($rRem) {
        while ($row = $rRem->fetch_assoc()) {
            $vId = intval($row['id']);
            if (sendAmcPeriodicReminderEmail($conn, $vId)) {
                $conn->query("UPDATE amc_visits SET last_reminder_sent_at = NOW(), reminder_count = reminder_count + 1 WHERE id = {$vId}");
                $remindersSent++;
            }
        }
    }

    return [
        'scheduled_emails_sent' => $scheduledSent,
        'reminder_emails_sent' => $remindersSent
    ];
}

/**
 * 48-Hour Automatic Inactivity Reassignment & Escalation System
 */
function checkAndApply48HourReassignment($conn) {
    ensureAmcSchemaColumns($conn);
    // Process scheduled day and periodic reminder emails first
    processAmcScheduledDayAndReminderEmails($conn);

    // Read configured reassignment window (default 48 hours)
    $hoursConfig = 48;
    $resSet = $conn->query("SELECT setting_value FROM amc_settings WHERE setting_key = 'reassignment_hours'");
    if ($resSet && $resSet->num_rows > 0) {
        $rowSet = $resSet->fetch_assoc();
        $hoursConfig = intval($rowSet['setting_value']);
        if ($hoursConfig <= 0) $hoursConfig = 48;
    }

    $reassignedCount = 0;
    $escalatedCount = 0;

    // Find visits where status is ASSIGNED or ACCEPTED and no activity for $hoursConfig hours
    $query = "
        SELECT v.*, c.amc_number, c.customer_name, c.customer_phone, c.customer_address
        FROM amc_visits v
        JOIN amc_contracts c ON v.contract_id = c.id
        WHERE v.status IN ('ASSIGNED', 'ACCEPTED')
        AND (
            v.last_activity_timestamp <= NOW() - INTERVAL {$hoursConfig} HOUR
            OR v.assignment_timestamp <= NOW() - INTERVAL {$hoursConfig} HOUR
        )
    ";

    $res = $conn->query($query);
    if (!$res) return ['reassigned' => 0, 'escalated' => 0];

    $availableEngs = getActiveEngineers($conn);
    $allEngNames = array_column($availableEngs, 'name');
    if (empty($allEngNames)) {
        $allEngNames = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh'];
    }

    while ($visit = $res->fetch_assoc()) {
        $visit_id = intval($visit['id']);
        $contract_id = intval($visit['contract_id']);
        $currentEng = $visit['assigned_engineer'];
        $escalationLevel = intval($visit['escalation_level']);

        if ($escalationLevel == 0) {
            // STEP 1: Reassign to next available engineer with lowest workload
            $candidates = array_diff($allEngNames, [$currentEng]);
            if (empty($candidates)) $candidates = $allEngNames;

            $bestEng = reset($candidates);
            $minWorkload = 99999;
            foreach ($candidates as $engCandidate) {
                $qW = "SELECT COUNT(*) as cnt FROM amc_visits WHERE assigned_engineer = '" . $conn->real_escape_string($engCandidate) . "' AND status NOT IN ('COMPLETED', 'CANCELLED')";
                $rW = $conn->query($qW);
                $cnt = ($rW && $rowW = $rW->fetch_assoc()) ? intval($rowW['cnt']) : 0;
                if ($cnt < $minWorkload) {
                    $minWorkload = $cnt;
                    $bestEng = $engCandidate;
                }
            }

            // Mark previous assignment inactive
            $conn->query("UPDATE amc_assignments SET status = 'Reassigned', expired_at = NOW() WHERE visit_id = {$visit_id} AND status = 'Active'");

            // Update visit record: reset timestamps & scheduled_day_email_sent for new engineer
            $stmtUpd = $conn->prepare("
                UPDATE amc_visits 
                SET assigned_engineer = ?, status = 'ASSIGNED', escalation_level = 1, is_inactive_reassigned = 1, 
                    assignment_timestamp = NOW(), last_activity_timestamp = NOW(), scheduled_day_email_sent = 0, 
                    last_reminder_sent_at = NULL, reminder_count = 0 
                WHERE id = ?
            ");
            $stmtUpd->bind_param("si", $bestEng, $visit_id);
            $stmtUpd->execute();

            // Record new assignment
            $stmtAss = $conn->prepare("INSERT INTO amc_assignments (visit_id, contract_id, engineer_name, previous_engineer, assigned_by, assignment_reason, status) VALUES (?, ?, ?, ?, 'System 48h Auto-Reassignment', 'Inactivity Reassignment', 'Active')");
            $stmtAss->bind_param("iiss", $visit_id, $contract_id, $bestEng, $currentEng);
            $stmtAss->execute();

            logAmcAudit($conn, $contract_id, $visit_id, '48h Auto-Reassign', 'System Cron', 'System', "Reassigned from {$currentEng} to {$bestEng} due to 48 hours of inactivity");

            // Send assignment email to the NEW engineer
            sendAmcEngineerAssignmentEmail($conn, $visit_id, true);

            // Send notification email to previous engineer and admin
            $subject = "AMC Visit Auto-Reassigned (48h Inactivity) - Visit #{$visit['visit_number']} ({$visit['amc_number']})";
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";
            $msg = "
            <html>
            <body style='font-family:Arial,sans-serif;'>
                <div style='max-width:600px; padding:20px; border:1px solid #3b82f6; border-top:5px solid #3b82f6; border-radius:8px;'>
                    <h2 style='color:#1d4ed8;'>🔄 AMC Visit Reassigned</h2>
                    <p>Engineer <strong>{$currentEng}</strong> had no activity for {$hoursConfig} hours.</p>
                    <p>Visit <strong>#{$visit['visit_number']}</strong> for <strong>{$visit['amc_number']} ({$visit['customer_name']})</strong> has been automatically reassigned to <strong>{$bestEng}</strong>.</p>
                    <p>Customer Address: {$visit['customer_address']}</p>
                </div>
            </body>
            </html>
            ";
            $prevEmail = getEngineerEmailByName($conn, $currentEng);
            if (!empty($prevEmail)) @mail($prevEmail, $subject, $msg, $headers);
            @mail('icc@infinitycomputer.in', $subject, $msg, $headers);
            @mail('suraj@staff.infinitycomputer.in', $subject, $msg, $headers);

            $reassignedCount++;

        } elseif ($escalationLevel >= 1) {
            // STEP 2: Reassigned engineer also inactive for another 48 hours -> ESCALATE TO ADMIN
            $adminEng = 'icc';
            $stmtEsc = $conn->prepare("UPDATE amc_visits SET assigned_engineer = ?, status = 'OVERDUE', escalation_level = 2, last_activity_timestamp = NOW() WHERE id = ?");
            $stmtEsc->bind_param("si", $adminEng, $visit_id);
            $stmtEsc->execute();

            $conn->query("UPDATE amc_assignments SET status = 'Escalated' WHERE visit_id = {$visit_id} AND status = 'Active'");

            logAmcAudit($conn, $contract_id, $visit_id, 'Admin Escalation', 'System Cron', 'System', "ESCALATED TO ADMIN: Engineer {$currentEng} failed to respond within 48 hours after reassignment.");

            // Email Urgent Escalation Notice to Admins
            $subject = "🚨 URGENT ADMIN ESCALATION: AMC Visit #{$visit['visit_number']} ({$visit['amc_number']})";
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";
            $msg = "
            <html>
            <body style='font-family:Arial,sans-serif;'>
                <div style='max-width:600px; padding:20px; border:1px solid #ef4444; border-top:5px solid #ef4444; border-radius:8px;'>
                    <h2 style='color:#b91c1c;'>⚠️ Urgent AMC Admin Escalation</h2>
                    <p>AMC Visit <strong>#{$visit['visit_number']}</strong> for contract <strong>{$visit['amc_number']} ({$visit['customer_name']})</strong> has failed multiple engineer assignments with no activity for over {$hoursConfig} hours.</p>
                    <p><strong>Last Assigned Engineer:</strong> {$currentEng}</p>
                    <p><strong>Scheduled Date:</strong> {$visit['scheduled_date']}</p>
                    <p><strong>Customer Address:</strong> {$visit['customer_address']}</p>
                    <p>The visit is now assigned to <strong>Admin</strong>. Please open AMC Management in the Admin Panel to manually assign an engineer or complete the visit.</p>
                </div>
            </body>
            </html>
            ";
            @mail('icc@infinitycomputer.in', $subject, $msg, $headers);
            @mail('suraj@staff.infinitycomputer.in', $subject, $msg, $headers);

            $escalatedCount++;
        }
    }

    return [
        'reassigned' => $reassignedCount,
        'escalated' => $escalatedCount
    ];
}
?>
