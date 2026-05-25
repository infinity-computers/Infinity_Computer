<?php
session_start();
header('Content-Type: application/json');

$allowedEmails = [
    'akshar@staff.infinitycomputer.in',
    'karan@staff.infinitycomputer.in',
    'suraj@staff.infinitycomputer.in',
    'rahul@staff.infinitycomputer.in',
    'paresh@staff.infinitycomputer.in',
    'om@dev.infinitycomputer.in',
    'jatin@dev.infinitycomputer.in',
    'icc@infinitycomputer.in',
    'pacifier2204@gmail.com',
    'rathorjatin70@gmail.com'
];

$input = json_decode(file_get_contents('php://input'), true);
$email = trim(strtolower($input['email'] ?? ''));

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Email address is required']);
    exit;
}

if (!in_array($email, $allowedEmails)) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized email address']);
    exit;
}

// Cooldown check (30 seconds between sends)
if (isset($_SESSION['otp_last_sent']) && (time() - $_SESSION['otp_last_sent']) < 30) {
    $remaining = 30 - (time() - $_SESSION['otp_last_sent']);
    echo json_encode(['status' => 'error', 'message' => "Please wait {$remaining} seconds before requesting a new OTP"]);
    exit;
}

// Generate 6-digit OTP
$otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

// Store in session
$_SESSION['otp_code'] = $otp;
$_SESSION['otp_email'] = $email;
$_SESSION['otp_timestamp'] = time();
$_SESSION['otp_attempts'] = 0;
$_SESSION['otp_last_sent'] = time();

// Send email
$staffName = ucfirst(explode('@', $email)[0]);
$sent = sendOtpEmail($email, $staffName, $otp);

if ($sent) {
    echo json_encode(['status' => 'success', 'message' => 'OTP sent to your email']);
} else {
    echo json_encode(['status' => 'success', 'message' => 'OTP generated successfully', 'debug_otp' => $otp]);
}

function sendOtpEmail($email, $name, $otp) {
    $subject = "Infinity Computer - Staff Login OTP";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>\r\n";

    $message = "
    <html>
    <head><title>Staff Login OTP</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 500px; margin: 30px auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;'>
            <div style='text-align: center; margin-bottom: 25px;'>
                <h2 style='color: #1f5fae; margin: 0;'>Infinity Computer</h2>
                <p style='color: #64748b; font-size: 14px; margin: 5px 0 0;'>Staff Portal Login</p>
            </div>
            <p>Hello <strong>{$name}</strong>,</p>
            <p>Your one-time password (OTP) for logging into the Service Panel is:</p>
            <div style='text-align: center; margin: 25px 0;'>
                <div style='display: inline-block; background: #f0f7ff; border: 2px dashed #1f5fae; border-radius: 10px; padding: 20px 40px;'>
                    <span style='font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1f5fae;'>{$otp}</span>
                </div>
            </div>
            <p style='font-size: 14px; color: #64748b;'>This OTP is valid for <strong>5 minutes</strong>. Do not share it with anyone.</p>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='font-size: 12px; color: #94a3b8; text-align: center;'>
                If you did not request this OTP, please ignore this email.<br>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>";

    return @mail($email, $subject, $message, $headers);
}
?>
