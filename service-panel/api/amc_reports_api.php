<?php
/**
 * AMC Dashboard Metrics & Reports API
 * Computes live operational dashboard metrics and generates AMC performance & audit reports.
 */

include __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/amc_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'dashboard_stats';
$staffName = getStaffName();
$isAdmin = isAdmin();

// Automatic 48h check
checkAndApply48HourReassignment($conn);

try {
    if ($action === 'dashboard_stats') {
        $stats = [
            'total_contracts' => 0,
            'active_contracts' => 0,
            'upcoming_visits' => 0,
            'todays_visits' => 0,
            'pending_visits' => 0,
            'completed_visits' => 0,
            'overdue_visits' => 0,
            'followup_required' => 0,
            'reassigned_visits' => 0,
            'escalated_visits' => 0
        ];

        // Total & Active Contracts
        $r1 = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active FROM amc_contracts");
        if ($r1 && $row1 = $r1->fetch_assoc()) {
            $stats['total_contracts'] = intval($row1['total']);
            $stats['active_contracts'] = intval($row1['active']);
        }

        // Visits Statistics
        $todayStr = date('Y-m-d');
        $r2 = $conn->query("
            SELECT 
                COUNT(*) as total_visits,
                SUM(CASE WHEN scheduled_date = '$todayStr' AND status NOT IN ('COMPLETED', 'CANCELLED') THEN 1 ELSE 0 END) as today_cnt,
                SUM(CASE WHEN scheduled_date > '$todayStr' AND status NOT IN ('COMPLETED', 'CANCELLED') THEN 1 ELSE 0 END) as upcoming_cnt,
                SUM(CASE WHEN status NOT IN ('COMPLETED', 'CANCELLED') THEN 1 ELSE 0 END) as pending_cnt,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_cnt,
                SUM(CASE WHEN status = 'OVERDUE' OR (scheduled_date < '$todayStr' AND status NOT IN ('COMPLETED', 'CANCELLED')) THEN 1 ELSE 0 END) as overdue_cnt,
                SUM(CASE WHEN status = 'FOLLOW-UP REQUIRED' THEN 1 ELSE 0 END) as followup_cnt,
                SUM(CASE WHEN is_inactive_reassigned = 1 THEN 1 ELSE 0 END) as reassigned_cnt,
                SUM(CASE WHEN escalation_level >= 2 THEN 1 ELSE 0 END) as escalated_cnt
            FROM amc_visits
        ");
        if ($r2 && $row2 = $r2->fetch_assoc()) {
            $stats['todays_visits'] = intval($row2['today_cnt']);
            $stats['upcoming_visits'] = intval($row2['upcoming_cnt']);
            $stats['pending_visits'] = intval($row2['pending_cnt']);
            $stats['completed_visits'] = intval($row2['completed_cnt']);
            $stats['overdue_visits'] = intval($row2['overdue_cnt']);
            $stats['followup_required'] = intval($row2['followup_cnt']);
            $stats['reassigned_visits'] = intval($row2['reassigned_cnt']);
            $stats['escalated_visits'] = intval($row2['escalated_cnt']);
        }

        echo json_encode(['status' => 'success', 'data' => $stats]);
        exit;
    }

    if ($action === 'engineer_performance') {
        $query = "
            SELECT 
                assigned_engineer,
                COUNT(*) as total_assigned,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'OVERDUE' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN is_inactive_reassigned = 1 THEN 1 ELSE 0 END) as reassigned_count
            FROM amc_visits
            WHERE assigned_engineer IS NOT NULL AND assigned_engineer != ''
            GROUP BY assigned_engineer
            ORDER BY completed_count DESC
        ";
        $res = $conn->query($query);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    if ($action === 'product_breakdown') {
        $query = "
            SELECT product_name, COUNT(*) as visit_count, SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count
            FROM amc_visit_issues
            GROUP BY product_name
            ORDER BY visit_count DESC
        ";
        $res = $conn->query($query);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
