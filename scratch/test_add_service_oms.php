<?php
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mock post data for testing add_service.php
$_POST['name'] = 'Test OMS Customer';
$_POST['phone'] = '9998887776';
$_POST['email'] = 'test@example.com';
$_POST['service_type'] = 'Display/Screen Repair';
$_POST['device_name'] = 'Dell XPS 15';
$_POST['company'] = 'Infinity Test';
$_POST['problem'] = 'Screen flicker issue';
$_POST['g-recaptcha-response'] = 'mock_pass';

// Mock files
$_FILES['images'] = [
    'name' => ['test.jpg'],
    'type' => ['image/jpeg'],
    'tmp_name' => [__DIR__ . '/../test.png'],
    'error' => [0],
    'size' => [50317]
];

ob_start();
include __DIR__ . '/../service-panel/api/add_service.php';
$output = ob_get_clean();

echo "TEST SUBMISSION OUTPUT:\n" . $output . "\n";
?>
