# Admin Panel for Remote Work Monitoring System

## Overview
This admin panel allows administrators to monitor users and recordings in the remote work monitoring system.

## Features
- View active users (each user listed only once with most recent activity in the last 24 hours)
- Accurate online/offline status based on login/logout activity
- Sortable columns in all data tables (click on column headers)
- Pagination for all data tables (10 records per page by default)
- Filter active users by status (online/offline) and branch ID
- Filter all users by account status (active/inactive), online status, and branch ID
- View recent recordings (from the last 7 days) with sortable columns
- View user activity logs with date range filtering and sortable columns
- View all registered users with their current online status
- Download available recordings
- Dashboard statistics for quick overview

## Setup Instructions

### Prerequisites
- PHP 7.0 or higher
- MySQL/MariaDB server
- Web server (Apache/Nginx) with PHP support

### Database Configuration
The admin panel connects to the same database as the main application:
- Host: localhost
- Database: remote-xwork
- Username: root
- Password: (empty by default)

### Admin Credentials
Default admin credentials:
- Username: `admin`
- Password: `admin123`

> **Note:** Change these credentials in `admin_auth.php` for security purposes.

### File Locations
- `admin/admin_login.php` - Login page for the admin panel
- `admin/admin_auth.php` - Authentication handler
- `admin/admin_dashboard.php` - Main dashboard with user and recording information
- `admin/admin_logout.php` - Logout handler
- `admin/admin_download.php` - File download handler for recordings

### Accessing the Admin Panel
Navigate to `http://your-domain/admin/admin_login.php` in your browser.

## Security Notes
- Change the default admin credentials immediately after installation
- Ensure the admin panel is protected with HTTPS in production
- Restrict access to authorized personnel only
- Regularly update and patch the system

## Troubleshooting
- If you get database connection errors, verify your database credentials in `admin/admin_dashboard.php`
- If recordings don't appear, check that the `uploads/` directory exists and has proper permissions
- Ensure PHP sessions are enabled on your server
- If you see duplicate users in the active users section, the query has been optimized to show each user only once with their most recent activity
- If you get "file not found" errors, make sure you're accessing the admin panel from the correct URL: `/admin/admin_login.php`