<?php
/**
 * CRON SCRIPT: Run this every 1 hour to send reminders to engineers
 * Example Cron: 0 * * * * php /path/to/service-panel/api/cron_reminders.php
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/email_helper.php';

// Header
header('Content-Type: application/json');

try {
    // 1. Find services that are active (not Completed, Delivered, or Cancelled)
    // 2. Have an assigned engineer
    // 3. Haven't been updated in at least 1 hour
    $query = "
        SELECT s.*, c.name as customer_name, e.email as eng_email
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        LEFT JOIN engineers e ON s.assigned_engineer = e.name
        WHERE s.status NOT IN ('Completed', 'Delivered', 'Cancelled') 
        AND s.assigned_engineer IS NOT NULL 
        AND s.assigned_engineer != ''
        AND s.updated_at <= (NOW() - INTERVAL 1 HOUR)
    ";
    
    $res = $conn->query($query);

    if (!$res) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $sent_count = 0;
    while ($svc = $res->fetch_assoc()) {
        $eng_name = $svc['assigned_engineer'];
        $eng_email = $svc['eng_email'];

        if ($eng_email) {
            // Send the reminder
            $success = sendEngineerReminderEmail(
                $eng_email, 
                $eng_name, 
                $svc['service_id'], 
                $svc['customer_name'], 
                $svc['device_name'], 
                $svc['updated_at']
            );
            
            if ($success) {
                $sent_count++;
            }
        }
    }

    echo json_encode([
        'status' => 'success', 
        'timestamp' => date('Y-m-d H:i:s'),
        'reminders_sent' => $sent_count,
        'message' => "Successfully processed active tasks. sent {$sent_count} reminders."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>
