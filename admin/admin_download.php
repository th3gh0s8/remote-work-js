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
    $filepath = __DIR__ . '/uploads/' . $filename;
    
    // Security check: ensure the file exists and is in the uploads directory
    if (file_exists($filepath) && strpos(realpath($filepath), realpath(__DIR__ . '/uploads/')) === 0) {
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        
        // Read and output the file
        readfile($filepath);
        exit;
    } else {
        // File not found or path traversal attempt
        http_response_code(404);
        echo "File not found.";
        exit;
    }
} else {
    // No file specified
    http_response_code(400);
    echo "No file specified.";
    exit;
}
?>