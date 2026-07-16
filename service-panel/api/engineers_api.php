<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['staff_logged_in']) || $_SESSION['staff_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Admins only
$admins = ['icc@infinitycomputer.in', 'suraj@staff.infinitycomputer.in'];
if (!in_array($_SESSION['staff_email'] ?? '', $admins)) {
    echo json_encode(['status' => 'error', 'message' => 'Forbidden: Admins only.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all engineers
    $query = "SELECT id, name, email, position, created_at FROM engineers ORDER BY name ASC";
    $result = $conn->query($query);

    $engineersList = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $engineersList[] = $row;
        }
    }
    echo json_encode(['status' => 'success', 'data' => $engineersList]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST' && isset($input['action']) && $input['action'] === 'delete') {
    // Delete engineer
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM engineers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Engineer deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete engineer.']);
    }
    exit;
}

if ($method === 'POST') {
    $id = intval($input['id'] ?? 0);
    $name = trim($input['name'] ?? '');
    $email = trim(strtolower($input['email'] ?? ''));
    $position = trim($input['position'] ?? 'staff');

    if (empty($name) || empty($email) || empty($position)) {
        echo json_encode(['status' => 'error', 'message' => 'Name, email, and position are required.']);
        exit;
    }

    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE engineers SET name = ?, email = ?, position = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $position, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Engineer updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update engineer. Maybe duplicate name?']);
        }
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO engineers (name, email, position) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $position);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Engineer added successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add engineer. Maybe duplicate name?']);
        }
    }
    exit;
}
?>
