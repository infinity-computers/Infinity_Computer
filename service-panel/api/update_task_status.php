<?php
include __DIR__ . '/../auth_guard.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $task_id = trim($_POST['task_id'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($task_id) || empty($status)) {
        echo json_encode(['status' => 'error', 'message' => 'Task ID and Status are required fields']);
        exit;
    }

    $allowed_statuses = ['Pending', 'Accepted', 'In Progress', 'On Hold', 'Completed', 'Rejected'];
    if (!in_array($status, $allowed_statuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid task status']);
        exit;
    }

    $done_by = $_SESSION['staff_email'] ?? 'System';

    $conn->begin_transaction();
    try {
        // Fetch current status
        $stmt_curr = $conn->prepare("SELECT status FROM tasks WHERE task_id = ?");
        $stmt_curr->bind_param("s", $task_id);
        $stmt_curr->execute();
        $curr_res = $stmt_curr->get_result()->fetch_assoc();

        if (!$curr_res) {
            echo json_encode(['status' => 'error', 'message' => 'Task not found']);
            exit;
        }

        $prev_status = $curr_res['status'];

        // If status remains the same and remarks are empty, no need to update
        if ($prev_status === $status && empty($remarks)) {
            echo json_encode(['status' => 'success', 'message' => 'No changes made']);
            exit;
        }

        // Update status in tasks table
        if ($status === 'Completed') {
            $stmt_upd = $conn->prepare("UPDATE tasks SET status = ?, completed_at = CURRENT_TIMESTAMP WHERE task_id = ?");
        } else {
            $stmt_upd = $conn->prepare("UPDATE tasks SET status = ?, completed_at = NULL WHERE task_id = ?");
        }
        $stmt_upd->bind_param("ss", $status, $task_id);
        $stmt_upd->execute();

        // Save log
        $log_action = "Status Updated";
        $log_remarks = "Status changed from '{$prev_status}' to '{$status}'";
        if (!empty($remarks)) {
            $log_remarks .= ". Remarks: " . $remarks;
        }

        $stmt_log = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("ssss", $task_id, $log_action, $log_remarks, $done_by);
        $stmt_log->execute();

        // Save comments if remarks are present
        if (!empty($remarks)) {
            $stmt_comm = $conn->prepare("INSERT INTO task_comments (task_id, comment, comment_by) VALUES (?, ?, ?)");
            $stmt_comm->bind_param("sss", $task_id, $remarks, $done_by);
            $stmt_comm->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Task status updated successfully']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
