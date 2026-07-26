<?php
/**
 * CRON SCRIPT: Run this to send reminders and stuck alerts
 * Example Cron: 0 * * * * php /path/to/service-panel/api/cron_reminders.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/email_helper.php';

// Engineer contact mapping
$engineer_emails = [
    'Suraj' => 'suraj@staff.infinitycomputer.in',
    'Akshar' => 'akshar@staff.infinitycomputer.in',
    'Karan' => 'karan@staff.infinitycomputer.in',
    'Rahul' => 'rahul@staff.infinitycomputer.in',
    'Paresh' => 'paresh@staff.infinitycomputer.in'
];

header('Content-Type: application/json');

try {
    $reminders_sent = 0;
    $assigned_alerts_sent = 0;
    $verify_alerts_sent = 0;

    // --- ALERT 1: Tickets stuck in "Assigned" for > 2 hours without engineer response ---
    $q_assigned = "
        SELECT s.*, c.name as customer_name 
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE s.status = 'Assigned'
        AND s.first_response_at IS NULL
        AND s.updated_at <= (NOW() - INTERVAL 2 HOUR)
    ";
    $res_assigned = $conn->query($q_assigned);
    while ($svc = $res_assigned->fetch_assoc()) {
        $eng_name = $svc['assigned_engineer'];
        $eng_email = $engineer_emails[$eng_name] ?? '';
        
        // Subject & Body
        $subject = "STUCK ALERT: Ticket {$svc['service_id']} is stuck in Assigned status";
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";
        
        $message = "
        <html>
        <body style='font-family:Arial,sans-serif; line-height:1.6; color:#333;'>
            <div style='max-width:600px; margin:0 auto; padding:20px; border:1px solid #fee2e2; border-top:5px solid #ef4444; border-radius:8px;'>
                <h2 style='color:#ef4444;'>⚠️ Assigned Ticket Stuck Alert</h2>
                <p>Hello <strong>{$eng_name}</strong>,</p>
                <p>The following ticket has been stuck in <strong>Assigned</strong> status for more than 2 hours without a response.</p>
                <div style='background:#fef2f2; padding:15px; border-radius:6px; border:1px solid #fee2e2; margin:20px 0;'>
                    <p style='margin:5px 0;'><strong>Service ID:</strong> {$svc['service_id']}</p>
                    <p style='margin:5px 0;'><strong>Customer:</strong> {$svc['customer_name']}</p>
                    <p style='margin:5px 0;'><strong>Device:</strong> {$svc['device_name']}</p>
                    <p style='margin:5px 0;'><strong>Assigned At:</strong> {$svc['updated_at']}</p>
                </div>
                <p>Please log in to the Service Panel, start diagnosis, and update status immediately.</p>
                <p style='font-size:0.85rem; color:#64748b;'>CC: Admin / Super Admin</p>
            </div>
        </body>
        </html>
        ";
        
        // Send email to engineer, copy to Admins
        if (!empty($eng_email)) {
            @mail($eng_email, $subject, $message, $headers);
        }
        @mail('suraj@staff.infinitycomputer.in', $subject, $message, $headers);
        @mail('icc@infinitycomputer.in', $subject, $message, $headers);
        $assigned_alerts_sent++;
    }

    // --- ALERT 2: Tickets stuck in "Engineer Submitted" for > 4 hours without admin verification ---
    $q_verify = "
        SELECT s.*, c.name as customer_name 
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE s.engineer_submitted = 1
        AND s.verified_by_admin IS NULL
        AND s.updated_at <= (NOW() - INTERVAL 4 HOUR)
    ";
    $res_verify = $conn->query($q_verify);
    while ($svc = $res_verify->fetch_assoc()) {
        $subject = "STUCK ALERT: Ticket {$svc['service_id']} requires Admin Verification";
        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Infinity Computer <noreply@infinitycomputer.in>\r\n";
        
        $message = "
        <html>
        <body style='font-family:Arial,sans-serif; line-height:1.6; color:#333;'>
            <div style='max-width:600px; margin:0 auto; padding:20px; border:1px solid #fef3c7; border-top:5px solid #f59e0b; border-radius:8px;'>
                <h2 style='color:#d97706;'>⚠️ Pending Verification Alert</h2>
                <p>Hello Admin,</p>
                <p>The following ticket has been waiting in <strong>Engineer Submitted</strong> status for more than 4 hours without verification.</p>
                <div style='background:#fffbeb; padding:15px; border-radius:6px; border:1px solid #fef3c7; margin:20px 0;'>
                    <p style='margin:5px 0;'><strong>Service ID:</strong> {$svc['service_id']}</p>
                    <p style='margin:5px 0;'><strong>Engineer:</strong> {$svc['assigned_engineer']}</p>
                    <p style='margin:5px 0;'><strong>Customer:</strong> {$svc['customer_name']}</p>
                    <p style='margin:5px 0;'><strong>Device:</strong> {$svc['device_name']}</p>
                    <p style='margin:5px 0;'><strong>Submitted At:</strong> {$svc['updated_at']}</p>
                </div>
                <p>Please review the submitted work and process verification / billing details in the Admin Panel.</p>
            </div>
        </body>
        </html>
        ";
        
        @mail('suraj@staff.infinitycomputer.in', $subject, $message, $headers);
        @mail('icc@infinitycomputer.in', $subject, $message, $headers);
        $verify_alerts_sent++;
    }

    // --- STANDARD ALERT: General Idle Ticket Reminders (updated_at <= NOW() - 1 Hour) ---
    $q_idle = "
        SELECT s.*, c.name as customer_name 
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        WHERE s.status NOT IN ('Completed', 'Delivered', 'Cancelled') 
        AND s.assigned_engineer IS NOT NULL 
        AND s.assigned_engineer != ''
        AND s.updated_at <= (NOW() - INTERVAL 1 HOUR)
    ";
    $res_idle = $conn->query($q_idle);
    while ($svc = $res_idle->fetch_assoc()) {
        $eng_name = $svc['assigned_engineer'];
        $eng_email = $engineer_emails[$eng_name] ?? '';

        if ($eng_email) {
            $success = sendEngineerReminderEmail(
                $eng_email, 
                $eng_name, 
                $svc['service_id'], 
                $svc['customer_name'], 
                $svc['device_name'], 
                $svc['updated_at']
            );
            if ($success) {
                $reminders_sent++;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'stuck_assigned_alerts' => $assigned_alerts_sent,
        'stuck_verification_alerts' => $verify_alerts_sent,
        'standard_reminders_sent' => $reminders_sent,
        'message' => 'Processed cron alerts successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
