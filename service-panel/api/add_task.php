<?php
include __DIR__ . '/../auth_guard.php';
require_once '../../config/db.php';
require_once 'task_email_helper.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verify reCAPTCHA
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    if (empty($recaptchaResponse)) {
        echo json_encode(['status' => 'error', 'message' => 'Please solve the reCAPTCHA first.']);
        exit;
    }

    $recaptchaSecret = '6LcadY0sAAAAAE-ADcAzbPWGpJLAdi1oW2jLB4Qe';
    $verifyUrl = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptchaSecret}&response={$recaptchaResponse}";
    
    $verifyResponse = @file_get_contents($verifyUrl);
    $responseData = json_decode($verifyResponse);
    if (!$responseData || !$responseData->success) {
        echo json_encode(['status' => 'error', 'message' => 'Robot verification failed. Please try again.']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';
    $assigned_to = $_POST['assigned_to'] ?? '';
    $due_date = $_POST['due_date'] ?? '';

    // Basic Validation
    if (empty($title) || empty($description) || empty($assigned_to)) {
        echo json_encode(['status' => 'error', 'message' => 'Title, Description, and Assigned Engineer are required fields']);
        exit;
    }

    $allowed_priorities = ['Low', 'Medium', 'High', 'Urgent'];
    if (!in_array($priority, $allowed_priorities)) {
        $priority = 'Medium';
    }

    // Validate due date format
    if (!empty($due_date)) {
        $due_date_timestamp = strtotime($due_date);
        if (!$due_date_timestamp) {
            $due_date = null;
        } else {
            $due_date = date('Y-m-d', $due_date_timestamp);
        }
    } else {
        $due_date = null;
    }

    // Handle Attachment Upload
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileSize = $_FILES['attachment']['size'];
        $fileType = $_FILES['attachment']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Allowed file types
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'doc', 'txt', 'zip'];
        if (in_array($fileExtension, $allowedExtensions)) {
            // Check size (max 10MB)
            if ($fileSize <= 10485760) {
                // Ensure directory exists
                $uploadFileDir = '../uploads/tasks/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }
                
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $attachment_path = 'uploads/tasks/' . $newFileName;
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'There was an error moving the uploaded file']);
                    exit;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'File size exceeds limit (max 10MB)']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Upload failed. Allowed extensions: ' . implode(', ', $allowedExtensions)]);
            exit;
        }
    }

    // Generate Task ID
    $conn->begin_transaction();
    try {
        $year = date('Y');
        $stmt_check = $conn->prepare("SELECT id FROM tasks WHERE task_id = ?");
        do {
            $count_res = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE task_id LIKE 'TSK-$year-%'");
            $count_row = $count_res->fetch_assoc();
            $seq = str_pad(intval($count_row['count']) + 1, 4, '0', STR_PAD_LEFT);
            $task_id = "TSK-{$year}-{$seq}";
            
            $stmt_check->bind_param("s", $task_id);
            $stmt_check->execute();
            $exists = $stmt_check->get_result()->fetch_assoc();
            if (!$exists) {
                break;
            }
        } while (true);

        $created_by = $_SESSION['staff_email'] ?? 'System';

        // 1. Insert Task Record
        $stmt_ins = $conn->prepare("INSERT INTO tasks (task_id, title, description, phone, customer_name, priority, assigned_to, status, due_date, attachment_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)");
        $stmt_ins->bind_param("ssssssssss", $task_id, $title, $description, $phone, $customer_name, $priority, $assigned_to, $due_date, $attachment_path, $created_by);
        $stmt_ins->execute();

        // 2. Save Initial Task Assignment
        $stmt_assign = $conn->prepare("INSERT INTO task_assignments (task_id, assigned_to, assigned_by) VALUES (?, ?, ?)");
        $stmt_assign->bind_param("sss", $task_id, $assigned_to, $created_by);
        $stmt_assign->execute();

        // 3. Log task creation
        $log_action = "Task Created";
        $log_remarks = "Task generated and assigned to {$assigned_to}";
        $stmt_log = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, ?, ?, ?)");
        $stmt_log->bind_param("ssss", $task_id, $log_action, $log_remarks, $created_by);
        $stmt_log->execute();

        $conn->commit();

        // Send email to assigned engineer
        $sent_email = sendTaskAssignmentEmail($task_id, $title, $description, $created_by, $priority, $phone, $due_date, $assigned_to);

        // Include log of email sending
        if ($sent_email) {
            $stmt_log_mail = $conn->prepare("INSERT INTO task_logs (task_id, action, remarks, done_by) VALUES (?, 'Email Sent', 'Assignment email notification sent to {$assigned_to}', 'System')");
            $stmt_log_mail->bind_param("s", $task_id);
            $stmt_log_mail->execute();
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Task successfully created',
            'task_id' => $task_id
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
