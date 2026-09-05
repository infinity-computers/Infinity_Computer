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

    // Self-healing: Ensure status column exists in engineers table to prevent uncaught exceptions
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM `engineers` LIKE 'status'");
        if ($checkCol && $checkCol->num_rows == 0) {
            @$conn->query("ALTER TABLE `engineers` ADD COLUMN `status` ENUM('Active', 'On Call', 'In Transit', 'On Job', 'On Hold', 'Off Duty') DEFAULT 'Active'");
        }
    } catch (\Throwable $e) {
        // Silently ignore schema modification error
    }

    try {
        $query = "SELECT id, name, email, position, status FROM engineers WHERE (status IS NULL OR status != 'Off Duty') ORDER BY name ASC";
        $res = $conn->query($query);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                if ($row['name'] !== 'icc') { // Exclude super admin from auto round-robin unless assigned manually
                    $engineers[] = $row;
                }
            }
        }
    } catch (\Throwable $e) {
        // Fallback query if status column fails or is not accessible
        try {
            $fallbackQuery = "SELECT id, name, email, position FROM engineers ORDER BY name ASC";
            $res = $conn->query($fallbackQuery);
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    if ($row['name'] !== 'icc') {
                        $row['status'] = 'Active';
                        $engineers[] = $row;
                    }
                }
            }
        } catch (\Throwable $e2) {
            // Ignore, proceed to array fallback below
        }
    }

    if (empty($engineers)) {
        $fallback = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh'];
        foreach ($fallback as $name) {
            $engineers[] = ['name' => $name, 'email' => '', 'position' => 'staff', 'status' => 'Active'];
        }
    }
    return $engineers;
}

/**
 * Audit Log Helper
 */
function logAmcAudit($conn, $contract_id, $visit_id, $action, $performed_by, $role = 'System', $details = '') {
    $stmt = $conn->prepare("INSERT INTO amc_audit_logs (contract_id, visit_id, action, performed_by, role, details) VALUES (?, ?, ?, ?, ?, ?)");
    $cid = $contract_id ? intval($contract_id) : null;
    $vid = $visit_id ? intval($visit_id) : null;
    $stmt->bind_param("iissss", $cid, $vid, $action, $performed_by, $role, $details);
    $stmt->execute();
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
 * 48-Hour Automatic Inactivity Reassignment & Escalation System
 */
function checkAndApply48HourReassignment($conn) {
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
        $visit_id = $visit['id'];
        $contract_id = $visit['contract_id'];
        $currentEng = $visit['assigned_engineer'];
        $escalationLevel = intval($visit['escalation_level']);

        if ($escalationLevel == 0) {
            // STEP 1: Reassign to next available engineer
            // Select candidate with minimum current assigned visits excluding currentEng
            $candidates = array_diff($allEngNames, [$currentEng]);
            if (empty($candidates)) $candidates = $allEngNames;

            // Find lowest workload engineer
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

            // Update visit record
            $stmtUpd = $conn->prepare("UPDATE amc_visits SET assigned_engineer = ?, status = 'ASSIGNED', escalation_level = 1, is_inactive_reassigned = 1, assignment_timestamp = NOW(), last_activity_timestamp = NOW() WHERE id = ?");
            $stmtUpd->bind_param("si", $bestEng, $visit_id);
            $stmtUpd->execute();

            // Record new assignment
            $stmtAss = $conn->prepare("INSERT INTO amc_assignments (visit_id, contract_id, engineer_name, previous_engineer, assigned_by, assignment_reason, status) VALUES (?, ?, ?, ?, 'System 48h Auto-Reassignment', 'Inactivity Reassignment', 'Active')");
            $stmtAss->bind_param("iiss", $visit_id, $contract_id, $bestEng, $currentEng);
            $stmtAss->execute();

            logAmcAudit($conn, $contract_id, $visit_id, '48h Auto-Reassign', 'System Cron', 'System', "Reassigned from {$currentEng} to {$bestEng} due to 48 hours of inactivity");

            // Email notifications
            $subject = "AMC Visit Reassignment Alert - Visit #{$visit['visit_number']} ({$visit['amc_number']})";
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";
            $msg = "
            <html>
            <body style='font-family:Arial,sans-serif;'>
                <div style='max-width:600px; padding:20px; border:1px solid #3b82f6; border-top:5px solid #3b82f6; border-radius:8px;'>
                    <h2 style='color:#1d4ed8;'>🔄 AMC Visit Reassigned</h2>
                    <p>Engineer <strong>{$currentEng}</strong> had no activity for {$hoursConfig} hours.</p>
                    <p>Visit <strong>#{$visit['visit_number']}</strong> for <strong>{$visit['amc_number']} ({$visit['customer_name']})</strong> has been reassigned to <strong>{$bestEng}</strong>.</p>
                    <p>Customer Address: {$visit['customer_address']}</p>
                </div>
            </body>
            </html>
            ";
            @mail('icc@infinitycomputer.in', $subject, $msg, $headers);
            @mail('suraj@staff.infinitycomputer.in', $subject, $msg, $headers);

            $reassignedCount++;

        } elseif ($escalationLevel >= 1) {
            // STEP 2: Reassigned engineer also inactive for another 48 hours -> ESCALATE TO ADMIN
            $stmtEsc = $conn->prepare("UPDATE amc_visits SET status = 'OVERDUE', escalation_level = 2, last_activity_timestamp = NOW() WHERE id = ?");
            $stmtEsc->bind_param("i", $visit_id);
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
                    <p>AMC Visit <strong>#{$visit['visit_number']}</strong> for contract <strong>{$visit['amc_number']} ({$visit['customer_name']})</strong> has failed multiple assignments with no activity for over {$hoursConfig} hours.</p>
                    <p><strong>Assigned Engineer:</strong> {$currentEng}</p>
                    <p><strong>Scheduled Date:</strong> {$visit['scheduled_date']}</p>
                    <p>Please open AMC Management in the Admin Panel to manually assign an engineer or resolve.</p>
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
