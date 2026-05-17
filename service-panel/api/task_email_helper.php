<?php
/**
 * Task Management Email Utility
 * Sends emails to assigned engineers for assignments, reassignments, and reminders.
 */

// Engineer email mapping
function getEngineerEmailMap() {
    return [
        'Suraj' => 'suraj@staff.infinitycomputer.in',
        'Akshar' => 'akshar@staff.infinitycomputer.in',
        'Karan' => 'karan@staff.infinitycomputer.in',
        'Rahul' => 'rahul@staff.infinitycomputer.in',
        'Paresh' => 'paresh@staff.infinitycomputer.in',
        'Om' => 'om@dev.infinitycomputer.in',
        'Jatin' => 'jatin@dev.infinitycomputer.in'
    ];
}

/**
 * Send email when a task is first assigned to an engineer
 */
function sendTaskAssignmentEmail($task_id, $title, $description, $assigned_by, $priority, $phone, $due_date, $assignee_name) {
    $emails = getEngineerEmailMap();
    $to = $emails[$assignee_name] ?? '';
    if (empty($to)) return false;

    $subject = "🔧 Infinity Computer - New Task Assigned [{$task_id}]";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $priorityColors = [
        'Low' => '#64748b',
        'Medium' => '#3b82f6',
        'High' => '#f59e0b',
        'Urgent' => '#ef4444'
    ];
    $pColor = $priorityColors[$priority] ?? '#3b82f6';

    $message = "
    <html>
    <head>
        <title>New Task Assigned</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 600px; margin: 30px auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 25px;'>
                <h2 style='color: #1f5fae; margin: 0;'>Infinity Computer</h2>
                <p style='color: #64748b; font-size: 14px; margin: 5px 0 0;'>Task Management Portal</p>
            </div>
            
            <h3 style='color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;'>📥 New Task Assigned to You</h3>
            
            <p>Hello <strong>{$assignee_name}</strong>,</p>
            <p>A new internal task has been assigned to you. Below are the details:</p>
            
            <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b; width: 120px;'>Task ID:</td>
                        <td style='padding: 6px 0; font-weight: bold; color: #1f5fae;'>{$task_id}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Title:</td>
                        <td style='padding: 6px 0; font-weight: 600; color: #0f172a;'>{$title}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Priority:</td>
                        <td style='padding: 6px 0;'><span style='background: {$pColor}; color: #ffffff; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;'>{$priority}</span></td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Assigned By:</td>
                        <td style='padding: 6px 0; color: #334155;'>{$assigned_by}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Due Date:</td>
                        <td style='padding: 6px 0; color: #334155; font-weight: 600;'>" . ($due_date ? date('d-M-Y', strtotime($due_date)) : 'No due date') . "</td>
                    </tr>
                    " . ($phone ? "
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Contact Phone:</td>
                        <td style='padding: 6px 0; color: #334155;'>{$phone}</td>
                    </tr>" : "") . "
                </table>
                
                <div style='margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;'>
                    <strong style='color: #64748b; display: block; margin-bottom: 5px;'>Description / Instructions:</strong>
                    <p style='margin: 0; color: #334155; white-space: pre-wrap;'>{$description}</p>
                </div>
            </div>
            
            <p style='font-size: 14px; color: #475569;'>Please acknowledge and begin working on this task as soon as possible. Be sure to keep the status updated.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/service-panel/task_management.php' style='background: #1f5fae; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px rgba(31,95,174,0.15);'>Open Task Portal</a>
            </div>
            
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='font-size: 11px; color: #94a3b8; text-align: center;'>
                This is an automated system notification from Infinity Computer. Please do not reply directly to this email.<br>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($to, $subject, $message, $headers);
}

/**
 * Send email when a task is re-assigned to a new engineer
 */
function sendTaskReassignmentEmail($task_id, $title, $description, $reassigned_by, $priority, $due_date, $new_assignee, $prev_assignee, $reassigned_at) {
    $emails = getEngineerEmailMap();
    $to = $emails[$new_assignee] ?? '';
    if (empty($to)) return false;

    $subject = "🔄 Infinity Computer - Task Re-assigned [{$task_id}]";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $priorityColors = [
        'Low' => '#64748b',
        'Medium' => '#3b82f6',
        'High' => '#f59e0b',
        'Urgent' => '#ef4444'
    ];
    $pColor = $priorityColors[$priority] ?? '#3b82f6';

    $message = "
    <html>
    <head>
        <title>Task Reassigned</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 600px; margin: 30px auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 25px;'>
                <h2 style='color: #1f5fae; margin: 0;'>Infinity Computer</h2>
                <p style='color: #64748b; font-size: 14px; margin: 5px 0 0;'>Task Management Portal</p>
            </div>
            
            <h3 style='color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;'>🔄 Task Re-assigned to You</h3>
            
            <p>Hello <strong>{$new_assignee}</strong>,</p>
            <p>An ongoing task has been re-assigned to you from <strong>{$prev_assignee}</strong>. Below are the details:</p>
            
            <div style='background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #bbf7d0; color: #166534; font-weight: 500; font-size: 14px; margin-bottom: 20px;'>
                Reassigned by <strong>{$reassigned_by}</strong> on " . date('d-M-Y h:i A', strtotime($reassigned_at)) . "
            </div>
            
            <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b; width: 120px;'>Task ID:</td>
                        <td style='padding: 6px 0; font-weight: bold; color: #1f5fae;'>{$task_id}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Title:</td>
                        <td style='padding: 6px 0; font-weight: 600; color: #0f172a;'>{$title}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Priority:</td>
                        <td style='padding: 6px 0;'><span style='background: {$pColor}; color: #ffffff; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;'>{$priority}</span></td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Previous Owner:</td>
                        <td style='padding: 6px 0; color: #475569; text-decoration: line-through;'>{$prev_assignee}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Due Date:</td>
                        <td style='padding: 6px 0; color: #334155; font-weight: 600;'>" . ($due_date ? date('d-M-Y', strtotime($due_date)) : 'No due date') . "</td>
                    </tr>
                </table>
                
                <div style='margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;'>
                    <strong style='color: #64748b; display: block; margin-bottom: 5px;'>Description / Instructions:</strong>
                    <p style='margin: 0; color: #334155; white-space: pre-wrap;'>{$description}</p>
                </div>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/service-panel/task_management.php' style='background: #1f5fae; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px rgba(31,95,174,0.15);'>Open Task Portal</a>
            </div>
            
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='font-size: 11px; color: #94a3b8; text-align: center;'>
                This is an automated system notification from Infinity Computer. Please do not reply directly to this email.<br>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($to, $subject, $message, $headers);
}

/**
 * Send a reminder email to the engineer if a task remains inactive
 */
function sendTaskReminderEmail($task_id, $title, $status, $hours_inactive, $assignee_name) {
    $emails = getEngineerEmailMap();
    $to = $emails[$assignee_name] ?? '';
    if (empty($to)) return false;

    $subject = "⏰ REMINDER: Inactive Task Action Required [{$task_id}]";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Infinity Computer <noreply@infinitycomputer.in>" . "\r\n";

    $message = "
    <html>
    <head>
        <title>Task Inactivity Reminder</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
        <div style='max-width: 600px; margin: 30px auto; padding: 25px; border: 1px solid #f59e0b; border-radius: 12px; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 25px;'>
                <h2 style='color: #d97706; margin: 0;'>Infinity Computer</h2>
                <p style='color: #64748b; font-size: 14px; margin: 5px 0 0;'>Task Management Portal</p>
            </div>
            
            <h3 style='color: #b45309; border-bottom: 2px solid #fef3c7; padding-bottom: 10px;'>⏰ Task Inactivity Alert</h3>
            
            <p>Hello <strong>{$assignee_name}</strong>,</p>
            <p>This is an automated reminder that a task assigned to you has been inactive (without any updates) for over <strong>{$hours_inactive} hours</strong>.</p>
            
            <div style='background: #fffbeb; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #fef3c7;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #b45309; width: 120px;'>Task ID:</td>
                        <td style='padding: 6px 0; font-weight: bold; color: #b45309;'>{$task_id}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Title:</td>
                        <td style='padding: 6px 0; font-weight: 600; color: #0f172a;'>{$title}</td>
                    </tr>
                    <tr>
                        <td style='padding: 6px 0; font-weight: bold; color: #64748b;'>Current Status:</td>
                        <td style='padding: 6px 0;'><span style='background: #d97706; color: #ffffff; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;'>{$status}</span></td>
                    </tr>
                </table>
            </div>
            
            <p style='font-size: 14px; color: #475569;'>Please log in to the portal and update the status, or add a brief comment to explain the current progress.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://infinitycomputer.in/service-panel/task_management.php' style='background: #d97706; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px rgba(217,119,6,0.15);'>Open Task Portal</a>
            </div>
            
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='font-size: 11px; color: #94a3b8; text-align: center;'>
                This is an automated system notification from Infinity Computer. Please do not reply directly to this email.<br>
                &copy; " . date('Y') . " Infinity Computer. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";

    return @mail($to, $subject, $message, $headers);
}
?>
