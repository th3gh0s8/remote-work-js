<?php
// Set content type to JSON
header('Content-Type: application/json');

// Set timezone to Sri Lanka time to ensure consistent datetime values
date_default_timezone_set('Asia/Colombo'); // Sri Lanka timezone

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection from db.php
require_once __DIR__ . '/db.php';

// Use the connection from db.php
if ($conn && $conn instanceof mysqli) {
    $dbConnected = true;
} else {
    // Log the connection error for debugging
    error_log("Database connection failed: Connection object not available");

    // For operations that require database access, we'll handle this gracefully
    // For file uploads, we can still save the file even if DB connection fails
    $dbConnected = false;
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
    // Log current PHP upload limits for debugging
    error_log("Current PHP upload limits - upload_max_filesize: " . ini_get('upload_max_filesize') . ", post_max_size: " . ini_get('post_max_size'));

    // Define maximum file size (e.g., 500MB to accommodate larger recordings)
    $appMaxSize = 500 * 1024 * 1024; // 500 MB app limit
    $phpUploadMax = return_bytes(ini_get('upload_max_filesize'));
    $phpPostMax = return_bytes(ini_get('post_max_size'));

    // Use the smallest of our limit or PHP's limits to prevent issues
    $maxFileSize = min($appMaxSize, $phpUploadMax ?: $appMaxSize, $phpPostMax ?: $appMaxSize); // Apply minimum of our limit or PHP's limits

    // Log the effective max file size
    error_log("Effective max file size: " . round($maxFileSize / (1024 * 1024), 2) . " MB");

    // Check if this is a chunked upload request
    $chunk = isset($_POST['chunk']) ? intval($_POST['chunk']) : 0;
    $chunks = isset($_POST['chunks']) ? intval($_POST['chunks']) : 1;
    $chunk_filename = isset($_POST['chunk_filename']) ? $_POST['chunk_filename'] : '';

    // Log incoming request for debugging
    error_log("Upload request received with Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown'));
    error_log("Chunk info - Chunk: $chunk, Chunks: $chunks, Filename: $chunk_filename");

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
            // Map upload error codes to human-readable messages
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $errorMessage = $uploadErrors[$uploadedFile['error']] ?? 'Unknown upload error: ' . $uploadedFile['error'];
            echo json_encode(['error' => 'File upload error: ' . $errorMessage]);
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

        // Handle chunked upload if this is a chunk
        if ($chunks > 1) {
            // Validate chunk filename
            $chunk_filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $chunk_filename);
            $chunk_filename = basename($chunk_filename);

            if (empty($chunk_filename)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid chunk filename']);
                exit;
            }

            // Define chunk directory
            $chunkDir = __DIR__ . '/chunks/';
            if (!file_exists($chunkDir)) {
                if (!mkdir($chunkDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Could not create chunks directory']);
                    exit;
                }
            }

            // Move uploaded chunk to chunk directory
            $chunkPath = $chunkDir . $chunk_filename . '_chunk_' . $chunk;
            if (!move_uploaded_file($tempFilePath, $chunkPath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save chunk']);
                exit;
            }

            // If this is the last chunk, combine all chunks
            if ($chunk == $chunks - 1) {
                $finalFilePath = __DIR__ . '/uploads/' . $chunk_filename;

                // Create uploads directory if it doesn't exist
                $uploadDir = __DIR__ . '/uploads/';
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Could not create uploads directory']);
                        exit;
                    }
                }

                // Check if upload directory is writable
                if (!is_writable($uploadDir)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Uploads directory is not writable']);
                    exit;
                }

                // Combine all chunks
                $finalFile = fopen($finalFilePath, 'wb');
                if (!$finalFile) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Could not create final file']);
                    exit;
                }

                for ($i = 0; $i < $chunks; $i++) {
                    $chunkPath = $chunkDir . $chunk_filename . '_chunk_' . $i;
                    if (!file_exists($chunkPath)) {
                        fclose($finalFile);
                        http_response_code(500);
                        echo json_encode(['error' => "Missing chunk $i"]);
                        exit;
                    }

                    $chunkData = file_get_contents($chunkPath);
                    fwrite($finalFile, $chunkData);

                    // Clean up chunk file
                    unlink($chunkPath);
                }

                fclose($finalFile);

                // Read the combined file data
                $fileBinary = file_get_contents($finalFilePath);

                // Clean up the temporary file
                unlink($finalFilePath);

                if ($fileBinary === false) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Could not read combined file']);
                    exit;
                }
            } else {
                // Not the last chunk, return success to continue upload
                echo json_encode(['success' => true, 'message' => "Chunk $chunk saved"]);
                exit;
            }
        } else {
            // Regular (non-chunked) upload
            // Check file size
            if ($uploadedFile['size'] > $maxFileSize) {
                http_response_code(400);
                echo json_encode(['error' => 'File size (' . round($uploadedFile['size'] / (1024 * 1024), 2) . ' MB) exceeds maximum allowed size of ' . ($maxFileSize / (1024 * 1024)) . ' MB']);
                exit;
            }

            // Additional debugging: log file info
            error_log("Uploaded file info - Name: " . $uploadedFile['name'] . ", Size: " . $uploadedFile['size'] . ", Type: " . $uploadedFile['type'] . ", Temp: " . $uploadedFile['tmp_name']);

            // Read the binary data from the uploaded file
            $fileBinary = file_get_contents($tempFilePath);

            if ($fileBinary === false) {
                http_response_code(500);
                echo json_encode(['error' => 'Could not read temporary file']);
                exit;
            }

            // Verify that the file was properly uploaded and has content
            if (strlen($fileBinary) === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Uploaded file is empty']);
                exit;
            }

            // Additional check: compare expected size with actual size
            if (isset($uploadedFile['size']) && strlen($fileBinary) !== $uploadedFile['size']) {
                error_log("Warning: Expected file size {$uploadedFile['size']} but got " . strlen($fileBinary));
            }
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

        // Verify that the file has content
        if (strlen($fileBinary) === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Request body is empty']);
            exit;
        }

        // Check file size for raw data
        $rawDataSize = strlen($fileBinary);
        if ($rawDataSize > $maxFileSize) {
            http_response_code(400);
            echo json_encode(['error' => 'File size (' . round($rawDataSize / (1024 * 1024), 2) . ' MB) exceeds maximum allowed size of ' . ($maxFileSize / (1024 * 1024)) . ' MB']);
            exit;
        }

        // Additional debugging: log raw data info
        error_log("Raw data upload info - Size: " . $rawDataSize . " bytes");

        // Get metadata from headers
        $userId = isset($_SERVER['HTTP_X_USER_ID']) ? intval($_SERVER['HTTP_X_USER_ID']) : 0;
        $brId = isset($_SERVER['HTTP_X_BR_ID']) ? intval($_SERVER['HTTP_X_BR_ID']) : 0;
        $filename = isset($_SERVER['HTTP_X_FILENAME']) ? basename($_SERVER['HTTP_X_FILENAME']) : 'recording_' . time();
        $type = isset($_SERVER['HTTP_X_TYPE']) ? $_SERVER['HTTP_X_TYPE'] : 'recording';
        $description = isset($_SERVER['HTTP_X_DESCRIPTION']) ? $_SERVER['HTTP_X_DESCRIPTION'] : 'Work Session Recording';

        error_log("Raw data upload params - UserId: $userId, BrId: $brId, Filename: $filename");
    }

    // Additional validation: Check if the data looks like a valid video file
    // Support multiple video formats by checking file signatures (magic numbers)
    $validVideoHeader = false;
    $fileMimeType = '';

    if (strlen($fileBinary) >= 12) {  // Need at least 12 bytes to check most video signatures
        $header = substr($fileBinary, 0, 12);

        // Check for WebM/EBML header (starts with 0x1A45DF)
        if (substr($fileBinary, 0, 3) === "\x1A\x45\xDF") {
            $validVideoHeader = true;
            $fileMimeType = 'video/webm';
        }
        // Check for MP4 header
        else if (substr($header, 4, 3) === "ftyp") {
            $validVideoHeader = true;
            $fileMimeType = 'video/mp4';
        }
        // Check for AVI header
        else if (substr($header, 0, 4) === "RIFF" && substr($header, 8, 4) === "AVI ") {
            $validVideoHeader = true;
            $fileMimeType = 'video/avi';
        }
        // Check for MOV/QuickTime header
        else if (substr($header, 4, 4) === "moov" || substr($header, 4, 4) === "mdat") {
            $validVideoHeader = true;
            $fileMimeType = 'video/quicktime';
        }
        // Check for FLV header
        else if (substr($header, 0, 3) === "FLV") {
            $validVideoHeader = true;
            $fileMimeType = 'video/x-flv';
        }
        // Check for MKV header
        else if (substr($header, 0, 4) === "\x1A\x45\xDF\xA3") {
            $validVideoHeader = true;
            $fileMimeType = 'video/x-matroska';
        }
    }

    // If we couldn't determine the type from binary signature, try to get it from the file extension
    if (!$validVideoHeader) {
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['mp4', 'webm', 'avi', 'mov', 'mkv', 'flv', 'wmv', 'm4v', '3gp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $validVideoHeader = true;
            // Set mime type based on extension
            switch ($fileExtension) {
                case 'mp4': $fileMimeType = 'video/mp4'; break;
                case 'webm': $fileMimeType = 'video/webm'; break;
                case 'avi': $fileMimeType = 'video/avi'; break;
                case 'mov': $fileMimeType = 'video/quicktime'; break;
                case 'mkv': $fileMimeType = 'video/x-matroska'; break;
                case 'flv': $fileMimeType = 'video/x-flv'; break;
                case 'wmv': $fileMimeType = 'video/x-ms-wmv'; break;
                case 'm4v': $fileMimeType = 'video/x-m4v'; break;
                case '3gp': $fileMimeType = 'video/3gpp'; break;
                default: $fileMimeType = 'video/mp4'; // default fallback
            }
        }
    }

    if (!$validVideoHeader) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid video file format. Only MP4, WebM, AVI, MOV, MKV, FLV, WMV, M4V, and 3GP files are allowed.']);
        exit;
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

    // Check if upload directory is writable
    if (!is_writable($uploadDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Uploads directory is not writable']);
        exit;
    }

    // Sanitize filename to prevent directory traversal attacks
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    $fileExtension = preg_replace('/[^a-zA-Z0-9]/', '', $fileExtension); // Only alphanumeric characters in extension
    $baseFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($filename, PATHINFO_FILENAME));

    // Generate unique filename to prevent conflicts
    $uniqueFilename = uniqid('vid_', true) . '_' . $baseFilename;
    if ($fileExtension) {
        $uniqueFilename .= '.' . $fileExtension;
    }
    // Ensure the final filename is safe
    $uniqueFilename = basename($uniqueFilename);

    $uploadPath = $uploadDir . $uniqueFilename;

    error_log("Saving file to: $uploadPath");

    // Validate the upload path to prevent directory traversal
    $realUploadDir = realpath($uploadDir);
    $realUploadPath = realpath(dirname($uploadPath));

    if ($realUploadPath !== $realUploadDir) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file path']);
        exit;
    }

    // Attempt to save the file
    if (file_put_contents($uploadPath, $fileBinary) !== false) {
        // Verify that the file was actually saved and get its size
        $savedFileSize = filesize($uploadPath);
        if ($savedFileSize === false || $savedFileSize === 0) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save file properly']);
            unlink($uploadPath); // Clean up the failed file
            exit;
        }

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
                $insertStmt->bind_param("iisssisss", $brId, $imgID, $uniqueFilename, $itmName, $type, $userId, $date, $time, $status);

                if (!$insertStmt->execute()) {
                    error_log("Failed to insert recording metadata: " . $insertStmt->error);
                    // Even if DB insert fails, we still return success since file was saved
                } else {
                    error_log("Successfully inserted recording metadata for user: $userId, filename: $uniqueFilename, insert_id: " . $insertStmt->insert_id);
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

        // Create a relative path for the response to avoid exposing system paths
        $relativePath = 'uploads/' . $uniqueFilename;

        $response = [
            'success' => true,
            'fileId' => $imgID, // Use database ID
            'filename' => $uniqueFilename,
            'relativePath' => $relativePath, // Provide relative path instead of full system path
            'size' => $savedFileSize, // Use actual saved file size instead of original binary length
            'userId' => $userId,
            'brId' => $brId,
            'type' => $type,
            'description' => $description,
            'timestamp' => date('Y-m-d H:i:s'),
            'mimeType' => $fileMimeType // Include detected MIME type
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

// Helper function to convert PHP size notation to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Close database connection
if ($conn) {
    $conn->close();
}
?>