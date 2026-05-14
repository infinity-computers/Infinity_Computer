<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST['name'] = 'Test';
$_POST['phone'] = '1234567890';
$_POST['email'] = 'test@example.com';
$_POST['address'] = 'Test Address';
$_POST['device_type'] = 'Laptop Repair';
$_POST['problem'] = 'Broken screen';

$_FILES['image'] = [
    'name' => 'test.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => __DIR__ . '/../test.png', // Just dummy, it won't be a valid uploaded file, but we can see if it reaches logic
    'error' => 0,
    'size' => 1000
];

require 'service-panel/api/add_user_request.php';
?>
