<?php
// test_update.php – trigger update_status API with sample data
$payload = json_encode([
    'id' => 1,
    'status' => 'Completed',
    'remarks' => 'Test update from CLI',
    'assigned_engineer' => ''
]);
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'ignore_errors' => true
    ]
];
$context = stream_context_create($opts);
$result = file_get_contents('http://localhost/service-panel/api/update_status.php', false, $context);
if ($result === false) {
    echo "Request failed\n";
} else {
    echo $result . "\n";
}
?>
