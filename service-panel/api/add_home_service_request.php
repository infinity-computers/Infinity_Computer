<?php
error_reporting(0);
ob_start();
header('Content-Type: application/json');
try {
    require_once __DIR__ . '/../../config/db.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Auto-create table if not exists
        $conn->query("CREATE TABLE IF NOT EXISTS home_service_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            service_type VARCHAR(100) NOT NULL,
            booking_date DATE NOT NULL,
            time_slot VARCHAR(50) NOT NULL,
            problem TEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Ensure columns exist (Compatible with older MySQL)
        $res = $conn->query("SHOW COLUMNS FROM home_service_requests LIKE 'problem'");
        if ($res->num_rows == 0) {
            $conn->query("ALTER TABLE home_service_requests ADD COLUMN problem TEXT DEFAULT NULL AFTER time_slot");
        }

        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        $service_type = $_POST['service_type'] ?? '';
        $booking_date = $_POST['booking_date'] ?? '';
        $time_slot = $_POST['time_slot'] ?? '';
        $problem = $_POST['problem'] ?? '';

        // Verify reCAPTCHA
        /* Temporarily disabled
        $recaptchaSecret = '6LcadY0sAAAAAE-ADcAzbPWGpJLAdi1oW2jLB4Qe';
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        
        $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $recaptchaSecret . '&response=' . $recaptchaResponse);
        $responseData = json_decode($verifyResponse);

        if (!$responseData->success) {
            echo json_encode(['status' => 'error', 'message' => 'Robot verification failed. Please try again.']);
            exit;
        }
        */

        $date_prefix = date("Ymd");
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM home_service_requests WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $next_num = $row['count'] + 1;
        $service_id = "INF-HOME-" . $date_prefix . "-" . str_pad($next_num, 3, "0", STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO home_service_requests (service_id, name, phone, email, address, service_type, booking_date, time_slot, problem, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("sssssssss", $service_id, $name, $phone, $email, $address, $service_type, $booking_date, $time_slot, $problem);
        $stmt->execute();

        // Send Email Notification to User
        $to = $email;
        $subject = "Infinity Computer - Home Service Booking Confirmed ({$service_id})";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

        $message = "
        <html>
        <head>
        <title>Home Service Booking Confirmed</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #0d6efd; text-align: center;'>Home Service Booking Confirmed</h2>
                <p>Dear {$name},</p>
                <p>Thank you for choosing Infinity Computer. Your home service request has been successfully booked.</p>
                
                <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
                    <h3 style='margin-top: 0; color: #1e293b; font-size: 16px;'>Booking Details:</h3>
                    <p style='margin: 5px 0;'><strong>Booking ID:</strong> <span style='color: #0d6efd; font-size: 18px; font-weight: bold;'>{$service_id}</span></p>
                    <p style='margin: 5px 0;'><strong>Service Requested:</strong> {$service_type}</p>
                    <p style='margin: 5px 0;'><strong>Date:</strong> " . date('F j, Y', strtotime($booking_date)) . "</p>
                    <p style='margin: 5px 0;'><strong>Time Slot:</strong> {$time_slot}</p>
                </div>

                <p><strong>How to track your status?</strong></p>
                <p>You can check the real-time status of your request anytime on our website using your <strong>Booking ID</strong>.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Track Booking Now</a>
                </div>

                <p>Our engineer will contact you shortly to confirm the visit.</p>
                
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
        
        echo json_encode(['status' => 'success', 'service_id' => $service_id, 'message' => 'Home service booked successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
ob_end_flush();
