<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can search customer directories']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    echo json_encode(['status' => 'success', 'data' => []]);
    exit;
}

try {
    $q_like = "%$query%";
    
    // Search customers table
    $stmt = $conn->prepare("SELECT id as customer_id, name, phone, email FROM customers WHERE name LIKE ? OR phone LIKE ? LIMIT 15");
    $stmt->bind_param("ss", $q_like, $q_like);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $stmt->close();

    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
