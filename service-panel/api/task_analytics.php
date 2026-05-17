<?php
include __DIR__ . '/../auth_guard.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

try {
    // 1. Core counters
    $counters = [
        'total' => 0,
        'pending' => 0,
        'accepted' => 0,
        'in_progress' => 0,
        'on_hold' => 0,
        'completed' => 0,
        'rejected' => 0,
        'overdue' => 0,
        'reassigned' => 0
    ];

    // Status breakdowns
    $status_res = $conn->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status");
    while ($row = $status_res->fetch_assoc()) {
        $counters['total'] += $row['count'];
        $status_key = strtolower(str_replace(' ', '_', $row['status']));
        $counters[$status_key] = intval($row['count']);
    }

    // Overdue tasks count (status is not Completed and due_date is before today)
    $overdue_res = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status != 'Completed' AND due_date IS NOT NULL AND due_date < CURRENT_DATE()");
    if ($overdue_res) {
        $counters['overdue'] = intval($overdue_res->fetch_assoc()['count']);
    }

    // Reassignment actions log count
    $reassign_res = $conn->query("SELECT COUNT(*) as count FROM task_logs WHERE action = 'Task Reassigned'");
    if ($reassign_res) {
        $counters['reassigned'] = intval($reassign_res->fetch_assoc()['count']);
    }

    // 2. Engineer workloads & performance
    $engineer_stats = [];
    $eng_res = $conn->query("SELECT assigned_to, 
                            COUNT(*) as total_tasks,
                            SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed_tasks,
                            SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending_tasks,
                            SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                            SUM(CASE WHEN status='On Hold' THEN 1 ELSE 0 END) as on_hold_tasks,
                            SUM(CASE WHEN status='Accepted' THEN 1 ELSE 0 END) as accepted_tasks,
                            SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected_tasks
                            FROM tasks GROUP BY assigned_to");
    
    $most_active_engineer = "None";
    $max_tasks = 0;

    while ($row = $eng_res->fetch_assoc()) {
        $eng_name = $row['assigned_to'];
        $tot = intval($row['total_tasks']);
        $comp = intval($row['completed_tasks']);
        
        $rate = $tot > 0 ? round(($comp / $tot) * 100, 1) : 0;
        
        $engineer_stats[] = [
            'engineer' => $eng_name,
            'total' => $tot,
            'completed' => $comp,
            'pending' => intval($row['pending_tasks']),
            'in_progress' => intval($row['in_progress_tasks']),
            'on_hold' => intval($row['on_hold_tasks']),
            'accepted' => intval($row['accepted_tasks']),
            'rejected' => intval($row['rejected_tasks']),
            'completion_rate' => $rate
        ];

        if ($tot > $max_tasks) {
            $max_tasks = $tot;
            $most_active_engineer = $eng_name;
        }
    }

    // 3. Average completion time (in hours)
    $avg_res = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours FROM tasks WHERE status = 'Completed' AND completed_at IS NOT NULL");
    $avg_completion_hours = 0;
    if ($avg_res) {
        $avg_row = $avg_res->fetch_assoc();
        $avg_completion_hours = $avg_row['avg_hours'] !== null ? round(floatval($avg_row['avg_hours']), 1) : 0;
    }

    // 4. Monthly task trends
    $monthly_trends = [];
    $trend_res = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                              COUNT(*) as count, 
                              SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed 
                              FROM tasks 
                              GROUP BY month 
                              ORDER BY month ASC 
                              LIMIT 12");
    while ($row = $trend_res->fetch_assoc()) {
        $monthly_trends[] = [
            'month' => $row['month'],
            'total' => intval($row['count']),
            'completed' => intval($row['completed'])
        ];
    }

    // 5. Delayed/Overdue tasks list
    $delayed_tasks = [];
    $delayed_res = $conn->query("SELECT task_id, title, assigned_to, due_date, status, priority FROM tasks WHERE status != 'Completed' AND due_date IS NOT NULL AND due_date < CURRENT_DATE() ORDER BY due_date ASC LIMIT 5");
    while ($row = $delayed_res->fetch_assoc()) {
        $delayed_tasks[] = $row;
    }

    // 6. Completion rate overall
    $completion_rate = $counters['total'] > 0 ? round(($counters['completed'] / $counters['total']) * 100, 1) : 0;

    echo json_encode([
        'status' => 'success',
        'data' => [
            'counters' => $counters,
            'engineer_stats' => $engineer_stats,
            'avg_completion_hours' => $avg_completion_hours,
            'monthly_trends' => $monthly_trends,
            'most_active_engineer' => $most_active_engineer,
            'delayed_tasks' => $delayed_tasks,
            'completion_rate' => $completion_rate
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
