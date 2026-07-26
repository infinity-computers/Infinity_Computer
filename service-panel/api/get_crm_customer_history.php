<?php
require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/oms_helper.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Only Admin/Accounts and Super Admin can view customer profiles']);
    exit;
}

$customer_id = $_GET['customer_id'] ?? '';
$phone       = $_GET['phone'] ?? '';

if (empty($customer_id) && empty($phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Customer ID or Phone number is required']);
    exit;
}

try {
    $email = '';
    $name  = '';

    // Find customer details from customers table
    if (!empty($customer_id)) {
        $stmt = $conn->prepare("SELECT id, name, phone, email FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($c) {
            $phone = $c['phone'];
            $email = $c['email'];
            $name  = $c['name'];
        }
    } else if (!empty($phone)) {
        $stmt = $conn->prepare("SELECT id, name, phone, email FROM customers WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $c = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($c) {
            $customer_id = $c['id'];
            $name  = $c['name'];
            $email = $c['email'];
        } else {
            // Find from services or requests
            $stmt = $conn->prepare("SELECT name, email FROM services WHERE phone = ? LIMIT 1");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $s = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($s) {
                $name  = $s['name'];
                $email = $s['email'];
            } else {
                $stmt = $conn->prepare("SELECT name, email FROM user_service_requests WHERE phone = ? LIMIT 1");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $u = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($u) {
                    $name  = $u['name'];
                    $email = $u['email'];
                }
            }
        }
    }

    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Customer record not found']);
        exit;
    }

    // Fetch all tickets from both tables
    $tickets = [];
    $total_spent_internal = 0;
    $unique_devices = [];

    // Query services table
    $sql_s = "
        SELECT service_id, status, service_value_internal, device_name, created_at, 'Shop Service' as source_label 
        FROM services 
        WHERE customer_id = ? OR phone = ? OR (email != '' AND email = ?)
    ";
    $stmt = $conn->prepare($sql_s);
    $stmt->bind_param("iss", $customer_id, $phone, $email);
    $stmt->execute();
    $res_s = $stmt->get_result();
    while ($row = $res_s->fetch_assoc()) {
        $row['service_value_rupees'] = decodeServiceValue($row['service_value_internal']);
        $total_spent_internal += $row['service_value_internal'];
        if (!empty($row['device_name'])) {
            $dev_clean = trim($row['device_name']);
            $unique_devices[strtolower($dev_clean)] = $dev_clean;
        }
        $tickets[] = $row;
    }
    $stmt->close();

    // Query user_service_requests table
    $sql_u = "
        SELECT service_id, status, service_value_internal, CONCAT(brand, ' ', model) as device_name, created_at, 'Web Inquiry' as source_label 
        FROM user_service_requests 
        WHERE phone = ? OR (email != '' AND email = ?)
    ";
    $stmt = $conn->prepare($sql_u);
    $stmt->bind_param("ss", $phone, $email);
    $stmt->execute();
    $res_u = $stmt->get_result();
    while ($row = $res_u->fetch_assoc()) {
        $row['service_value_rupees'] = decodeServiceValue($row['service_value_internal']);
        $total_spent_internal += $row['service_value_internal'];
        if (!empty($row['device_name'])) {
            $dev_clean = trim($row['device_name']);
            $unique_devices[strtolower($dev_clean)] = $dev_clean;
        }
        $tickets[] = $row;
    }
    $stmt->close();

    // Fetch call log history for tickets
    $call_logs = [];
    if (count($tickets) > 0) {
        $svc_ids = array_map(function($t) { return $t['service_id']; }, $tickets);
        $placeholders = implode(',', array_fill(0, count($svc_ids), '?'));
        
        $sql_c = "
            SELECT service_id, call_status, notes, logged_by, created_at 
            FROM service_call_attempts 
            WHERE service_id IN ($placeholders) 
            ORDER BY created_at DESC
        ";
        $stmt = $conn->prepare($sql_c);
        $stmt->bind_param(str_repeat('s', count($svc_ids)), ...$svc_ids);
        $stmt->execute();
        $res_c = $stmt->get_result();
        while ($row = $res_c->fetch_assoc()) {
            $call_logs[] = $row;
        }
        $stmt->close();
    }

    echo json_encode([
        'status' => 'success',
        'customer' => [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'total_spent' => decodeServiceValue($total_spent_internal),
            'unique_devices' => array_values($unique_devices),
            'tickets' => $tickets,
            'call_logs' => $call_logs
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
