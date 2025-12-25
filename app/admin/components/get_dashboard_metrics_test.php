<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Test if we can connect
include '../../../database/config.php';

if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No DB connection']);
    exit;
}

// Simple test query
$result = mysqli_query($conn, "SELECT 1 as test");
if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

// Return minimal success response
echo json_encode([
    'success' => true,
    'test' => 'Database connection OK',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
