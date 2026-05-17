<?php
include __DIR__ . '/../auth_guard.php';
require_once '../../config/db.php';
require_once 'task_email_helper.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $task_id = trim($_POST['task_id'] ?? '');
    $new_assignee = trim($_POST['assigned_to'] ?? '');

    if (empty($task_id) || empty($new_assignee)) {
        echo json_encode(['status' => 'error', 'message' => 'Task ID and Assigned To are required fields']);
        exit;
    }

    $allowed_engineers = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh', 'Om', 'Jatin'];
    if (!in_array($new_assignee, $allowed_engineers)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid engineer selection']);
        exit;
    }

    $reassigned_by = $_SESSION['staff_email'] ?? 'System';

    $conn->begin_transaction();
    try {
        // Fetch current task details
        $stmt_curr = $conn->prepare("SELECT title, description, priority, assigned_to, due_date FROM tasks WHERE task_id = ?");
        $stmt_curr->bind_param("s", $task_id);
        $stmt_curr->execute();
        $task = $stmt_curr->get_result()->fetch_assoc();

        if (!$task) {
            echo json_encode(['status' => 'error', 'message' => 'Task not found']);
            exit;
        }

        $prev_assignee = $task['assigned_to'];

        if ($prev_assignee === $new_assignee) {
            echo json_encode(['status' => 'success', 'message' => 'Task is already assigned to ' . $new_assignee]);
            exit;
        }

        // Update assigned_to in tasks table
        $stmt_upd = $conn->prepare("UPDATE tasks SET assigned_to = ? WHERE task_id = ?");
        $stmt_upd->bind_param("ss", $new_assignee, $task_id);
        $stmt_upd->execute();

        // Save Assignment Record
        $stmt_assign = $conn->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by) VALUES (?, ?, ?)");
        $stmt_assign->bind_param("sss", $task_id, $new_assignee, $reassigned_by);
        $stmt_assign->execute();

        // Save Log
        $reassigned_at = date('Y-m-d H:i:s');
        $log_action = "Task Reassigned";
        $log_remarks = "Task re-assigned from {$prev_assignee} to {$new_assignee} by {$reassigned_by}";
        $stmt_log = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("ssss", $task_id, $log_action, $log_remarks, $reassigned_by);
        $stmt_log->execute();

        $conn->commit();

        // Send email notification to new engineer
        $sent_email = sendTaskReassignmentEmail(
            $task_id,
            $task['title'],
            $task['description'],
            $reassigned_by,
            $task['priority'],
            $task['due_date'],
            $new_assignee,
            $prev_assignee,
            $reassigned_at
        );

        if ($sent_email) {
            $stmt_log_mail = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, 'Email Sent', 'Reassignment email notification sent to {$new_assignee}', 'System')");
            $stmt_log_mail->bind_param("s", $task_id);
            $stmt_log_mail->execute();
        }

        echo json_encode(['status' => 'success', 'message' => 'Task successfully reassigned to ' . $new_assignee]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
