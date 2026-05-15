<?php

/**
 * Send an email regarding the user service request (Approved/Rejected)
 */
function sendUserRequestStatusEmail($email, $name, $service_id, $status) {
    if (empty($email)) return false;

    $subject = "Infinity Computer - Service Request Update ({$service_id})";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    if ($status === 'Approved') {
        $messageBody = "
        <h2 style='color: #22c55e; text-align: center;'>Request Accepted!</h2>
        <p>Dear {$name},</p>
        <p>Great news! Your service request has been <strong>approved</strong>.</p>
        <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
            <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-size: 18px; font-weight: bold;'>{$service_id}</span></p>
        </div>
        <p>Please deliver your device to our office at your earliest convenience so our engineers can begin working on it.</p>
        <p><strong>Office Location:</strong><br>
        Infinity Computer<br>
        First Floor, Zadeshwar Road, Bharuch</p>
        <p>You can track the live status of your device once dropped off by using your Service ID.</p>
        ";
    } elseif ($status === 'Rejected') {
        $messageBody = "
        <h2 style='color: #ef4444; text-align: center;'>Request Status Update</h2>
        <p>Dear {$name},</p>
        <p>We regret to inform you that your service request (<strong>{$service_id}</strong>) has been <strong>rejected</strong> at this time.</p>
        <p>This may be due to incomplete details, unsupported device types, or unavailable parts. Please contact our firm directly for more information.</p>
        <p><strong>Contact Us:</strong> +91 9876543210 (or visit our store)</p>
        ";
    } else {
        return false; // Don't send for other intermediate statuses unless requested
    }

    $message = "
    <html>
    <head><title>Service Request Update</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            {$messageBody}
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Track Status</a>
            </div>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($email, $subject, $message, $headers);
}

/**
 * Send an email regarding Home Service Request (Approved/Rejected)
 */
function sendHomeServiceStatusEmail($email, $name, $service_id, $status) {
    if (empty($email)) return false;

    $subject = "Infinity Computer - Home Service Update ({$service_id})";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    if ($status === 'Approved') {
        $messageBody = "
        <h2 style='color: #22c55e; text-align: center;'>Booking Accepted!</h2>
        <p>Dear {$name},</p>
        <p>Congratulations! Your home service request (<strong>{$service_id}</strong>) has been <strong>accepted</strong>.</p>
        <p>Our team is reviewing your preferred schedule, and soon one of our engineers will contact you or be at your location for your service.</p>
        <p>Thank you for choosing Infinity Computer for your service needs.</p>
        ";
    } elseif ($status === 'Rejected') {
        $messageBody = "
        <h2 style='color: #ef4444; text-align: center;'>Booking Status Update</h2>
        <p>Dear {$name},</p>
        <p>We regret to inform you that your home service request (<strong>{$service_id}</strong>) has been <strong>rejected</strong>.</p>
        <p>This could be due to unavailability of slots in your area or service constraints. Please contact our firm directly for assistance.</p>
        <p><strong>Contact Us:</strong> +91 9876543210</p>
        ";
    } else {
        return false;
    }

    $message = "
    <html>
    <head><title>Home Service Update</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            {$messageBody}
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Track Booking</a>
            </div>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($email, $subject, $message, $headers);
}

/**
 * Send an email when the actual service status changes (Completed, Cancelled, Delivered, etc.)
 */
function sendServiceStatusUpdateEmail($email, $name, $service_id, $status, $device_name) {
    if (empty($email)) return false;

    $subject = "Infinity Computer - Device Service Update ({$service_id})";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $statusColor = "#0d6efd";
    $statusMessage = "There is an update regarding your device service.";

    if ($status === 'Completed' || $status === 'Ready for Pickup') {
        $statusColor = "#22c55e";
        $statusMessage = "Great news! The service for your device is now <strong>completed</strong> and it is ready.";
    } elseif ($status === 'Delivered') {
        $statusColor = "#10b981";
        $statusMessage = "Your device has been marked as <strong>delivered</strong>. Thank you for choosing us!";
    } elseif ($status === 'Cancelled') {
        $statusColor = "#ef4444";
        $statusMessage = "Your device service has been <strong>cancelled</strong>. Please contact us for details.";
    } else {
        $statusMessage = "The status of your device has been updated to: <strong>{$status}</strong>.";
    }

    $message = "
    <html>
    <head><title>Device Service Update</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            <h2 style='color: {$statusColor}; text-align: center;'>Service Status: {$status}</h2>
            <p>Dear {$name},</p>
            <p>{$statusMessage}</p>
            
            <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
                <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-weight: bold;'>{$service_id}</span></p>
                <p style='margin: 5px 0;'><strong>Device:</strong> {$device_name}</p>
                <p style='margin: 5px 0;'><strong>Current Status:</strong> <span style='color: {$statusColor}; font-weight: bold;'>{$status}</span></p>
            </div>

            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>View Full Details</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($email, $subject, $message, $headers);
}

/**
 * Send an email when the request is created by Admin but device is NOT received yet.
 */
function sendPendingDropoffEmail($email, $name, $service_id) {
    if (empty($email)) return false;

    $subject = "Infinity Computer - Request Created, Pending Device Drop-off ({$service_id})";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $messageBody = "
    <h2 style='color: #f59e0b; text-align: center;'>Action Required: Drop off your device</h2>
    <p>Dear {$name},</p>
    <p>A service request (<strong>{$service_id}</strong>) has been successfully generated for you.</p>
    <p>However, we have <strong>not yet received your device</strong> at our station. To proceed with the service, please bring your device to our office at your earliest convenience.</p>
    <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
        <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-size: 18px; font-weight: bold;'>{$service_id}</span></p>
    </div>
    <p><strong>Office Location:</strong><br>
    Infinity Computer<br>
    First Floor, Zadeshwar Road, Bharuch</p>
    <p>Once you drop off your device, our engineers will begin working on it, and you can track the live status using your Service ID.</p>
    ";

    $message = "
    <html>
    <head><title>Pending Device Drop-off</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            {$messageBody}
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/track-request.html' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Track Status</a>
            </div>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($email, $subject, $message, $headers);
}


/**
 * Send an email to the engineer when a task is assigned or re-assigned
 */
function sendEngineerAssignmentEmail($engineer_email, $engineer_name, $service_id, $customer_name, $device_name, $problem, $is_reassignment = false) {
    if (empty($engineer_email)) return false;

    $subject = $is_reassignment ? "Infinity Computer - Task Re-assigned ({$service_id})" : "Infinity Computer - New Task Assigned ({$service_id})";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $title = $is_reassignment ? "Task Re-assigned to You" : "New Task Assigned to You";
    $statusMsg = $is_reassignment ? "A service task has been re-assigned to you for further action." : "A new service task has been assigned to you.";

    $message = "
    <html>
    <head><title>{$title}</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
            <h2 style='color: #0d6efd; text-align: center;'>{$title}</h2>
            <p>Hello {$engineer_name},</p>
            <p>{$statusMsg}</p>
            
            <div style='background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #cbd5e1;'>
                <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-weight: bold;'>{$service_id}</span></p>
                <p style='margin: 5px 0;'><strong>Customer:</strong> {$customer_name}</p>
                <p style='margin: 5px 0;'><strong>Device:</strong> {$device_name}</p>
                <p style='margin: 5px 0;'><strong>Problem:</strong> {$problem}</p>
            </div>

            <p>Please check the Service Panel for more details and start working on this task.</p>

            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/service-panel/login.php' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Open Service Panel</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($engineer_email, $subject, $message, $headers);
}

/**
 * Send a reminder email to the engineer if a task hasn't been updated
 */
function sendEngineerReminderEmail($engineer_email, $engineer_name, $service_id, $customer_name, $device_name, $last_update) {
    if (empty($engineer_email)) return false;

    $subject = "REMINDER: Action Required for Task {$service_id}";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $message = "
    <html>
    <head><title>Task Reminder</title></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; border-top: 5px solid #f59e0b;'>
            <h2 style='color: #f59e0b; text-align: center;'>Task Update Reminder</h2>
            <p>Hello {$engineer_name},</p>
            <p>This is a reminder that the following task assigned to you has not been updated recently.</p>
            
            <div style='background: #fffbeb; padding: 15px; border-radius: 6px; margin: 20px 0; border: 1px dashed #f59e0b;'>
                <p style='margin: 5px 0;'><strong>Service ID:</strong> <span style='color: #0d6efd; font-weight: bold;'>{$service_id}</span></p>
                <p style='margin: 5px 0;'><strong>Customer:</strong> {$customer_name}</p>
                <p style='margin: 5px 0;'><strong>Device:</strong> {$device_name}</p>
                <p style='margin: 5px 0;'><strong>Last Updated:</strong> {$last_update}</p>
            </div>

            <p>Please update the task status or add a remark in the Service Panel to keep the customer informed.</p>

            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/service-panel/login.php' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Open Service Panel</a>
            </div>

            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='font-size: 12px; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($engineer_email, $subject, $message, $headers);
}

?>
