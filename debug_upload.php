<?php
// Debug script to check upload configuration
header('Content-Type: application/json');

// Set timezone to ensure consistent datetime values
date_default_timezone_set('Asia/Colombo'); // Sri Lanka timezone

echo json_encode([
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'memory_limit' => ini_get('memory_limit'),
    'file_uploads' => ini_get('file_uploads'),
    'upload_tmp_dir' => ini_get('upload_tmp_dir'),
    'is_uploads_dir_writable' => is_writable(__DIR__ . '/uploads/'),
    'current_time' => date('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
]);
?>