<?php
// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Database configuration
$servername = "localhost"; // Change to your actual database server if different
$username = "stcloudb_104u";
$password = "104-2019-08-10";
$dbname = "stcloudb_104";
$port = 3306;

// Create connection
$conn = null;
try {
    $conn = new mysqli($servername, $username, $password, $dbname, $port);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set charset
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Get the action from the request
$action = isset($_POST['action']) ? $_POST['action'] : 'upload';

switch ($action) {
    case 'authenticate':
        handleAuthentication($conn);
        break;
    case 'log_activity':
        handleLogActivity($conn);
        break;
    case 'save_recording_metadata':
        handleSaveRecordingMetadata($conn);
        break;
    case 'ping':
        handlePing();
        break;
    case 'upload':
    default:
        handleFileUpload($conn);
        break;
}

function handleAuthentication($conn) {
    try {
        $repid = isset($_POST['repid']) ? $conn->real_escape_string($_POST['repid']) : '';
        $nic = isset($_POST['nic']) ? $conn->real_escape_string($_POST['nic']) : '';

        if (empty($repid) || empty($nic)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing repid or nic']);
            exit;
        }

        $query = "SELECT * FROM salesrep WHERE RepID = ? AND nic = ? AND Actives = 'YES'";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $repid, $nic);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Authentication error: ' . $e->getMessage()]);
    }
}

function handleLogActivity($conn) {
    try {
        $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
        $activityType = isset($_POST['activityType']) ? $conn->real_escape_string($_POST['activityType']) : '';
        $duration = isset($_POST['duration']) ? floatval($_POST['duration']) : 0;

        if (!$userId || empty($activityType)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }

        $query = "INSERT INTO user_activity (salesrepTb, activity_type, duration, rDateTime) VALUES (?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isd", $userId, $activityType, $duration);
        
        if ($stmt->execute()) {
            $insertId = $stmt->insert_id;
            echo json_encode(['success' => true, 'id' => $insertId]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Log activity error: ' . $e->getMessage()]);
    }
}

function handleSaveRecordingMetadata($conn) {
    try {
        $brId = isset($_POST['brId']) ? intval($_POST['brId']) : 0;
        $imgID = isset($_POST['imgID']) ? $conn->real_escape_string($_POST['imgID']) : date('U');
        $imgName = isset($_POST['imgName']) ? $conn->real_escape_string($_POST['imgName']) : '';
        $itmName = isset($_POST['itmName']) ? $conn->real_escape_string($_POST['itmName']) : 'Work Session Recording Segment';
        $type = isset($_POST['type']) ? $conn->real_escape_string($_POST['type']) : 'recording';
        $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
        $status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : 'uploaded';
        $date = isset($_POST['date']) ? $conn->real_escape_string($_POST['date']) : date('Y-m-d');
        $time = isset($_POST['time']) ? $conn->real_escape_string($_POST['time']) : date('H:i:s');

        if (!$userId || !$imgName) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }

        $query = "INSERT INTO web_images (br_id, imgID, imgName, itmName, type, user_id, date, time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("iisssisss", $brId, $imgID, $imgName, $itmName, $type, $userId, $date, $time, $status);
        
        if ($stmt->execute()) {
            $insertId = $stmt->insert_id;
            echo json_encode(['success' => true, 'id' => $insertId]);
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Save recording metadata error: ' . $e->getMessage()]);
    }
}

function handlePing() {
    echo json_encode(['success' => true, 'message' => 'Server is reachable']);
}

function handleFileUpload($conn) {
    try {
        // Log incoming request for debugging
        error_log("Upload request received with Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown'));

        // Determine if the request contains multipart form data or raw binary data
        $isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;

        if ($isMultipart) {
            // Handle multipart form data (Traditional file upload)
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
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'File upload error: ' . $e->getMessage()]);
    }
}

// Close database connection
if ($conn) {
    $conn->close();
}
?>