<?php
// Since this is a cron job, it can be run from the command line or via HTTP.
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/task_email_helper.php';

header('Content-Type: application/json');

// Threshold in hours (can be customized via query parameter)
$hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;
if ($hours <= 0) $hours = 24;

try {
    // Find all active (non-completed, non-rejected) tasks that haven't been updated for $hours hours
    $stmt = $conn->prepare("SELECT task_id, title, status, assigned_to, updated_at 
                            FROM tasks 
                            WHERE status NOT IN ('Completed', 'Rejected') 
                            AND updated_at < DATE_SUB(NOW(), INTERVAL ? HOUR)");
    $stmt->bind_param("i", $hours);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $reminders_sent = 0;
    $errors = [];

    foreach ($tasks as $task) {
        $task_id = $task['task_id'];
        $assignee = $task['assigned_to'];
        
        // Check if we already sent a reminder to this engineer for this task in the last 24 hours
        $stmt_chk = $conn->prepare("SELECT id FROM task_reminders WHERE task_id = ? AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt_chk->bind_param("s", $task_id);
        $stmt_chk->execute();
        $already_sent = $stmt_chk->get_result()->fetch_assoc();

        if ($already_sent) {
            continue; // Skip to avoid spamming
        }

        // Send reminder email
        $sent = sendTaskReminderEmail($task_id, $task['title'], $task['status'], $hours, $assignee);

        if ($sent) {
            $reminders_sent++;
            
            // Insert into task_reminders table
            $emails = getEngineerEmailMap();
            $email_address = $emails[$assignee] ?? 'unknown';
            $stmt_ins = $conn->prepare("INSERT INTO task_reminders (task_id, reminder_sent_to) VALUES (?, ?)");
            $stmt_ins->bind_param("ss", $task_id, $email_address);
            $stmt_ins->execute();

            // Log this reminder in task_logs (Timeline)
            $log_action = "Reminder Sent";
            $log_remarks = "Automated inactivity reminder email sent to {$assignee} (inactive for {$hours}+ hours)";
            $stmt_log = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, ?, ?, 'System')");
            $stmt_log->bind_param("sss", $task_id, $log_action, $log_remarks);
            $stmt_log->execute();
        } else {
            $errors[] = "Failed to send email to {$assignee} for task {$task_id}";
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => "Cron reminder run completed",
        'data' => [
            'tasks_checked' => count($tasks),
            'reminders_sent' => $reminders_sent,
            'errors' => $errors
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
