<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? ''; // Approved, Rejected
    $device_received = $_POST['device_received'] ?? '';
    $assigned_engineer = $_POST['assigned_engineer'] ?? '';

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID is required']);
        exit;
    }

    try {
        $conn->begin_transaction();

        // 1. Get current record
        $stmt = $conn->prepare("SELECT * FROM user_service_requests WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();

        if (!$record) {
            throw new Exception("Record not found.");
        }

        // Ensure column exists in user_service_requests
        $res = $conn->query("SHOW COLUMNS FROM user_service_requests LIKE 'assigned_engineer'");
        if ($res->num_rows == 0) {
            $conn->query("ALTER TABLE user_service_requests ADD COLUMN assigned_engineer VARCHAR(100) DEFAULT 'Suraj' AFTER device_received");
            $record['assigned_engineer'] = 'Suraj';
        }

        // 2. Update record
        $new_status = !empty($status) ? $status : $record['status'];
        $new_dr = ($device_received !== '') ? intval($device_received) : $record['device_received'];
        $final_engineer = !empty($assigned_engineer) ? $assigned_engineer : $record['assigned_engineer'];
        
        $stmt = $conn->prepare("UPDATE user_service_requests SET status = ?, device_received = ?, assigned_engineer = ? WHERE id = ?");
        $stmt->bind_param("sisi", $new_status, $new_dr, $final_engineer, $id);
        $stmt->execute();

        // 3. Check if Approved (or Pending) AND Device Received
        if (($new_status === 'Approved' || $new_status === 'Pending Drop-off' || $new_status === 'Pending Approval') && $new_dr === 1) {
            // Update status to Approved if it was just received
            if ($new_status !== 'Approved') {
                $new_status = 'Approved';
                $stmt = $conn->prepare("UPDATE user_service_requests SET status = ?, device_received = 1 WHERE id = ?");
                $stmt->bind_param("si", $new_status, $id);
                $stmt->execute();
            }

            // Check if it already exists in the main `services` table
            $srv_id = $record['service_id'];
            $check_stmt = $conn->prepare("SELECT id FROM services WHERE service_id = ?");
            $check_stmt->bind_param("s", $srv_id);
            $check_stmt->execute();
            if (!$check_stmt->get_result()->fetch_assoc()) {
                // Must insert into customers first
                $phone = $record['phone'];
                $c_stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
                $c_stmt->bind_param("s", $phone);
                $c_stmt->execute();
                $c_res = $c_stmt->get_result();
                $customer = $c_res->fetch_assoc();

                if ($customer) {
                    $customer_id = $customer['id'];
                } else {
                    $i_stmt = $conn->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
                    $i_stmt->bind_param("sss", $record['name'], $phone, $record['email']);
                    $i_stmt->execute();
                    $customer_id = $conn->insert_id;
                }

                // Insert into main services table
                $device_name = trim($record['brand'] . ' ' . $record['model']);
                if (empty($device_name)) $device_name = 'Unknown Device';
                
                $date_received = date('Y-m-d');
                $img_path = $record['image_path']; 
                
                // Ensure columns exist in services table
                $res = $conn->query("SHOW COLUMNS FROM services LIKE 'assigned_engineer'");
                if ($res->num_rows == 0) {
                    $conn->query("ALTER TABLE services ADD COLUMN assigned_engineer VARCHAR(255) DEFAULT NULL AFTER status");
                }
                $res = $conn->query("SHOW COLUMNS FROM services LIKE 'assigned_at'");
                if ($res->num_rows == 0) {
                    $conn->query("ALTER TABLE services ADD COLUMN assigned_at TIMESTAMP NULL DEFAULT NULL AFTER assigned_engineer");
                }

                $ins_svc = $conn->prepare("INSERT INTO services (service_id, customer_id, service_type, device_name, problem, image_path, status, assigned_engineer, assigned_at, date_received) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, NOW(), ?)");
                $ins_svc->bind_param("sissssss", $srv_id, $customer_id, $record['device_type'], $device_name, $record['problem'], $img_path, $final_engineer, $date_received);
                $ins_svc->execute();
                $service_pk = $conn->insert_id;

                // Log status
                $log_stmt = $conn->prepare("INSERT INTO service_status_logs (service_id, status, remarks) VALUES (?, 'Pending', 'Moved from User Service Requests')");
                $log_stmt->bind_param("i", $service_pk);
                $log_stmt->execute();

                // Send Email to Assigned Engineer
                if (!empty($final_engineer)) {
                    require_once 'email_helper.php';
                    $engineer_emails = [
                        'Suraj' => 'suraj@staff.infinitycomputer.in',
                        'Akshar' => 'akshar@staff.infinitycomputer.in',
                        'Karan' => 'karan@staff.infinitycomputer.in',
                        'Rahul' => 'rahul@staff.infinitycomputer.in',
                        'Paresh' => 'paresh@staff.infinitycomputer.in'
                    ];
                    $eng_email = $engineer_emails[$final_engineer] ?? '';
                    if ($eng_email) {
                        sendEngineerAssignmentEmail($eng_email, $final_engineer, $srv_id, $record['name'], $device_name, $record['problem']);
                    }
                }
            }
        }

        if ($new_status != $record['status']) {
            require_once 'email_helper.php';
            sendUserRequestStatusEmail($record['email'], $record['name'], $record['service_id'], $new_status);
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);

    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
