<?php
// Simple database connection test
header('Content-Type: application/json');

// Set timezone to ensure consistent datetime values
date_default_timezone_set('Asia/Colombo'); // Sri Lanka timezone

$servername = "localhost"; // or "206.72.199.6" if different server
$username = "stcloudb_104u";
$password = "104-2019-08-10";
$dbname = "stcloudb_104";
$port = 3306;

try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful!',
        'host_info' => $conn->host_info,
        'server_info' => $conn->server_info
    ]);
    
    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?>