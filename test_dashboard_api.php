<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'owner';

// Call the actual owner_api.php
$apiUrl = 'http://localhost/egg-farm-inventory-main1/owner_api.php';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id()
    ]
]);

$result = file_get_contents($apiUrl, false, $context);
echo $result;
?>
