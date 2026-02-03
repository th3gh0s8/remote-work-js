<?php
// Set content type to JSON
header('Content-Type: application/json');

// Set timezone to Sri Lanka time to ensure consistent datetime values
date_default_timezone_set('Asia/Colombo'); // Sri Lanka timezone

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration - using localhost since database is on the same server
$servername = "localhost"; // Database is on the same server as the PHP script
$username = "stcloudb_104u";
$password = "104-2019-08-10";
$dbname = "stcloudb_104";
$port = 3306;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    // Log the connection error for debugging
    error_log("Database connection failed: " . $conn->connect_error);

    // For operations that require database access, we'll handle this gracefully
    // For file uploads, we can still save the file even if DB connection fails
    $dbConnected = false;
} else {
    $dbConnected = true;

    // Set charset
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
    }
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the action from the request
$action = isset($_POST['action']) ? $_POST['action'] : 'upload';

switch ($action) {
    case 'authenticate':
        if ($dbConnected) {
            handleAuthentication($conn);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        break;
    case 'log_activity':
        if ($dbConnected) {
            handleLogActivity($conn);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        break;
    case 'save_recording_metadata':
        if ($dbConnected) {
            handleSaveRecordingMetadata($conn);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        break;
    case 'ping':
        handlePing();
        break;
    case 'upload':
    default:
        if ($dbConnected) {
            handleFileUpload($conn, true);
        } else {
            // Still allow file upload to proceed but skip DB operations
            handleFileUpload($conn, false);
        }
        break;
}

function handleAuthentication($conn, $dbConnected = true) {
    try {
        $repid = isset($_POST['repid']) ? trim($_POST['repid']) : '';
        $nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';

        // Log what we received
        error_log("Authentication attempt - RepID: '$repid', NIC: '$nic'");

        if (empty($repid) || empty($nic)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing repid or nic',
                'received_repId' => $repid,
                'received_nic' => $nic
            ]);
            exit;
        }

        if (!$dbConnected) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
            exit;
        }

        // First, let's see what's in the database for this RepID
        $debug_query = "SELECT RepID, nic, Actives FROM salesrep WHERE RepID = ?";
        $debug_stmt = $conn->prepare($debug_query);
        $debug_stmt->bind_param("s", $repid);
        $debug_stmt->execute();
        $debug_result = $debug_stmt->get_result();

        $found_records = [];
        while ($row = $debug_result->fetch_assoc()) {
            $found_records[] = $row;
        }
        $debug_stmt->close();

        // Now run the actual authentication query
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
            echo json_encode([
                'success' => true,
                'user' => $user,
                'debug_info' => [
                    'records_found_with_repId' => $found_records,
                    'authentication_successful' => true
                ]
            ]);
        } else {
            // Authentication failed - return debug info
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials',
                'debug_info' => [
                    'records_found_with_repId' => $found_records,
                    'authentication_successful' => false,
                    'attempted_repId' => $repid,
                    'attempted_nic' => $nic,
                    'expected_nic_from_db' => !empty($found_records) ? $found_records[0]['nic'] : 'No record found',
                    'expected_actives_from_db' => !empty($found_records) ? $found_records[0]['Actives'] : 'No record found'
                ]
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication error: ' . $e->getMessage()
        ]);
    }
}
function handlePing() {
    echo json_encode(['success' => true, 'message' => 'Server is reachable']);
}

function handleDebugInfo($conn, $dbConnected = true) {
    try {
        if (!$dbConnected) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
            exit;
        }

        // Return some sample records to help with debugging
        $query = "SELECT RepID, nic, Actives, Name FROM salesrep WHERE Actives = 'YES' LIMIT 5";
        $result = $conn->query($query);

        $sample_records = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $sample_records[] = $row;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'sample_records' => $sample_records,
            'table_exists' => true
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Debug error: ' . $e->getMessage()
        ]);
    }
}

function handleFileUpload($conn, $dbConnected = true) {
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

        // Insert recording metadata into the database
        $imgID = crc32(uniqid()); // Use CRC32 to convert uniqid() to integer
        $itmName = 'Work Session Recording Segment';
        $status = 'uploaded';
        $date = date('Y-m-d');
        $time = date('H:i:s');

        if ($dbConnected) {
            $insertQuery = "INSERT INTO web_images (br_id, imgID, imgName, itmName, type, user_id, date, time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            if (!$insertStmt) {
                error_log("Failed to prepare insert statement: " . $conn->error);
            } else {
                $insertStmt->bind_param("iisssisss", $brId, $imgID, $filename, $itmName, $type, $userId, $date, $time, $status);

                if (!$insertStmt->execute()) {
                    error_log("Failed to insert recording metadata: " . $insertStmt->error);
                } else {
                    error_log("Successfully inserted recording metadata for user: $userId, filename: $filename");
                }
                $insertStmt->close();
            }
        } else {
            error_log("Skipping database insert for web_images as database is not connected");
        }

        // Also log this as an activity in user_activity table
        error_log("Attempting to log activity - DB Connected: " . ($dbConnected ? 'yes' : 'no') . ", UserId: $userId, Type: $type");

        if ($dbConnected && $userId > 0) {  // Only proceed if DB is connected and userId is a positive integer
            $currentDateTime = date('Y-m-d H:i:s'); // Use PHP's date function with consistent format
            $activityQuery = "INSERT INTO user_activity (salesrepTb, activity_type, duration, rDateTime) VALUES (?, ?, 0, ?)";
            $activityStmt = $conn->prepare($activityQuery);
            if (!$activityStmt) {
                error_log("Failed to prepare activity statement: " . $conn->error);
            } else {
                error_log("About to bind params - UserId: $userId (type: " . gettype($userId) . "), Type: $type (type: " . gettype($type) . ")");
                $activityStmt->bind_param("iss", $userId, $type, $currentDateTime);

                if (!$activityStmt->execute()) {
                    error_log("Failed to log upload activity: " . $activityStmt->error . " - UserId: $userId, Type: $type");
                } else {
                    error_log("Successfully logged upload activity for user: $userId, type: $type, insert_id: " . $activityStmt->insert_id);
                }
                $activityStmt->close();
            }
        } elseif (!$dbConnected) {
            error_log("Skipping activity log due to database connection not being available");
        } else {
            error_log("Skipping activity log due to invalid user ID: $userId");
        }

        $response = [
            'success' => true,
            'fileId' => $imgID, // Use database ID
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
}

function handleLogActivity($conn, $dbConnected = true) {
    try {
        $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;
        $activityType = isset($_POST['activityType']) ? $conn->real_escape_string($_POST['activityType']) : '';
        $duration = isset($_POST['duration']) ? floatval($_POST['duration']) : 0;

        if (!$userId || empty($activityType)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }

        if (!$dbConnected) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }

        error_log("handleLogActivity - UserId: $userId, ActivityType: $activityType, Duration: $duration");

        $currentDateTime = date('Y-m-d H:i:s'); // Use PHP's date function with consistent format
        $query = "INSERT INTO user_activity (salesrepTb, activity_type, duration, rDateTime) VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Failed to prepare activity statement in handleLogActivity: " . $conn->error);
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("isds", $userId, $activityType, $duration, $currentDateTime);

        if ($stmt->execute()) {
            $insertId = $stmt->insert_id;
            error_log("Successfully logged activity via handleLogActivity - UserId: $userId, ActivityType: $activityType, InsertId: $insertId");
            echo json_encode(['success' => true, 'id' => $insertId]);
        } else {
            error_log("Failed to execute activity insert in handleLogActivity: " . $stmt->error);
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Log activity error: ' . $e->getMessage()]);
    }
}

function handleSaveRecordingMetadata($conn, $dbConnected = true) {
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

        if (!$dbConnected) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
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

// Close database connection
if ($conn) {
    $conn->close();
}
?>