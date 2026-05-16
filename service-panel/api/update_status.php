<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if(empty($data)) {
        $data = $_POST;
    }
    
    $service_pk = $data['id'] ?? '';
    $new_status = $data['status'] ?? '';
    $remarks = $data['remarks'] ?? '';
    $assigned_engineer = $data['assigned_engineer'] ?? '';

    if(empty($service_pk) || empty($new_status)) {
        echo json_encode(['status' => 'error', 'message' => 'ID and status are required']);
        exit;
    }

    try {
        $conn->begin_transaction();

        if(in_array($new_status, ['Completed', 'Ready for Pickup', 'Delivered'])) {
            $stmt = $conn->prepare("UPDATE services SET status = ?, date_completed = ? WHERE id = ?");
            $date = date('Y-m-d');
            $stmt->bind_param("ssi", $new_status, $date, $service_pk);
        } else {
            $stmt = $conn->prepare("UPDATE services SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $service_pk);
        }
        $stmt->execute();

        // Handle Engineer Re-assignment
        $reassigned = false;
        $stmt = $conn->prepare("SELECT status, assigned_engineer, assigned_at, service_id, device_name, customer_id FROM services WHERE id = ?");
        $stmt->bind_param("i", $service_pk);
        $stmt->execute();
        $current_data = $stmt->get_result()->fetch_assoc();

        if ($current_data && !empty($assigned_engineer) && $current_data['assigned_engineer'] !== $assigned_engineer) {
            // Check if status is Completed
            if ($current_data['status'] === 'Completed') {
                // Ignore assignment change if completed
            } else {
                $stmt = $conn->prepare("UPDATE services SET assigned_engineer = ?, assigned_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $assigned_engineer, $service_pk);
                $stmt->execute();
                $reassigned = true;
                $current_data['assigned_at'] = date('Y-m-d H:i:s'); // Update for completion email logic below
                $current_data['assigned_engineer'] = $assigned_engineer;

                // Send Email to NEW Assigned Engineer
                require_once 'email_helper.php';
                $engineer_emails = [
                    'Suraj' => 'suraj@staff.infinitycomputer.in',
                    'Akshar' => 'akshar@staff.infinitycomputer.in',
                    'Karan' => 'karan@staff.infinitycomputer.in',
                    'Rahul' => 'rahul@staff.infinitycomputer.in',
                    'Paresh' => 'paresh@staff.infinitycomputer.in'
                ];
                $eng_email = $engineer_emails[$assigned_engineer] ?? '';
                if ($eng_email) {
                    // Get customer name
                    $c_stmt = $conn->prepare("SELECT name FROM customers WHERE id = ?");
                    $c_stmt->bind_param("i", $current_data['customer_id']);
                    $c_stmt->execute();
                    $cust_name = $c_stmt->get_result()->fetch_assoc()['name'] ?? 'Customer';
                    
                    sendEngineerAssignmentEmail($eng_email, $assigned_engineer, $current_data['service_id'], $cust_name, $current_data['device_name'], 'Task re-assigned', true);
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO service_status_logs (service_id, status, remarks) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $service_pk, $new_status, $remarks);
        $stmt->execute();

        // Fetch service details for email
        $e_stmt = $conn->prepare("SELECT s.service_id, s.device_name, c.name, c.email FROM services s JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
        $e_stmt->bind_param("i", $service_pk);
        $e_stmt->execute();
        $srv_data = $e_stmt->get_result()->fetch_assoc();
        
        if ($srv_data) {
            $email = $srv_data['email'];
            $srv_id = $srv_data['service_id'];
            
            // If email is not in customers table, try user_service_requests
            if (empty($email)) {
                $usr_stmt = $conn->prepare("SELECT email FROM user_service_requests WHERE service_id = ?");
                $usr_stmt->bind_param("s", $srv_id);
                $usr_stmt->execute();
                $usr_res = $usr_stmt->get_result()->fetch_assoc();
                if ($usr_res && !empty($usr_res['email'])) {
                    $email = $usr_res['email'];
                }
            }

            if (!empty($email)) {
                require_once 'email_helper.php';
                sendServiceStatusUpdateEmail($email, $srv_data['name'], $srv_id, $new_status, $srv_data['device_name']);
            }
        }

        // Handle Owner Notification on Completion
        if ($new_status === 'Completed' && $current_data['status'] !== 'Completed') {
            require_once 'email_helper.php';
            $assigned_at = $current_data['assigned_at'];
            $completed_at = date('Y-m-d H:i:s');
            
            $duration = "Not recorded (Unassigned)";
            if ($assigned_at) {
                $start = new DateTime($assigned_at);
                $end = new DateTime($completed_at);
                $diff = $start->diff($end);
                $duration = "";
                if ($diff->days > 0) $duration .= $diff->days . " days, ";
                $duration .= $diff->h . " hours, " . $diff->i . " minutes";
            }
            
            // Get customer name
            $c_stmt = $conn->prepare("SELECT name FROM customers WHERE id = ?");
            $c_stmt->bind_param("i", $current_data['customer_id']);
            $c_stmt->execute();
            $cust_name = $c_stmt->get_result()->fetch_assoc()['name'] ?? 'Customer';
            
            sendOwnerCompletionEmail(
                $current_data['service_id'], 
                $cust_name, 
                $current_data['device_name'], 
                $current_data['assigned_engineer'] ?: 'Unassigned', 
                $assigned_at ?: 'N/A', 
                $completed_at, 
                $duration
            );
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
