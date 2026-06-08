<?php
include __DIR__.'/../../config/db.php';
if ($conn->connect_error) { die('Connect error: '.$conn->connect_error); }
$result = $conn->query('SHOW TABLES');
if (!$result) { die('Query error: '.$conn->error); }
while($row = $result->fetch_array()) {
    echo $row[0]."\n";
}
?>
