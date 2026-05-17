<?php
include __DIR__ . '/../auth_guard.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $task_id = trim($_POST['task_id'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    if (empty($task_id) || empty($comment)) {
        echo json_encode(['status' => 'error', 'message' => 'Task ID and Comment are required fields']);
        exit;
    }

    $comment_by = $_SESSION['staff_email'] ?? 'System';

    $conn->begin_transaction();
    try {
        // Fetch task details to verify task exists
        $stmt_check = $conn->prepare("SELECT id FROM tasks WHERE task_id = ?");
        $stmt_check->bind_param("s", $task_id);
        $stmt_check->execute();
        if (!$stmt_check->get_result()->fetch_assoc()) {
            echo json_encode(['status' => 'error', 'message' => 'Task not found']);
            exit;
        }

        // Insert Comment
        $stmt_comm = $conn->prepare("INSERT INTO task_comments (task_id, comment, comment_by) VALUES (?, ?, ?)");
        $stmt_comm->bind_param("sss", $task_id, $comment, $comment_by);
        $stmt_comm->execute();

        // Save Log
        $log_action = "Comment Added";
        $log_remarks = "New comment added: " . (strlen($comment) > 50 ? substr($comment, 0, 50) . "..." : $comment);
        $stmt_log = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("ssss", $task_id, $log_action, $log_remarks, $comment_by);
        $stmt_log->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Comment added successfully']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
