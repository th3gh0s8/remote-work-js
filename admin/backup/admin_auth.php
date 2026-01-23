<?php
session_start();

// Simple admin credentials - in a real application, these should be securely stored
$admin_username = 'admin';
$admin_password = 'admin123'; // Change this in production!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ./admin_dashboard.php');
        exit;
    } else {
        header('Location: ./admin_login.php?error=1');
        exit;
    }
} else {
    header('Location: ./admin_login.php');
    exit;
}
?>