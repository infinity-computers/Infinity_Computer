<?php
error_reporting(0);
ob_start();
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/../../config/db.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // 1. Auto-create table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS user_service_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            device_type VARCHAR(100) NOT NULL,
            brand VARCHAR(100) DEFAULT NULL,
            model VARCHAR(100) DEFAULT NULL,
            problem TEXT NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'Pending Approval',
            device_received BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Ensure columns exist if table was already there
        $res = $conn->query("SHOW COLUMNS FROM user_service_requests LIKE 'image_path'");
        if ($res->num_rows == 0) {
            $conn->query("ALTER TABLE user_service_requests ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER problem");
        }

        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        $device_type = $_POST['device_type'] ?? '';
        $brand = $_POST['brand'] ?? '';
        $model = $_POST['model'] ?? '';
        $problem = $_POST['problem'] ?? '';

        if (empty($name) || empty($phone) || empty($email) || empty($address) || empty($device_type) || empty($problem)) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'All mandatory fields are required (Name, Phone, Email, Address, Device Type, Problem).']);
            exit;
        }

        // Determine unique ID INF-SRV-YYYYMMDD-XXXX
        $date_prefix = date("Ymd");
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_service_requests WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $next_num = $row['count'] + 1;
        $service_id = "INF-SRV-" . $date_prefix . "-" . str_pad($next_num, 4, "0", STR_PAD_LEFT);
        
        // Check uniqueness (just in case)
        $stmt = $conn->prepare("SELECT id FROM user_service_requests WHERE service_id = ?");
        while(true) {
            $stmt->bind_param("s", $service_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if(!$res->fetch_assoc()) break;
            $next_num++;
            $service_id = "INF-SRV-" . $date_prefix . "-" . str_pad($next_num, 4, "0", STR_PAD_LEFT);
        }

        // Image Upload & Processing
        require_once __DIR__ . '/image_helper.php';
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $filename = processAndSaveImage($_FILES['image'], "../../uploads/service-requests/");
            if ($filename) {
                $image_path = "uploads/service-requests/" . $filename;
            } else {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Image processing failed.']);
                exit;
            }
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Image is mandatory for submitting a request.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO user_service_requests (service_id, name, phone, email, address, device_type, brand, model, problem, image_path, status, device_received) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Approval', 0)");
        $stmt->bind_param("ssssssssss", $service_id, $name, $phone, $email, $address, $device_type, $brand, $model, $problem, $image_path);
        $stmt->execute();

        // Send Email Notification to User
        $to = $email;
        $subject = "Infinity Computer - Service Request Received ({$service_id})";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

        $message = "
        <html>
        <head>
        <title>Service Request Received</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #0d6efd; text-align: center;'>Service Request Confirmation</h2>
                <p>Dear {$name},</p>
                <p>Thank you for reaching out to Infinity Computer. We have successfully received your service request.</p>
                
                <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
                    <h3 style='margin-top: 0; color: #1e293b; font-size: 16px;'>Request Details:</h3>
                    <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-size: 18px; font-weight: bold;'>{$service_id}</span></p>
                    <p style='margin: 5px 0;'><strong>Device Type:</strong> {$device_type}</p>
                    <p style='margin: 5px 0;'><strong>Brand/Model:</strong> {$brand} {$model}</p>
                </div>

                <p><strong>How to track your status?</strong></p>
                <p>You can check the real-time status of your request anytime on our website using your <strong>Service ID</strong>.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Track Request Now</a>
                </div>

                <p>Our team will review your request shortly and you will receive further updates as we process it.</p>
                
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 12px; color: #64748b; text-align: center;'>
                    This is an automated message. Please do not reply directly to this email.<br>
                    &copy; " . date('Y') . " Infinity Computer. All rights reserved.
                </p>
            </div>
        </body>
        </html>
        ";
        
        @mail($to, $subject, $message, $headers);
        
        ob_clean();
        echo json_encode(['status' => 'success', 'service_id' => $service_id, 'message' => 'Service request submitted successfully. We will review it shortly.']);
    } else {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
ob_end_flush();
