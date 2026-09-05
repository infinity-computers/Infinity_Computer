<?php
/**
 * Dynamic AMC Product Management API
 * Allows Admin to add, list, edit, and activate/deactivate product types dynamically.
 */

include __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/amc_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$staffName = getStaffName();
$staffRole = getStaffRole();
$isAdmin = isAdmin();

try {
    if ($action === 'list') {
        $res = $conn->query("SELECT * FROM amc_products ORDER BY is_active DESC, name ASC");
        $products = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) $products[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $products]);
        exit;
    }

    if ($action === 'add') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Only Admin can add products.']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Product name is required.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO amc_products (name, description, is_active) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $name, $description);
        
        if (!$stmt->execute()) {
            if ($conn->errno === 1062) {
                echo json_encode(['status' => 'error', 'message' => 'Product type already exists.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            }
            exit;
        }

        logAmcAudit($conn, null, null, 'Product Added', $staffName, $staffRole, "Added dynamic product type: {$name}");

        echo json_encode(['status' => 'success', 'message' => "Product '{$name}' added successfully."]);
        exit;
    }

    if ($action === 'toggle') {
        if (!$isAdmin) {
            echo json_encode(['status' => 'error', 'message' => 'Only Admin can change product status.']);
            exit;
        }

        $id = intval($_POST['id'] ?? 0);
        $is_active = intval($_POST['is_active'] ?? 1);

        $stmt = $conn->prepare("UPDATE amc_products SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $id);
        $stmt->execute();

        logAmcAudit($conn, null, null, 'Product Status Toggled', $staffName, $staffRole, "Updated status for product ID {$id} to " . ($is_active ? 'Active' : 'Deactivated'));

        echo json_encode(['status' => 'success', 'message' => 'Product status updated successfully.']);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
