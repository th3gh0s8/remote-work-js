<?php
// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if file data is present
if (!isset($_POST['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file data provided']);
    exit;
}

// Get the file data and metadata
$fileData = $_POST['file']; // This is the base64 encoded file
$userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
$brId = isset($_POST['brId']) ? intval($_POST['brId']) : 0;
$filename = isset($_POST['filename']) ? basename($_POST['filename']) : 'recording_' . time();
$type = isset($_POST['type']) ? $_POST['type'] : 'recording';
$description = isset($_POST['description']) ? $_POST['description'] : 'Work Session Recording';

// Decode the base64 file data
$fileBinary = base64_decode($fileData);

if ($fileBinary === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file data']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename to prevent conflicts
$uniqueFilename = uniqid() . '_' . $filename;
$uploadPath = $uploadDir . $uniqueFilename;

// Attempt to save the file
if (file_put_contents($uploadPath, $fileBinary)) {
    // File saved successfully
    $response = [
        'success' => true,
        'fileId' => uniqid(), // Generate a unique ID for the file
        'filename' => $uniqueFilename,
        'path' => $uploadPath,
        'size' => strlen($fileBinary),
        'userId' => $userId,
        'brId' => $brId,
        'type' => $type,
        'description' => $description,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
} else {
    // Failed to save file
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save file',
        'details' => error_get_last()['message'] ?? 'Unknown error'
    ]);
}
?>