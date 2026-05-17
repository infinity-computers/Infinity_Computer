<?php
include __DIR__ . '/../auth_guard.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

// Check if a single task is requested
$task_id = $_GET['task_id'] ?? '';

if (!empty($task_id)) {
    try {
        // Fetch task details
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE task_id = ?");
        $stmt->bind_param("s", $task_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();

        if (!$task) {
            echo json_encode(['status' => 'error', 'message' => 'Task not found']);
            exit;
        }

        // Fetch task comments
        $stmt_comm = $conn->prepare("SELECT * FROM task_comments WHERE task_id = ? ORDER BY created_at ASC");
        $stmt_comm->bind_param("s", $task_id);
        $stmt_comm->execute();
        $comments = $stmt_comm->get_result()->fetch_all(MYSQLI_ASSOC);

        // Fetch task logs (Timeline)
        $stmt_logs = $conn->prepare("SELECT * FROM task_logs WHERE task_id = ? ORDER BY created_at ASC");
        $stmt_logs->bind_param("s", $task_id);
        $stmt_logs->execute();
        $logs = $stmt_logs->get_result()->fetch_all(MYSQLI_ASSOC);

        // Fetch assignments history
        $stmt_ass = $conn->prepare("SELECT * FROM task_assignments WHERE task_id = ? ORDER BY assigned_at ASC");
        $stmt_ass->bind_param("s", $task_id);
        $stmt_ass->execute();
        $assignments = $stmt_ass->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'task' => $task,
                'comments' => $comments,
                'logs' => $logs,
                'assignments' => $assignments
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Otherwise, handle list query
try {
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $assigned_to = $_GET['assigned_to'] ?? '';
    $due_date_start = $_GET['due_date_start'] ?? '';
    $due_date_end = $_GET['due_date_end'] ?? '';
    
    $sort_by = $_GET['sort_by'] ?? 'created_at';
    $sort_order = $_GET['sort_order'] ?? 'DESC';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, intval($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    // Build query conditions
    $conditions = [];
    $params = [];
    $types = "";

    if ($search !== '') {
        $conditions[] = "(title LIKE ? OR task_id LIKE ? OR description LIKE ? OR customer_name LIKE ? OR phone LIKE ?)";
        $search_val = "%" . $search . "%";
        $params[] = $search_val;
        $params[] = $search_val;
        $params[] = $search_val;
        $params[] = $search_val;
        $params[] = $search_val;
        $types .= "sssss";
    }

    if ($status !== '') {
        $conditions[] = "status = ?";
        $params[] = $status;
        $types .= "s";
    }

    if ($priority !== '') {
        $conditions[] = "priority = ?";
        $params[] = $priority;
        $types .= "s";
    }

    if ($assigned_to !== '') {
        $conditions[] = "assigned_to = ?";
        $params[] = $assigned_to;
        $types .= "s";
    }

    if ($due_date_start !== '') {
        $conditions[] = "due_date >= ?";
        $params[] = $due_date_start;
        $types .= "s";
    }

    if ($due_date_end !== '') {
        $conditions[] = "due_date <= ?";
        $params[] = $due_date_end;
        $types .= "s";
    }

    $where_clause = "";
    if (count($conditions) > 0) {
        $where_clause = "WHERE " . implode(" AND ", $conditions);
    }

    // Validate sorting parameters
    $allowed_sorts = ['created_at', 'due_date', 'priority', 'status', 'task_id'];
    if (!in_array($sort_by, $allowed_sorts)) {
        $sort_by = 'created_at';
    }
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

    // Count query for pagination metadata
    $count_query = "SELECT COUNT(*) as total FROM tasks $where_clause";
    if (count($params) > 0) {
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total_records = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $res = $conn->query($count_query);
        $total_records = $res->fetch_assoc()['total'];
    }

    // Main records query
    $query = "SELECT * FROM tasks $where_clause ORDER BY $sort_by $sort_order LIMIT ? OFFSET ?";
    
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($query);
    if (count($params) > 2) {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'tasks' => $tasks,
            'pagination' => [
                'total' => intval($total_records),
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total_records / $limit)
            ]
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
