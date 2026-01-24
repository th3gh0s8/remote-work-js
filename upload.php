<?php
// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Log incoming request for debugging
error_log("Upload request received with Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown'));

// Determine if the request contains multipart form data or raw binary data
$isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

if ($isMultipart) {
    // Handle multipart form data (traditional file upload)
    error_log("Processing multipart form data");

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file data provided in multipart request']);
        exit;
    }

    // Get the file data and metadata
    $uploadedFile = $_FILES['file'];
    $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
    $brId = isset($_POST['brId']) ? intval($_POST['brId']) : 0;
    $filename = isset($_POST['filename']) ? basename($_POST['filename']) : 'recording_' . time();
    $type = isset($_POST['type']) ? $_POST['type'] : 'recording';
    $description = isset($_POST['description']) ? $_POST['description'] : 'Work Session Recording';

    error_log("File upload params - UserId: $userId, BrId: $brId, Filename: $filename");

    // Validate uploaded file
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload error: ' . $uploadedFile['error']]);
        exit;
    }

    // Get the temporary file path
    $tempFilePath = $uploadedFile['tmp_name'];

    // Validate the temporary file exists
    if (!file_exists($tempFilePath)) {
        http_response_code(400);
        echo json_encode(['error' => 'Temporary file does not exist at path: ' . $tempFilePath]);
        exit;
    }

    // Read the binary data from the uploaded file
    $fileBinary = file_get_contents($tempFilePath);

    if ($fileBinary === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not read temporary file']);
        exit;
    }
} else {
    // Handle raw binary data in request body
    error_log("Processing raw binary data");

    $fileBinary = file_get_contents('php://input');

    if ($fileBinary === false || strlen($fileBinary) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'No file data provided or empty request body']);
        exit;
    }

    // Get metadata from headers
    $userId = isset($_SERVER['HTTP_X_USER_ID']) ? intval($_SERVER['HTTP_X_USER_ID']) : 0;
    $brId = isset($_SERVER['HTTP_X_BR_ID']) ? intval($_SERVER['HTTP_X_BR_ID']) : 0;
    $filename = isset($_SERVER['HTTP_X_FILENAME']) ? basename($_SERVER['HTTP_X_FILENAME']) : 'recording_' . time();
    $type = isset($_SERVER['HTTP_X_TYPE']) ? $_SERVER['HTTP_X_TYPE'] : 'recording';
    $description = isset($_SERVER['HTTP_X_DESCRIPTION']) ? $_SERVER['HTTP_X_DESCRIPTION'] : 'Work Session Recording';

    error_log("Raw data upload params - UserId: $userId, BrId: $brId, Filename: $filename");
}

// Additional validation: Check if the data looks like a valid video file
// WebM files typically start with EBML header
if (strlen($fileBinary) >= 4) {
    $header = substr($fileBinary, 0, 4);
    $validVideoHeader = false;

    // Check for WebM/EBML header (starts with 0x1A45DF)
    if (substr($fileBinary, 0, 3) === "\x1A\x45\xDF") {
        $validVideoHeader = true;
    }

    if (!$validVideoHeader) {
        error_log("Warning: Uploaded file doesn't have expected WebM header. First 4 bytes: " . bin2hex($header));
        // We'll still save it, but log the issue
    }
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not create uploads directory']);
        exit;
    }
}

// Generate unique filename to prevent conflicts, preserving original extension
$fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
$uniqueFilename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
if ($fileExtension) {
    $uniqueFilename .= '.' . $fileExtension;
}
$uploadPath = $uploadDir . $uniqueFilename;

error_log("Saving file to: $uploadPath");

// Attempt to save the file
if (file_put_contents($uploadPath, $fileBinary) !== false) {
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
    error_log("Failed to save file to: $uploadPath");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save file',
        'details' => error_get_last()['message'] ?? 'Unknown error'
    ]);
}
?>