<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ./admin_login.php');
    exit;
}

// This file would handle downloading recordings
// Since the original application stores recordings in uploads/ directory
// we'll create a simple download handler

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = __DIR__ . '/../uploads/' . $filename;

    // First check if the exact file exists
    if (file_exists($filepath) && strpos(realpath($filepath), realpath(__DIR__ . '/../uploads/')) === 0) {
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
            echo "File not found.";
            exit;
        }
    }

    // Security check: ensure the file exists and is in the uploads directory
    if (strpos(realpath($actual_filepath), realpath(__DIR__ . '/../uploads/')) === 0) {
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($actual_filepath) . '"');
        header('Content-Length: ' . filesize($actual_filepath));

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