<?php
session_start();
header('Content-Type: application/json');

// Catch any uncaught exceptions/errors and return JSON
set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

set_error_handler(function($errno, $errstr) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $errstr]);
    exit;
});

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$enteredOtp = trim($input['otp'] ?? '');

// Ensure OTP session data exists
if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'No OTP request found. Please request a new OTP.', 'expired' => true]);
    exit;
}

// OTP expiration check (5 minutes)
if (!isset($_SESSION['otp_timestamp']) || (time() - $_SESSION['otp_timestamp']) > 300) {
    unset($_SESSION['otp_code'], $_SESSION['otp_email'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts']);
    echo json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.', 'expired' => true]);
    exit;
}

// Max attempts check
if (isset($_SESSION['otp_attempts']) && $_SESSION['otp_attempts'] >= 3) {
    echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.', 'blocked' => true]);
    exit;
}

// Validate OTP format
if (empty($enteredOtp) || strlen($enteredOtp) !== 6) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 6-digit OTP']);
    exit;
}

// Verify OTP value
if ($enteredOtp === $_SESSION['otp_code']) {
    // Load DB connection safely
    $email = $_SESSION['otp_email'];
    $role  = 'Engineer';
    $emailPrefix = explode('@', $email)[0];
    $name  = ucfirst($emailPrefix);

    try {
        require_once __DIR__ . '/../config/db.php';
        $stmt = $conn->prepare("SELECT name, role FROM engineers WHERE LOWER(email) = LOWER(?) OR LOWER(name) = LOWER(?)");
        if ($stmt) {
            $stmt->bind_param('ss', $email, $emailPrefix);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['name'])) {
                    $name = $row['name'];
                }
                $role = $row['role'] ?: 'Engineer';
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // DB failed — still allow login with defaults, don't block
    }

    // Role overrides for known admin accounts
    $lowerEmail = strtolower($email);
    if ($lowerEmail === 'icc@infinitycomputer.in') {
        $role = 'Super Admin';
    } elseif ($lowerEmail === 'suraj@staff.infinitycomputer.in') {
        $role = 'Admin/Accounts';
    }

    // Set authenticated staff session
    $_SESSION['staff_logged_in']     = true;
    $_SESSION['staff_email']         = $email;
    $_SESSION['staff_name']          = $name;
    $_SESSION['staff_role']          = $role;
    $_SESSION['staff_login_time']    = time();
    $_SESSION['staff_last_activity'] = time();

    // Clean OTP related session data
    unset($_SESSION['otp_code'], $_SESSION['otp_timestamp'], $_SESSION['otp_attempts'], $_SESSION['otp_last_sent']);

    echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'redirect' => 'index.php']);
    exit;
} else {
    // Increment failed attempts
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    $remaining = max(0, 3 - $_SESSION['otp_attempts']);
    if ($remaining <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Try again later.', 'blocked' => true]);
    } else {
        echo json_encode(['status' => 'error', 'message' => "Invalid OTP. {$remaining} attempt(s) remaining.", 'remaining' => $remaining]);
    }
    exit;
}
?>
