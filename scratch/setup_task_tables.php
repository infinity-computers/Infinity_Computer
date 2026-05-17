<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';
require_once __DIR__ . '/../config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 1. Create tasks table
    $q1 = "CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id VARCHAR(50) UNIQUE NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        phone VARCHAR(20) DEFAULT NULL,
        customer_name VARCHAR(100) DEFAULT NULL,
        priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
        assigned_to VARCHAR(100) NOT NULL,
        status ENUM('Pending', 'Accepted', 'In Progress', 'On Hold', 'Completed', 'Rejected') DEFAULT 'Pending',
        due_date DATE DEFAULT NULL,
        attachment_path VARCHAR(255) DEFAULT NULL,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX (task_id),
        INDEX (assigned_to),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($q1)) {
        throw new Exception("Error creating tasks table: " . $conn->error);
    }

    // 2. Create task_assignments table
    $q2 = "CREATE TABLE IF NOT EXISTS task_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id VARCHAR(50) NOT NULL,
        assigned_to VARCHAR(100) NOT NULL,
        assigned_by VARCHAR(100) NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
        INDEX (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($q2)) {
        throw new Exception("Error creating task_assignments table: " . $conn->error);
    }

    // 3. Create task_comments table
    $q3 = "CREATE TABLE IF NOT EXISTS task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id VARCHAR(50) NOT NULL,
        comment TEXT NOT NULL,
        comment_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
        INDEX (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($q3)) {
        throw new Exception("Error creating task_comments table: " . $conn->error);
    }

    // 4. Create task_logs table
    $q4 = "CREATE TABLE IF NOT EXISTS task_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id VARCHAR(50) NOT NULL,
        action VARCHAR(255) NOT NULL,
        remarks TEXT DEFAULT NULL,
        done_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
        INDEX (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($q4)) {
        throw new Exception("Error creating task_logs table: " . $conn->error);
    }

    // 5. Create task_reminders table
    $q5 = "CREATE TABLE IF NOT EXISTS task_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id VARCHAR(50) NOT NULL,
        reminder_sent_to VARCHAR(100) NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(task_id) ON DELETE CASCADE,
        INDEX (task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!$conn->query($q5)) {
        throw new Exception("Error creating task_reminders table: " . $conn->error);
    }

    echo "SUCCESS: Task database tables created successfully!";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
