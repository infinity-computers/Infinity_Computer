<?php
/**
 * AMC Scheduled Cron Script
 * Automated 48-Hour Inactivity Reassignment & Admin Escalation Job
 * Example Cron: 0 * * * * php /path/to/service-panel/api/cron_amc_reassignment.php
 */

if (!isset($_SERVER['SERVER_NAME'])) $_SERVER['SERVER_NAME'] = 'localhost';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/amc_helper.php';

header('Content-Type: application/json');

try {
    $result = checkAndApply48HourReassignment($conn);
    
    echo json_encode([
        'status' => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'reassigned_visits' => $result['reassigned'],
        'escalated_visits' => $result['escalated'],
        'message' => 'Processed 48-hour AMC inactivity checks successfully.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Cron Execution Error: ' . $e->getMessage()
    ]);
}
?>
