<?php
require_once 'config/db.php';
$res1 = $conn->query("SELECT COUNT(*) as count FROM user_service_requests");
$row1 = $res1->fetch_assoc();
echo "user_service_requests: " . $row1['count'] . "\n";

$res2 = $conn->query("SELECT COUNT(*) as count FROM home_service_requests");
$row2 = $res2->fetch_assoc();
echo "home_service_requests: " . $row2['count'] . "\n";
?>
