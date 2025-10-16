<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$currentVersion = '2.1.0';
$response = [
    'version' => $currentVersion,
    'updateAvailable' => false,
    'timestamp' => time()
];

echo json_encode($response);
?>