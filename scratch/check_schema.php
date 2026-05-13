<?php
require 'config/db.php';
$res = $conn->query('SHOW COLUMNS FROM services');
echo "Services columns:\n";
while($row = $res->fetch_assoc()) echo $row['Field'] . " ";
echo "\n\nUser Service Requests columns:\n";
$res2 = $conn->query('SHOW COLUMNS FROM user_service_requests');
while($row = $res2->fetch_assoc()) echo $row['Field'] . " ";
?>
