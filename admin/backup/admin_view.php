<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ./admin_login.php');
    exit;
}

// This file would handle viewing recordings
// Since the original application stores recordings in uploads/ directory
// we'll create a simple viewer that serves the file with appropriate content type

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = __DIR__ . '/../uploads/' . $filename;

    // Debug: Uncomment the next lines to see the file path being checked
    // echo "Checking file: " . $filepath . "<br>";
    // echo "File exists: " . (file_exists($filepath) ? 'Yes' : 'No') . "<br>";
    // echo "Uploads dir: " . __DIR__ . '/../uploads/' . "<br>";
    // echo "Realpath of uploads: " . realpath(__DIR__ . '/../uploads/') . "<br>";
    // exit;

    // First check if the exact file exists
    if (file_exists($filepath) && strpos(realpath($filepath), realpath(__DIR__ . '/../../uploads/')) === 0) {
        // File exists with exact name
        $actual_filepath = $filepath;
    } else {
        // File doesn't exist with exact name, search for files ending with the requested name
        $uploads_dir = __DIR__ . '/../uploads/';
        $found_file = false;

        if (is_dir($uploads_dir)) {
            $files = scandir($uploads_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    // Check if the file ends with the requested filename
                    if (preg_match('/' . preg_quote($filename, '/') . '$/', $file)) {
                        $actual_filepath = $uploads_dir . $file;
                        $found_file = true;
                        break;
                    }
                }
            }
        }

        if (!$found_file) {
            // File not found or path traversal attempt
            http_response_code(404);
            echo "File not found. Path: " . $filepath;
            exit;
        }
    }

    // Security check: ensure the file exists and is in the uploads directory
    if (strpos(realpath($actual_filepath), realpath(__DIR__ . '/../uploads/')) === 0) {
        // Determine the content type based on file extension
        $extension = strtolower(pathinfo($actual_filepath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'webm':
                $contentType = 'video/webm';
                break;
            case 'mp4':
                $contentType = 'video/mp4';
                break;
            case 'mov':
                $contentType = 'video/quicktime';
                break;
            case 'avi':
                $contentType = 'video/x-msvideo';
                break;
            case 'wmv':
                $contentType = 'video/x-ms-wmv';
                break;
            case 'flv':
                $contentType = 'video/x-flv';
                break;
            case 'mkv':
                $contentType = 'video/x-matroska';
                break;
            case 'mp3':
                $contentType = 'audio/mpeg';
                break;
            case 'wav':
                $contentType = 'audio/wav';
                break;
            default:
                $contentType = 'application/octet-stream';
                break;
        }

        // Set headers for file viewing
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($actual_filepath));
        header('Accept-Ranges: none');

        // Read and output the file
        readfile($actual_filepath);
        exit;
    } else {
        // Path traversal attempt
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
} else {
    // No file specified
    http_response_code(400);
    echo "No file specified.";
    exit;
}
?>