<?php
require_once __DIR__ . '/config/db.php';
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    echo $row[0] . "\n";
}
$res = $conn->query("DESCRIBE engineers");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "engineers table does not exist.\n";
}
?>
