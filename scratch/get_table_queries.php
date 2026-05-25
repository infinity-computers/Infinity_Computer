<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = 'C:/xampp/htdocs';

require_once __DIR__ . '/../config/db.php';

$new_tables = ['engineers', 'tasks', 'task_assignments', 'task_comments', 'task_logs', 'task_reminders'];

foreach ($new_tables as $table) {
    echo "================================================================================\n";
    echo "TABLE: $table\n";
    echo "================================================================================\n";
    
    $res = $conn->query("SHOW CREATE TABLE `$table`");
    if ($res) {
        $row = $res->fetch_row();
        echo $row[1] . ";\n\n";
    } else {
        echo "Error fetching table details: " . $conn->error . "\n\n";
    }
}
?>
