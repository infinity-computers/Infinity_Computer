<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
/**
 * CRM Analytics API
 * Read-only endpoint that aggregates data from existing tables.
 * No schema changes required.
 */
require_once '../config/db.php';
header('Content-Type: application/json');

// Date range filter
$range = isset($_GET['range']) ? intval($_GET['range']) : 30;
$validRanges = [7, 30, 90, 365];
if (!in_array($range, $validRanges)) $range = 30;

$dateFilter = date('Y-m-d', strtotime("-{$range} days"));

try {
    $result = [];

    // ============================================
    // 1. OVERVIEW CARDS — from services table
    // ============================================
    $overview = [
        'total' => 0,
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'user_requests' => 0,
        'home_services' => 0
    ];

    $res = $conn->query("SELECT status, COUNT(*) as cnt FROM services GROUP BY status");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $s = strtolower($row['status']);
            $overview['total'] += $row['cnt'];
            if ($s === 'pending' || $s === 'accepted') $overview['pending'] += $row['cnt'];
            elseif (in_array($s, ['diagnosing', 'repair in progress', 'waiting for parts'])) $overview['in_progress'] += $row['cnt'];
            elseif ($s === 'completed' || $s === 'ready for pickup') $overview['completed'] += $row['cnt'];
            elseif ($s === 'delivered') $overview['delivered'] += $row['cnt'];
            elseif ($s === 'cancelled') $overview['cancelled'] += $row['cnt'];
        }
    }

    // Count user requests
    $res = $conn->query("SELECT COUNT(*) as cnt FROM user_service_requests");
    if ($res) {
        $row = $res->fetch_assoc();
        $overview['user_requests'] = intval($row['cnt']);
    }

    // Count home services
    $res = $conn->query("SELECT COUNT(*) as cnt FROM home_service_requests");
    if ($res) {
        $row = $res->fetch_assoc();
        $overview['home_services'] = intval($row['cnt']);
    }

    $result['overview'] = $overview;

    // ============================================
    // 2. STATUS DISTRIBUTION — for pie chart
    // ============================================
    $statusDist = [];
    $res = $conn->query("SELECT status, COUNT(*) as cnt FROM services GROUP BY status ORDER BY cnt DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $statusDist[] = ['status' => $row['status'], 'count' => intval($row['cnt'])];
        }
    }
    $result['status_distribution'] = $statusDist;

    // ============================================
    // 3. DAILY PERFORMANCE — last N days (bar chart)
    // ============================================
    $dailyPerf = [];
    $stmt = $conn->prepare("
        SELECT DATE(date_received) as day, COUNT(*) as received,
            SUM(CASE WHEN status IN ('Completed','Delivered','Ready for Pickup') THEN 1 ELSE 0 END) as completed
        FROM services
        WHERE date_received >= ?
        GROUP BY DATE(date_received)
        ORDER BY day ASC
    ");
    $stmt->bind_param("s", $dateFilter);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dailyPerf[] = [
            'day' => $row['day'],
            'received' => intval($row['received']),
            'completed' => intval($row['completed'])
        ];
    }
    $result['daily_performance'] = $dailyPerf;

    // ============================================
    // 4. SERVICE TYPE DISTRIBUTION — doughnut chart
    // ============================================
    $typeDist = [];
    $res = $conn->query("SELECT service_type, COUNT(*) as cnt FROM services GROUP BY service_type ORDER BY cnt DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $typeDist[] = ['type' => $row['service_type'], 'count' => intval($row['cnt'])];
        }
    }
    $result['type_distribution'] = $typeDist;

    // ============================================
    // 5. TIME-BASED INSIGHTS
    // ============================================
    $insights = [];

    // This week
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $res = $conn->query("SELECT COUNT(*) as cnt FROM services WHERE status IN ('Completed','Delivered','Ready for Pickup') AND updated_at >= '$weekStart'");
    $insights['completed_this_week'] = $res ? intval($res->fetch_assoc()['cnt']) : 0;

    // This month
    $monthStart = date('Y-m-01');
    $res = $conn->query("SELECT COUNT(*) as cnt FROM services WHERE status IN ('Completed','Delivered','Ready for Pickup') AND updated_at >= '$monthStart'");
    $insights['completed_this_month'] = $res ? intval($res->fetch_assoc()['cnt']) : 0;

    // Average completion time (days)
    $res = $conn->query("SELECT AVG(DATEDIFF(COALESCE(date_completed, updated_at), date_received)) as avg_days FROM services WHERE status IN ('Completed','Delivered')");
    $insights['avg_completion_days'] = $res ? round(floatval($res->fetch_assoc()['avg_days']), 1) : 0;

    // Peak service day
    $res = $conn->query("SELECT DAYNAME(date_received) as day_name, COUNT(*) as cnt FROM services GROUP BY DAYNAME(date_received) ORDER BY cnt DESC LIMIT 1");
    $insights['peak_day'] = $res ? ($res->fetch_assoc()['day_name'] ?? 'N/A') : 'N/A';

    // Most common service type
    $res = $conn->query("SELECT service_type, COUNT(*) as cnt FROM services GROUP BY service_type ORDER BY cnt DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $insights['most_common_type'] = $row ? $row['service_type'] : 'N/A';

    // Completion rate
    $totalDone = $overview['completed'] + $overview['delivered'];
    $insights['completion_rate'] = $overview['total'] > 0 ? round(($totalDone / $overview['total']) * 100, 1) : 0;

    // Pending backlog
    $insights['pending_backlog'] = $overview['pending'] + $overview['in_progress'];

    // Growth rate: this week vs last week
    $lastWeekStart = date('Y-m-d', strtotime('-1 week monday'));
    $lastWeekEnd = date('Y-m-d', strtotime('monday this week'));
    $res = $conn->query("SELECT COUNT(*) as cnt FROM services WHERE date_received >= '$weekStart'");
    $thisWeekCount = $res ? intval($res->fetch_assoc()['cnt']) : 0;
    $res = $conn->query("SELECT COUNT(*) as cnt FROM services WHERE date_received >= '$lastWeekStart' AND date_received < '$lastWeekEnd'");
    $lastWeekCount = $res ? intval($res->fetch_assoc()['cnt']) : 0;
    $insights['growth_rate'] = $lastWeekCount > 0 ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1) : 0;
    $insights['this_week_count'] = $thisWeekCount;
    $insights['last_week_count'] = $lastWeekCount;

    $result['insights'] = $insights;

    // ============================================
    // 6. RECENT SERVICES TABLE — last 10
    // ============================================
    $recent = [];
    $res = $conn->query("
        SELECT s.service_id, s.service_type, s.device_name, s.status, s.date_received, c.name, c.phone
        FROM services s 
        JOIN customers c ON s.customer_id = c.id 
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $recent[] = $row;
        }
    }
    $result['recent_services'] = $recent;

    // ============================================
    // 7. SOURCE BREAKDOWN — web vs home vs store
    // ============================================
    $sourceDist = [];
    $storeCount = $overview['total'];
    $webCount = $overview['user_requests'];
    $homeCount = $overview['home_services'];
    $sourceDist[] = ['source' => 'Store Service', 'count' => $storeCount];
    $sourceDist[] = ['source' => 'Web Request', 'count' => $webCount];
    $sourceDist[] = ['source' => 'Home Service', 'count' => $homeCount];
    $result['source_distribution'] = $sourceDist;

    // ============================================
    // 8. REPEAT CUSTOMERS — customers with 2+ services
    // ============================================
    $repeatCustomers = [];
    $res = $conn->query("
        SELECT c.name, c.phone, COUNT(s.id) as visit_count,
            MAX(s.date_received) as last_visit,
            MIN(s.date_received) as first_visit
        FROM services s
        JOIN customers c ON s.customer_id = c.id
        GROUP BY c.id
        HAVING visit_count >= 2
        ORDER BY visit_count DESC
        LIMIT 10
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $repeatCustomers[] = $row;
        }
    }
    $result['repeat_customers'] = $repeatCustomers;

    // Total unique customers
    $res = $conn->query("SELECT COUNT(DISTINCT customer_id) as cnt FROM services");
    $result['total_unique_customers'] = $res ? intval($res->fetch_assoc()['cnt']) : 0;
    $result['repeat_customer_count'] = count($repeatCustomers);

    // ============================================
    // 9. MONTHLY COMPARISON — this month vs last month
    // ============================================
    $thisMonthStart = date('Y-m-01');
    $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
    $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

    $monthly = ['this_month' => [], 'last_month' => []];

    $res = $conn->query("SELECT COUNT(*) as received, SUM(CASE WHEN status IN ('Completed','Delivered','Ready for Pickup') THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled FROM services WHERE date_received >= '$thisMonthStart'");
    if ($res) $monthly['this_month'] = $res->fetch_assoc();

    $res = $conn->query("SELECT COUNT(*) as received, SUM(CASE WHEN status IN ('Completed','Delivered','Ready for Pickup') THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled FROM services WHERE date_received >= '$lastMonthStart' AND date_received <= '$lastMonthEnd'");
    if ($res) $monthly['last_month'] = $res->fetch_assoc();

    $monthly['this_month_name'] = date('F Y');
    $monthly['last_month_name'] = date('F Y', strtotime('-1 month'));
    $result['monthly_comparison'] = $monthly;

    // ============================================
    // 10. SLA OVERDUE — services stuck too long
    // ============================================
    $overdue = [];
    $res = $conn->query("
        SELECT s.service_id, s.device_name, s.status, s.date_received,
            DATEDIFF(CURDATE(), s.date_received) as days_open, c.name, c.phone
        FROM services s
        JOIN customers c ON s.customer_id = c.id
        WHERE s.status NOT IN ('Completed','Delivered','Cancelled','Ready for Pickup')
            AND DATEDIFF(CURDATE(), s.date_received) > 3
        ORDER BY days_open DESC
        LIMIT 10
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $overdue[] = $row;
        }
    }
    $result['overdue_services'] = $overdue;
    
    // ============================================
    // 11. ENGINEER PERFORMANCE
    // ============================================
    $engStats = [];
    
    // Fetch all active engineers first to ensure they all show up
    $res = $conn->query("SELECT name FROM engineers WHERE is_active = 1 ORDER BY name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $engStats[$row['name']] = ['total' => 0, 'completed' => 0, 'pending' => 0];
        }
    }

    $res = $conn->query("SELECT assigned_engineer, status, COUNT(*) as cnt FROM services WHERE assigned_engineer IS NOT NULL AND assigned_engineer != '' GROUP BY assigned_engineer, status");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $eng = $row['assigned_engineer'];
            
            // If the engineer is not in the active list (e.g. deactivated), add them dynamically if they have jobs
            if (!isset($engStats[$eng])) {
                $engStats[$eng] = ['total' => 0, 'completed' => 0, 'pending' => 0];
            }
            
            $cnt = intval($row['cnt']);
            $s = strtolower($row['status']);
            
            $engStats[$eng]['total'] += $cnt;
            if (in_array($s, ['completed', 'delivered', 'ready for pickup'])) {
                $engStats[$eng]['completed'] += $cnt;
            } else {
                $engStats[$eng]['pending'] += $cnt;
            }
        }
    }
    $result['engineer_performance'] = $engStats;

    echo json_encode(['status' => 'success', 'data' => $result, 'range' => $range]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
