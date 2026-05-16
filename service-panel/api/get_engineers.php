<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $res = $conn->query("SELECT name, email FROM engineers WHERE is_active = 1 ORDER BY name ASC");
    $engineers = [];
    while ($row = $res->fetch_assoc()) {
        $engineers[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $engineers]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
