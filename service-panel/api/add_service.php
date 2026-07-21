<?php include __DIR__ . '/../auth_guard.php'; ?>
<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $device_name = $_POST['device_name'] ?? '';
    $company = $_POST['company'] ?? '';
    $problem = $_POST['problem'] ?? '';
    $assigned_engineer = $_POST['assigned_engineer'] ?? '';
    
    if (empty($name) || empty($phone) || empty($email) || empty($company) || empty($service_type) || empty($device_name) || empty($problem)) {
        echo json_encode(['status' => 'error', 'message' => 'All text fields are required']);
        exit;
    }

    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        echo json_encode(['status' => 'error', 'message' => 'At least one device image is required.']);
        exit;
    }

    // reCAPTCHA Verification
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaResponse)) {
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA Verification Failed. Please solve the captcha.']);
        exit;
    }

    $recaptchaSecret = '6LcadY0sAAAAAE-ADcAzbPWGpJLAdi1oW2jLB4Qe';
    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $recaptchaSecret . '&response=' . $recaptchaResponse);
    $responseData = json_decode($verifyResponse);

    if (!$responseData->success) {
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA Verification Failed. Please try again.']);
        exit;
    }

    // Ensure columns exist in services table
    $res = $conn->query("SHOW COLUMNS FROM services LIKE 'image_path'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER problem");
    }
    $res = $conn->query("SHOW COLUMNS FROM services LIKE 'company'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN company VARCHAR(255) DEFAULT NULL AFTER device_name");
    }
    $res = $conn->query("SHOW COLUMNS FROM services LIKE 'assigned_engineer'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN assigned_engineer VARCHAR(255) DEFAULT NULL AFTER status");
    }
    $res = $conn->query("SHOW COLUMNS FROM services LIKE 'assigned_at'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN assigned_at TIMESTAMP NULL DEFAULT NULL AFTER assigned_engineer");
    }

    // Ensure email and company column exists in customers table
    $res = $conn->query("SHOW COLUMNS FROM customers LIKE 'email'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE customers ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER phone");
    }
    $res = $conn->query("SHOW COLUMNS FROM customers LIKE 'company'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE customers ADD COLUMN company VARCHAR(255) DEFAULT NULL AFTER email");
    }

    try {
        $conn->begin_transaction();

        $device_received = isset($_POST['device_received']) ? 1 : 0;

        if ($device_received == 0) {
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
                company VARCHAR(255) DEFAULT NULL,
                problem TEXT NOT NULL,
                image_path VARCHAR(255),
                status VARCHAR(50) DEFAULT 'Pending Approval',
                device_received BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Ensure company column in user_service_requests
            $res = $conn->query("SHOW COLUMNS FROM user_service_requests LIKE 'company'");
            if ($res->num_rows == 0) {
                $conn->query("ALTER TABLE user_service_requests ADD COLUMN company VARCHAR(255) DEFAULT NULL AFTER model");
            }

            $date_prefix = date("Ymd");
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_service_requests WHERE DATE(created_at) = CURDATE()");
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $next_num = $row['count'] + 1;
            $service_id = "INF-SRV-" . $date_prefix . "-" . str_pad($next_num, 4, "0", STR_PAD_LEFT);
            
            $stmt = $conn->prepare("SELECT id FROM user_service_requests WHERE service_id = ?");
            while(true) {
                $stmt->bind_param("s", $service_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if(!$res->fetch_assoc()) break;
                $next_num++;
                $service_id = "INF-SRV-" . $date_prefix . "-" . str_pad($next_num, 4, "0", STR_PAD_LEFT);
            }

            require_once 'image_helper.php';

            // Ensure service_images table exists
            $conn->query("CREATE TABLE IF NOT EXISTS service_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id VARCHAR(50) NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                source_table VARCHAR(50) DEFAULT 'user_service_requests',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $image_path = null;
            $uploadDir = "../../uploads/service-requests/";
            $uploadedPaths = [];

            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $fileCount = min(count($_FILES['images']['name']), 5); // Max 5
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($_FILES['images']['error'][$i] == 0) {
                        $singleFile = [
                            'name'     => $_FILES['images']['name'][$i],
                            'type'     => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error'    => $_FILES['images']['error'][$i],
                            'size'     => $_FILES['images']['size'][$i],
                        ];
                        $filename = processAndSaveImage($singleFile, $uploadDir);
                        if ($filename) {
                            $uploadedPaths[] = "uploads/service-requests/" . $filename;
                        }
                    }
                }
            }

            if (empty($uploadedPaths)) {
                echo json_encode(['status' => 'error', 'message' => 'Image processing failed. Please try again.']);
                exit;
            }

            $image_path = $uploadedPaths[0]; // first image as primary

            $address = 'N/A';
            $brand = 'N/A';
            
            $stmt = $conn->prepare("INSERT INTO user_service_requests (service_id, name, phone, email, address, device_type, brand, model, company, problem, image_path, status, device_received) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending Drop-off', 0)");
            $stmt->bind_param("sssssssssss", $service_id, $name, $phone, $email, $address, $service_type, $brand, $device_name, $company, $problem, $image_path);
            $stmt->execute();

            // Insert all images into service_images table
            $imgStmt = $conn->prepare("INSERT INTO service_images (service_id, image_path, source_table) VALUES (?, ?, 'user_service_requests')");
            foreach ($uploadedPaths as $path) {
                $imgStmt->bind_param("ss", $service_id, $path);
                $imgStmt->execute();
            }

            $conn->commit();

            if (!empty($email)) {
                require_once 'email_helper.php';
                sendPendingDropoffEmail($email, $name, $service_id);
            }

            echo json_encode(['status' => 'success', 'service_id' => $service_id, 'message' => 'Request added to User Requests. Device pending drop-off']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, email FROM customers WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $res = $stmt->get_result();
        $customer = $res->fetch_assoc();

        if ($customer) {
            $customer_id = $customer['id'];
            if ((empty($customer['email']) && !empty($email)) || !empty($company)) {
                $upd = $conn->prepare("UPDATE customers SET email = IFNULL(email, ?), company = IFNULL(company, ?) WHERE id = ?");
                $upd->bind_param("ssi", $email, $company, $customer_id);
                $upd->execute();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, company) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $phone, $email, $company);
            $stmt->execute();
            $customer_id = $conn->insert_id;
        }

        $year = date("Y");
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE YEAR(created_at) = ?");
        $stmt->bind_param("s", $year);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        
        $next_num = $row['count'] + 1;
        $service_id = "INF-" . $year . "-" . str_pad($next_num, 3, "0", STR_PAD_LEFT);
        
        $stmt = $conn->prepare("SELECT id FROM services WHERE service_id = ?");
        while(true) {
            $stmt->bind_param("s", $service_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if(!$res->fetch_assoc()) break;
            $next_num++;
            $service_id = "INF-" . $year . "-" . str_pad($next_num, 3, "0", STR_PAD_LEFT);
        }

        // Image Processing
        require_once 'image_helper.php';

        // Ensure service_images table exists
        $conn->query("CREATE TABLE IF NOT EXISTS service_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id VARCHAR(50) NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            source_table VARCHAR(50) DEFAULT 'services',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $image_path = null;
        $uploadDir = "../../uploads/images/";
        $uploadedPaths = [];

        if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
            $fileCount = min(count($_FILES['images']['name']), 5); // Max 5
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['images']['error'][$i] == 0) {
                    $singleFile = [
                        'name'     => $_FILES['images']['name'][$i],
                        'type'     => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error'    => $_FILES['images']['error'][$i],
                        'size'     => $_FILES['images']['size'][$i],
                    ];
                    $filename = processAndSaveImage($singleFile, $uploadDir);
                    if ($filename) {
                        $uploadedPaths[] = "uploads/images/" . $filename;
                    }
                }
            }
        }

        if (empty($uploadedPaths)) {
            echo json_encode(['status' => 'error', 'message' => 'Image processing failed. Please try again.']);
            exit;
        }

        $image_path = $uploadedPaths[0]; // first image as primary for backwards compat

        $date_received = date('Y-m-d');
        $assigned_at = !empty($assigned_engineer) ? date('Y-m-d H:i:s') : null;
        
        $stmt = $conn->prepare("INSERT INTO services (service_id, customer_id, service_type, device_name, company, problem, image_path, status, assigned_engineer, assigned_at, date_received) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
        $stmt->bind_param("sissssssss", $service_id, $customer_id, $service_type, $device_name, $company, $problem, $image_path, $assigned_engineer, $assigned_at, $date_received);
        $stmt->execute();
        $service_pk = $conn->insert_id;

        // Insert all images into service_images table
        $imgStmt = $conn->prepare("INSERT INTO service_images (service_id, image_path, source_table) VALUES (?, ?, 'services')");
        foreach ($uploadedPaths as $path) {
            $imgStmt->bind_param("ss", $service_id, $path);
            $imgStmt->execute();
        }

        $stmt = $conn->prepare("INSERT INTO service_status_logs (service_id, status, remarks) VALUES (?, 'Pending', 'Service request created')");
        $stmt->bind_param("i", $service_pk);
        $stmt->execute();

        $conn->commit();

        // Send Email to Assigned Engineer
        if (!empty($assigned_engineer)) {
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
                sendEngineerAssignmentEmail($eng_email, $assigned_engineer, $service_id, $name, $device_name, $problem);
            }
        }

        if (!empty($email)) {
            // Send Email Notification to User
            $to = $email;
            $subject = "Infinity Computer - Service Request Registered ({$service_id})";
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

            $message = "
            <html>
            <head>
            <title>Service Request Registered</title>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #0d6efd; text-align: center;'>Service Request Confirmation</h2>
                    <p>Dear {$name},</p>
                    <p>Your service request has been successfully registered at Infinity Computer.</p>
                    
                    <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
                        <h3 style='margin-top: 0; color: #1e293b; font-size: 16px;'>Request Details:</h3>
                        <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-size: 18px; font-weight: bold;'>{$service_id}</span></p>
                        <p style='margin: 5px 0;'><strong>Service Type:</strong> {$service_type}</p>
                        <p style='margin: 5px 0;'><strong>Device:</strong> {$device_name}</p>
                    </div>

                    <p><strong>How to track your status?</strong></p>
                    <p>You can check the real-time status of your request anytime on our website using your <strong>Service ID</strong>.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Track Request Now</a>
                    </div>

                    <p>Our team is working on your request and you will receive updates as we progress.</p>
                    
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
        }

        echo json_encode(['status' => 'success', 'service_id' => $service_id, 'message' => 'Service added successfully']);

    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
