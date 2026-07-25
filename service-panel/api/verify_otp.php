<?php
session_start();
header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enteredOtp = trim($input['otp'] ?? '');

function respond(array $payload, int $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

// Ensure OTP session data exists
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email'])) {
    respond(['status' => 'error', 'message' => 'No OTP request found. Please request a new OTP.', 'expired' => true]);
}

// OTP expiration check (5 minutes)
if ((time() - $_SESSION['otp_timestamp']) > 300) {
    unset($_SESSION['otp_code'], $_SESSION['otp_email'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts']);
    respond(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
}

// Max attempts check
if (isset($_SESSION['otp_attempts']) && $_SESSION['otp_attempts'] >= 3) {
    respond(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.', 'blocked' => true]);
}

// Validate OTP format
if (empty($enteredOtp) || strlen($enteredOtp) !== 6) {
    respond(['status' => 'error', 'message' => 'Please enter a valid 6-digit OTP']);
}

// Successful OTP verification
if ($enteredOtp === $_SESSION['otp_code']) {
    require_once __DIR__ . '/../config/db.php';
    $email = $_SESSION['otp_email'];
    $role  = 'Engineer';
    $name  = 'Staff Member';

    $stmt = $conn->prepare("SELECT name, role FROM engineers WHERE email = ? OR LOWER(email) = LOWER(?)");
    if ($stmt) {
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $name = $row['name'];
            $role = $row['role'] ?: 'Engineer';
        }
        $stmt->close();
    }

    // Role overrides for known admin accounts
    $lowerEmail = strtolower($email);
    if ($lowerEmail === 'icc@infinitycomputer.in') {
        $role = 'Super Admin';
    } elseif ($lowerEmail === 'suraj@staff.infinitycomputer.in') {
        $role = 'Admin/Accounts';
    }

    // Set authenticated staff session
    $_SESSION['staff_logged_in']   = true;
    $_SESSION['staff_email']       = $email;
    $_SESSION['staff_name']        = $name;
    $_SESSION['staff_role']        = $role;
    $_SESSION['staff_login_time']  = time();
    $_SESSION['staff_last_activity'] = time();

    // Clean OTP related session data
    unset($_SESSION['otp_code'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts'], $_SESSION['otp_last_sent']);

    respond(['status' => 'success', 'message' => 'Login successful!', 'redirect' => 'index.php']);
} else {
    // Increment failed attempts
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    $remaining = max(0, 3 - $_SESSION['otp_attempts']);
    if ($remaining <= 0) {
        respond(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.', 'blocked' => true]);
    } else {
        respond(['status' => 'error', 'message' => "Invalid OTP. {$remaining} attempt(s) remaining.", 'remaining' => $remaining]);
    }
}
?>
