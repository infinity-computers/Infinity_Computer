<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$enteredOtp = trim($input['otp'] ?? '');

// Check if OTP session exists
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'No OTP request found. Please request a new OTP.', 'expired' => true]);
    exit;
}

// Check if OTP has expired (5 minutes)
if ((time() - $_SESSION['otp_timestamp']) > 300) {
    unset($_SESSION['otp_code'], $_SESSION['otp_email'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts']);
    echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
    exit;
}

// Check max attempts
if ($_SESSION['otp_attempts'] >= 3) {
    echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.', 'blocked' => true]);
    exit;
}

// Validate input
if (empty($enteredOtp) || strlen($enteredOtp) !== 6) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 6-digit OTP']);
    exit;
}

// Verify OTP
if ($enteredOtp === $_SESSION['otp_code']) {
    // Fetch engineer details from DB for role and name
    require_once __DIR__ . '/../config/db.php';
    $email = $_SESSION['otp_email'];
    $role = 'Engineer';
    $name = 'Staff Member';

    $stmt = $conn->prepare("SELECT name, role FROM engineers WHERE email = ? OR LOWER(email) = LOWER(?)");
    if ($stmt) {
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $name = $row['name'];
            $role = $row['role'] ?: 'Engineer';
        }
        $stmt->close();
    }

    // Specific fallback overrides
    if (in_array(strtolower($email), ['icc@infinitycomputer.in'])) {
        $role = 'Super Admin';
    } elseif (in_array(strtolower($email), ['suraj@staff.infinitycomputer.in'])) {
        $role = 'Admin/Accounts';
    }

    // Success — create authenticated session
    $_SESSION['staff_logged_in'] = true;
    $_SESSION['staff_email'] = $email;
    $_SESSION['staff_name'] = $name;
    $_SESSION['staff_role'] = $role;
    $_SESSION['staff_login_time'] = time();
    $_SESSION['staff_last_activity'] = time();

    // Clean up OTP data
    unset($_SESSION['otp_code'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts'], $_SESSION['otp_last_sent']);

    echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => 'index.php']);
} else {
    $_SESSION['otp_attempts']++;
    $remaining = 3 - $_SESSION['otp_attempts'];

    if ($remaining <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.', 'blocked' => true]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Invalid OTP. {$remaining} attempt(s) remaining.", 'remaining' => $remaining]);
    }
}
?>
