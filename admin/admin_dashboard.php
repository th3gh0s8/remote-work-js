<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ./admin_login.php');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'remote-xwork';
$username = 'root'; // Default MySQL user
$password = '';     // Default MySQL password (empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch active users (users who have logged in recently) - each user only once
$sort_column = $_GET['sort_col'] ?? 'last_activity';
$sort_direction = $_GET['sort_dir'] ?? 'DESC';
$user_status_filter = $_GET['user_status'] ?? '';
$branch_filter = $_GET['branch_id'] ?? '';
$page = isset($_GET['page']) && $_GET['page'] == 'active_users' ? (int)$_GET['active_users_page'] : 1;
$limit = 10; // Number of records per page
$offset = ($page - 1) * $limit;

// Validate sort column to prevent SQL injection
$allowed_columns = ['ID', 'RepID', 'Name', 'br_id', 'last_activity'];
$sort_column = in_array($sort_column, $allowed_columns) ? $sort_column : 'last_activity';

// Validate sort direction
$sort_direction = strtoupper($sort_direction) === 'ASC' ? 'ASC' : 'DESC';

// Build the WHERE clause dynamically
$where_conditions = ["ua.rDateTime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"];
$params = [];

if (!empty($user_status_filter)) {
    $where_conditions[] = "CASE
        WHEN EXISTS (
            SELECT 1 FROM user_activity ua2
            WHERE ua2.salesrepTb = s.ID
            AND ua2.activity_type = 'login'
            AND ua2.rDateTime > COALESCE((
                SELECT MAX(ua3.rDateTime)
                FROM user_activity ua3
                WHERE ua3.salesrepTb = s.ID
                AND ua3.activity_type IN ('logout', 'check-out')
            ), '1900-01-01')
        ) THEN 'online'
        ELSE 'offline'
    END = ?";
    $params[] = $user_status_filter;
}

if (!empty($branch_filter)) {
    $where_conditions[] = "s.br_id LIKE ?";
    $params[] = "%{$branch_filter}%";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.ID) as total
    FROM salesrep s
    INNER JOIN user_activity ua ON s.ID = ua.salesrepTb
    WHERE {$where_clause}
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("
    SELECT s.*, MAX(ua.rDateTime) as last_activity,
           CASE
               WHEN EXISTS (
                   SELECT 1 FROM user_activity ua2
                   WHERE ua2.salesrepTb = s.ID
                   AND ua2.activity_type = 'login'
                   AND ua2.rDateTime > COALESCE((
                       SELECT MAX(ua3.rDateTime)
                       FROM user_activity ua3
                       WHERE ua3.salesrepTb = s.ID
                       AND ua3.activity_type IN ('logout', 'check-out')
                   ), '1900-01-01')
               ) THEN 'online'
               ELSE 'offline'
           END as current_status
    FROM salesrep s
    INNER JOIN user_activity ua ON s.ID = ua.salesrepTb
    WHERE {$where_clause}
    GROUP BY s.ID
    ORDER BY {$sort_column} {$sort_direction}
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent recordings
$rec_sort_column = $_GET['rec_sort_col'] ?? 'w.date';
$rec_sort_direction = $_GET['rec_sort_dir'] ?? 'DESC';
$rec_page = isset($_GET['page']) && $_GET['page'] == 'recordings' ? (int)$_GET['recordings_page'] : 1;
$rec_limit = 10; // Number of records per page
$rec_offset = ($rec_page - 1) * $rec_limit;

// Validate sort column to prevent SQL injection
$allowed_rec_columns = ['w.ID', 's.Name', 's.RepID', 'w.imgName', 'w.date', 'w.time', 'w.status'];
$rec_sort_column = in_array($rec_sort_column, $allowed_rec_columns) ? $rec_sort_column : 'w.date';

// Validate sort direction
$rec_sort_direction = strtoupper($rec_sort_direction) === 'ASC' ? 'ASC' : 'DESC';

// Get total count for pagination
$rec_count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM web_images w
    LEFT JOIN salesrep s ON w.user_id = s.ID
    WHERE w.type = 'recording' AND w.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$rec_count_stmt->execute();
$rec_total_records = $rec_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$rec_total_pages = ceil($rec_total_records / $rec_limit);

$stmt = $pdo->prepare("
    SELECT w.*, s.Name as user_name, s.RepID
    FROM web_images w
    LEFT JOIN salesrep s ON w.user_id = s.ID
    WHERE w.type = 'recording' AND w.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY {$rec_sort_column} {$rec_sort_direction}, w.time DESC
    LIMIT {$rec_limit} OFFSET {$rec_offset}
");
$stmt->execute();
$recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get date range for filtering (moved before the user activities query)
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Fetch recent user activities
$act_sort_column = $_GET['act_sort_col'] ?? 'ua.rDateTime';
$act_sort_direction = $_GET['act_sort_dir'] ?? 'DESC';
$act_page = isset($_GET['page']) && $_GET['page'] == 'activities' ? (int)$_GET['activities_page'] : 1;
$act_limit = 10; // Number of records per page
$act_offset = ($act_page - 1) * $act_limit;

// Validate sort column to prevent SQL injection
$allowed_act_columns = ['ua.ID', 's.Name', 's.RepID', 'ua.activity_type', 'ua.rDateTime', 'ua.duration'];
$act_sort_column = in_array($act_sort_column, $allowed_act_columns) ? $act_sort_column : 'ua.rDateTime';

// Validate sort direction
$act_sort_direction = strtoupper($act_sort_direction) === 'ASC' ? 'ASC' : 'DESC';

// Build the WHERE clause dynamically to allow for date range filtering
$where_conditions = [];
$params = [];

// Add date range condition only if dates are provided
if (!empty($start_date) && !empty($end_date)) {
    $where_conditions[] = "ua.rDateTime BETWEEN ? AND ?";
    $params[] = $start_date . ' 00:00:00';
    $params[] = $end_date . ' 23:59:59';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$act_count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM user_activity ua
    LEFT JOIN salesrep s ON ua.salesrepTb = s.ID
    {$where_clause}
");
$act_count_stmt->execute($params);
$act_total_records = $act_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$act_total_pages = ceil($act_total_records / $act_limit);

$stmt = $pdo->prepare("
    SELECT ua.*, s.Name as user_name, s.RepID
    FROM user_activity ua
    LEFT JOIN salesrep s ON ua.salesrepTb = s.ID
    {$where_clause}
    ORDER BY {$act_sort_column} {$act_sort_direction}
    LIMIT {$act_limit} OFFSET {$act_offset}
");
$stmt->execute($params);
$user_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM salesrep WHERE Actives = 'YES'");
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Count active users today
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT salesrepTb) as active_today
    FROM user_activity
    WHERE DATE(rDateTime) = CURDATE()
");
$stmt->execute();
$active_today = $stmt->fetch(PDO::FETCH_ASSOC)['active_today'];

// Count total recordings
$stmt = $pdo->query("SELECT COUNT(*) as total FROM web_images WHERE type = 'recording'");
$total_recordings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Count recordings from today
$stmt = $pdo->prepare("
    SELECT COUNT(*) as today_count
    FROM web_images
    WHERE type = 'recording' AND date = CURDATE()
");
$stmt->execute();
$recordings_today = $stmt->fetch(PDO::FETCH_ASSOC)['today_count'];

// Fetch all users for the user management section with online status
$all_users_sort_column = $_GET['all_users_sort_col'] ?? 's.ID';
$all_users_sort_direction = $_GET['all_users_sort_dir'] ?? 'DESC';
$account_status_filter = $_GET['account_status'] ?? '';
$online_status_filter = $_GET['online_status'] ?? '';
$all_users_branch_filter = $_GET['all_users_branch'] ?? '';
$all_users_page = isset($_GET['page']) && $_GET['page'] == 'all_users' ? (int)$_GET['all_users_page'] : 1;
$all_users_limit = 10; // Number of records per page
$all_users_offset = ($all_users_page - 1) * $all_users_limit;

// Validate sort column to prevent SQL injection
$allowed_all_users_columns = ['s.ID', 's.RepID', 's.Name', 's.br_id', 's.emailAddress', 's.join_date', 's.Actives'];
$all_users_sort_column = in_array($all_users_sort_column, $allowed_all_users_columns) ? $all_users_sort_column : 's.ID';

// Validate sort direction
$all_users_sort_direction = strtoupper($all_users_sort_direction) === 'ASC' ? 'ASC' : 'DESC';

// Build the WHERE clause dynamically
$all_users_where_conditions = [];
$all_users_params = [];

if (!empty($account_status_filter)) {
    $all_users_where_conditions[] = "s.Actives = ?";
    $all_users_params[] = $account_status_filter;
}

if (!empty($online_status_filter)) {
    $all_users_where_conditions[] = "
        CASE
            WHEN EXISTS (
                SELECT 1 FROM user_activity ua
                WHERE ua.salesrepTb = s.ID
                AND ua.activity_type = 'login'
                AND ua.rDateTime > COALESCE((
                    SELECT MAX(ua2.rDateTime)
                    FROM user_activity ua2
                    WHERE ua2.salesrepTb = s.ID
                    AND ua2.activity_type IN ('logout', 'check-out')
                ), '1900-01-01')
                AND ua.rDateTime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ) THEN 'online'
            ELSE 'offline'
        END = ? ";
    $all_users_params[] = $online_status_filter;
}

if (!empty($all_users_branch_filter)) {
    $all_users_where_conditions[] = "s.br_id LIKE ?";
    $all_users_params[] = "%{$all_users_branch_filter}%";
}

$all_users_where_clause = !empty($all_users_where_conditions) ? "WHERE " . implode(" AND ", $all_users_where_conditions) : "";

// Get total count for pagination
$all_users_count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM salesrep s
    {$all_users_where_clause}
");
$all_users_count_stmt->execute($all_users_params);
$all_users_total_records = $all_users_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$all_users_total_pages = ceil($all_users_total_records / $all_users_limit);

$stmt = $pdo->prepare("
    SELECT s.*,
           CASE
               WHEN EXISTS (
                   SELECT 1 FROM user_activity ua
                   WHERE ua.salesrepTb = s.ID
                   AND ua.activity_type = 'login'
                   AND ua.rDateTime > COALESCE((
                       SELECT MAX(ua2.rDateTime)
                       FROM user_activity ua2
                       WHERE ua2.salesrepTb = s.ID
                       AND ua2.activity_type IN ('logout', 'check-out')
                   ), '1900-01-01')
                   AND ua.rDateTime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               ) THEN 'online'
               ELSE 'offline'
           END as current_status,
           (SELECT MAX(rDateTime) FROM user_activity WHERE salesrepTb = s.ID) as last_activity
    FROM salesrep s
    {$all_users_where_clause}
    ORDER BY {$all_users_sort_column} {$all_users_sort_direction}
    LIMIT {$all_users_limit} OFFSET {$all_users_offset}
");
$stmt->execute($all_users_params);
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Remote Work Monitoring</title>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-bg: #f8f9fa;
            --dark-bg: #212529;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 600;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            font-size: 1.5em;
        }

        .logout-btn {
            background: linear-gradient(to right, var(--danger-color), #e63946);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(247, 37, 133, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(247, 37, 133, 0.4);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            margin: 0 0 15px 0;
            color: #555;
            font-size: 1.1em;
            font-weight: 500;
        }

        .stat-card .number {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #777;
            font-size: 0.9em;
        }

        .section {
            background-color: white;
            margin-bottom: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .section:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 25px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.4em;
            font-weight: 500;
        }

        .section-header .icon {
            margin-right: 10px;
            font-size: 1.2em;
        }

        .filters {
            padding: 20px;
            background-color: #f8f9fc;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            border-bottom: 1px solid #eee;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }

        .filter-item label {
            font-size: 0.9em;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .filter-item input,
        .filter-item select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95em;
            transition: var(--transition);
        }

        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .apply-filters {
            padding: 10px 20px;
            background: linear-gradient(to right, var(--success-color), #4895ef);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            align-self: flex-end;
            margin-bottom: 5px;
            font-weight: 500;
            transition: var(--transition);
        }

        .apply-filters:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(76, 201, 240, 0.3);
        }

        .section-content {
            padding: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        th a {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        th a:hover {
            color: var(--primary-color);
        }

        tr:nth-child(even) {
            background-color: #fafafa;
        }

        tr:hover {
            background-color: #f0f5ff;
            transform: scale(1.01);
        }

        .recording-actions {
            display: flex;
            gap: 8px;
        }

        .view-btn, .download-btn, .edit-btn, .delete-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85em;
            color: white;
            transition: var(--transition);
            font-weight: 500;
        }

        .view-btn {
            background: linear-gradient(to right, #2a9d8f, #264653);
        }

        .download-btn {
            background: linear-gradient(to right, var(--info-color), #4361ee);
        }

        .edit-btn {
            background: linear-gradient(to right, #ffb703, #fb8500);
            color: #212529;
        }

        .delete-btn {
            background: linear-gradient(to right, var(--danger-color), #e63946);
        }

        .view-btn:hover, .download-btn:hover, .edit-btn:hover, .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.1em;
        }

        .activity-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .badge-login {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-check-in {
            background-color: #cce5ff;
            color: #004085;
        }

        .badge-break-start {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-break-end {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .badge-check-out {
            background-color: #f8d7da;
            color: #721c24;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            background: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            overflow: hidden;
        }

        .tab {
            padding: 15px 25px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 2px;
            font-weight: 500;
            transition: var(--transition);
        }

        .tab:hover {
            background-color: #e9ecef;
        }

        .tab.active {
            background: linear-gradient(to bottom, var(--primary-color), var(--secondary-color));
            color: white;
            border-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .user-status-active {
            color: #28a745;
            font-weight: 600;
            background-color: rgba(40, 167, 69, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .user-status-inactive {
            color: #dc3545;
            font-weight: 600;
            background-color: rgba(220, 53, 69, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .pagination a {
            padding: 10px 16px;
            margin: 0 4px;
            text-decoration: none;
            border: 1px solid #ddd;
            color: var(--primary-color);
            border-radius: 5px;
            transition: var(--transition);
        }

        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination a:hover:not(.active) {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }

        .pagination a:first-child,
        .pagination a:last-child {
            background: linear-gradient(to right, var(--info-color), var(--primary-color));
            color: white;
            border: none;
        }

        .pagination a:first-child:hover,
        .pagination a:last-child:hover {
            opacity: 0.9;
        }

        .search-box {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 250px;
            margin-bottom: 15px;
        }

        .table-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .action-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .refresh-btn {
            background: linear-gradient(to right, #6a11cb, #2575fc);
            color: white;
        }

        .export-btn {
            background: linear-gradient(to right, #11998e, #38ef7d);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <span class="logo-icon">📊</span>
            <h1>Admin Dashboard</h1>
        </div>
        <a href="./admin_logout.php" class="logout-btn">Logout</a>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?php echo $total_users; ?></div>
                <div class="label">Registered in system</div>
            </div>
            <div class="stat-card">
                <h3>Active Today</h3>
                <div class="number"><?php echo $active_today; ?></div>
                <div class="label">Currently using app</div>
            </div>
            <div class="stat-card">
                <h3>Total Recordings</h3>
                <div class="number"><?php echo $total_recordings; ?></div>
                <div class="label">Saved sessions</div>
            </div>
            <div class="stat-card">
                <h3>Recordings Today</h3>
                <div class="number"><?php echo $recordings_today; ?></div>
                <div class="label">New uploads</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('active-users')">Active Users</div>
            <div class="tab" onclick="switchTab('recordings')">Recordings</div>
            <div class="tab" onclick="switchTab('activities')">Activities</div>
            <div class="tab" onclick="switchTab('all-users')">All Users</div>
        </div>
        
        <div id="active-users" class="tab-content active">
            <div class="section">
                <div class="section-header">
                    <h2><span class="icon">👥</span> Active Users (Last 24 Hours)</h2>
                </div>
                <div class="filters">
                    <div class="filter-item">
                        <label for="user_status_filter">Status:</label>
                        <select id="user_status_filter" name="user_status_filter" onchange="filterActiveUsers()">
                            <option value="">All Statuses</option>
                            <option value="online" <?= (isset($_GET['user_status']) && $_GET['user_status'] == 'online') ? 'selected' : '' ?>>Online</option>
                            <option value="offline" <?= (isset($_GET['user_status']) && $_GET['user_status'] == 'offline') ? 'selected' : '' ?>>Offline</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="branch_filter">Branch ID:</label>
                        <input type="text" id="branch_filter" name="branch_filter" value="<?= $_GET['branch_id'] ?? '' ?>" placeholder="Filter by branch">
                    </div>
                    <button class="apply-filters" onclick="filterActiveUsers()">Apply Filters</button>
                </div>
                <div class="section-content">
                    <?php if (count($active_users) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="?page=active_users&active_users_page=<?= $page ?>&sort_col=ID&sort_dir=<?= $sort_column === 'ID' && $sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">ID <?= $sort_column === 'ID' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=active_users&active_users_page=<?= $page ?>&sort_col=RepID&sort_dir=<?= $sort_column === 'RepID' && $sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Rep ID <?= $sort_column === 'RepID' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=active_users&active_users_page=<?= $page ?>&sort_col=Name&sort_dir=<?= $sort_column === 'Name' && $sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Name <?= $sort_column === 'Name' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=active_users&active_users_page=<?= $page ?>&sort_col=br_id&sort_dir=<?= $sort_column === 'br_id' && $sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Branch ID <?= $sort_column === 'br_id' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=active_users&active_users_page=<?= $page ?>&sort_col=last_activity&sort_dir=<?= $sort_column === 'last_activity' && $sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Last Activity <?= $sort_column === 'last_activity' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['ID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['RepID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['br_id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['last_activity']); ?></td>
                                        <td>
                                            <?php if ($user['current_status'] === 'online'): ?>
                                                <span class="user-status-active">Online</span>
                                            <?php else: ?>
                                                <span class="user-status-inactive">Offline</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=active_users&active_users_page=<?= $page - 1 ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=active_users&active_users_page=<?= $i ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>"
                                   class="<?= $i == $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=active_users&active_users_page=<?= $page + 1 ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No active users in the last 24 hours</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="recordings" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2><span class="icon">📹</span> Recent Recordings (Last 7 Days)</h2>
                </div>
                <div class="section-content">
                    <?php if (count($recordings) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=w.ID&rec_sort_dir=<?= $rec_sort_column === 'w.ID' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">ID <?= $rec_sort_column === 'w.ID' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=s.Name&rec_sort_dir=<?= $rec_sort_column === 's.Name' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">User <?= $rec_sort_column === 's.Name' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=s.RepID&rec_sort_dir=<?= $rec_sort_column === 's.RepID' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">Rep ID <?= $rec_sort_column === 's.RepID' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=w.imgName&rec_sort_dir=<?= $rec_sort_column === 'w.imgName' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">Recording Name <?= $rec_sort_column === 'w.imgName' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=w.date&rec_sort_dir=<?= $rec_sort_column === 'w.date' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">Date <?= $rec_sort_column === 'w.date' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=w.time&rec_sort_dir=<?= $rec_sort_column === 'w.time' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">Time <?= $rec_sort_column === 'w.time' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=w.status&rec_sort_dir=<?= $rec_sort_column === 'w.status' && $rec_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?>">Status <?= $rec_sort_column === 'w.status' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recordings as $recording): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recording['ID']); ?></td>
                                        <td><?php echo htmlspecialchars($recording['user_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($recording['RepID'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($recording['imgName']); ?></td>
                                        <td><?php echo htmlspecialchars($recording['date']); ?></td>
                                        <td><?php echo htmlspecialchars($recording['time']); ?></td>
                                        <td>
                                            <?php if ($recording['status'] === 'uploaded'): ?>
                                                <span style="color: green;">Uploaded</span>
                                            <?php elseif ($recording['status'] === 'local-fallback'): ?>
                                                <span style="color: orange;">Local Fallback</span>
                                            <?php else: ?>
                                                <span style="color: #666;"><?php echo htmlspecialchars($recording['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="recording-actions">
                                            <?php if ($recording['status'] === 'uploaded'): ?>
                                                <!-- View button would link to the actual recording if stored on server -->
                                                <button class="view-btn" disabled>View</button>
                                                <a href="./admin_download.php?file=<?php echo urlencode($recording['imgName']); ?>" class="download-btn">Download</a>
                                            <?php else: ?>
                                                <!-- For local fallback recordings, we could provide a download link if path is known -->
                                                <button class="view-btn" disabled>View</button>
                                                <button class="download-btn" disabled>Download</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($rec_page > 1): ?>
                                <a href="?page=recordings&recordings_page=<?= $rec_page - 1 ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $rec_page - 2); $i <= min($rec_total_pages, $rec_page + 2); $i++): ?>
                                <a href="?page=recordings&recordings_page=<?= $i ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?>"
                                   class="<?= $i == $rec_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($rec_page < $rec_total_pages): ?>
                                <a href="?page=recordings&recordings_page=<?= $rec_page + 1 ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No recordings found in the last 7 days</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="activities" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2><span class="icon">📋</span> Recent User Activities</h2>
                </div>
                <div class="filters">
                    <div class="filter-item">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="filter-item">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button class="apply-filters" onclick="applyFilters()">Apply Filters</button>
                    <small style="align-self: center; color: #666;">Leave empty to show all records</small>
                </div>
                <div class="section-content">
                    <?php if (count($user_activities) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="?page=activities&activities_page=<?= $act_page ?>&act_sort_col=ua.ID&act_sort_dir=<?= $act_sort_column === 'ua.ID' && $act_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">ID <?= $act_sort_column === 'ua.ID' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=activities&activities_page=<?= $act_page ?>&act_sort_col=s.Name&act_sort_dir=<?= $act_sort_column === 's.Name' && $act_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">User <?= $act_sort_column === 's.Name' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=activities&activities_page=<?= $act_page ?>&act_sort_col=s.RepID&act_sort_dir=<?= $act_sort_column === 's.RepID' && $act_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">Rep ID <?= $act_sort_column === 's.RepID' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=activities&activities_page=<?= $act_page ?>&act_sort_col=ua.activity_type&act_sort_dir=<?= $act_sort_column === 'ua.activity_type' && $act_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">Activity Type <?= $act_sort_column === 'ua.activity_type' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=activities&activities_page=<?= $act_page ?>&act_sort_col=ua.rDateTime&act_sort_dir=<?= $act_sort_column === 'ua.rDateTime' && $act_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">Date/Time <?= $act_sort_column === 'ua.rDateTime' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=activities&activities_page=<?= $act_page ?>&act_sort_col=ua.duration&act_sort_dir=<?= $act_sort_column === 'ua.duration' && $act_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">Duration <?= $act_sort_column === 'ua.duration' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_activities as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['ID']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['user_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($activity['RepID'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="activity-badge badge-<?php echo str_replace('-', '', $activity['activity_type']); ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $activity['activity_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['rDateTime']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['duration']); ?> sec</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($act_page > 1): ?>
                                <a href="?page=activities&activities_page=<?= $act_page - 1 ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $act_page - 2); $i <= min($act_total_pages, $act_page + 2); $i++): ?>
                                <a href="?page=activities&activities_page=<?= $i ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>"
                                   class="<?= $i == $act_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($act_page < $act_total_pages): ?>
                                <a href="?page=activities&activities_page=<?= $act_page + 1 ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No user activities found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="all-users" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2><span class="icon">👤</span> All Users</h2>
                </div>
                <div class="filters">
                    <div class="filter-item">
                        <label for="all_users_status_filter">Account Status:</label>
                        <select id="all_users_status_filter" name="all_users_status_filter" onchange="filterAllUsers()">
                            <option value="">All Accounts</option>
                            <option value="YES" <?= (isset($_GET['account_status']) && $_GET['account_status'] == 'YES') ? 'selected' : '' ?>>Active</option>
                            <option value="NO" <?= (isset($_GET['account_status']) && $_GET['account_status'] == 'NO') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="all_users_online_status_filter">Online Status:</label>
                        <select id="all_users_online_status_filter" name="all_users_online_status_filter" onchange="filterAllUsers()">
                            <option value="">All Statuses</option>
                            <option value="online" <?= (isset($_GET['online_status']) && $_GET['online_status'] == 'online') ? 'selected' : '' ?>>Online</option>
                            <option value="offline" <?= (isset($_GET['online_status']) && $_GET['online_status'] == 'offline') ? 'selected' : '' ?>>Offline</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="all_users_branch_filter">Branch ID:</label>
                        <input type="text" id="all_users_branch_filter" name="all_users_branch_filter" value="<?= $_GET['all_users_branch'] ?? '' ?>" placeholder="Filter by branch">
                    </div>
                    <button class="apply-filters" onclick="filterAllUsers()">Apply Filters</button>
                </div>
                <div class="section-content">
                    <?php if (count($all_users) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="?page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=s.ID&all_users_sort_dir=<?= $all_users_sort_column === 's.ID' && $all_users_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">ID <?= $all_users_sort_column === 's.ID' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=s.RepID&all_users_sort_dir=<?= $all_users_sort_column === 's.RepID' && $all_users_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Rep ID <?= $all_users_sort_column === 's.RepID' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=s.Name&all_users_sort_dir=<?= $all_users_sort_column === 's.Name' && $all_users_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Name <?= $all_users_sort_column === 's.Name' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=s.br_id&all_users_sort_dir=<?= $all_users_sort_column === 's.br_id' && $all_users_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Branch ID <?= $all_users_sort_column === 's.br_id' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=s.emailAddress&all_users_sort_dir=<?= $all_users_sort_column === 's.emailAddress' && $all_users_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Email <?= $all_users_sort_column === 's.emailAddress' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th><a href="?page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=s.join_date&all_users_sort_dir=<?= $all_users_sort_column === 's.join_date' && $all_users_sort_direction === 'ASC' ? 'DESC' : 'ASC' ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Join Date <?= $all_users_sort_column === 's.join_date' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['ID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['RepID']); ?></td>
                                        <td><?php echo htmlspecialchars($user['Name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['br_id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['emailAddress']); ?></td>
                                        <td><?php echo htmlspecialchars($user['join_date']); ?></td>
                                        <td>
                                            <?php if ($user['Actives'] === 'YES'): ?>
                                                <?php if ($user['current_status'] === 'online'): ?>
                                                    <span class="user-status-active">Online</span>
                                                <?php else: ?>
                                                    <span class="user-status-inactive">Offline</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="user-status-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="recording-actions">
                                            <button class="edit-btn" disabled>Edit</button>
                                            <button class="delete-btn" disabled>Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($all_users_page > 1): ?>
                                <a href="?page=all_users&all_users_page=<?= $all_users_page - 1 ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">&laquo; Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $all_users_page - 2); $i <= min($all_users_total_pages, $all_users_page + 2); $i++): ?>
                                <a href="?page=all_users&all_users_page=<?= $i ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>"
                                   class="<?= $i == $all_users_page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($all_users_page < $all_users_total_pages): ?>
                                <a href="?page=all_users&all_users_page=<?= $all_users_page + 1 ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">No users found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');

            // Update URL to reflect current tab
            const tabMap = {
                'active-users': 'active_users',
                'recordings': 'recordings',
                'activities': 'activities',
                'all-users': 'all_users'
            };

            const pageParam = tabMap[tabName] || 'active_users';
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('page', pageParam);
            window.history.replaceState({}, '', currentUrl);
        }

        // Function to activate the correct tab based on URL parameters
        function activateTabBasedOnURL() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page');

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            let activeTabId = 'active-users'; // Default tab

            // Determine which tab should be active based on the page parameter
            if (currentPage === 'recordings') {
                activeTabId = 'recordings';
            } else if (currentPage === 'activities') {
                activeTabId = 'activities';
            } else if (currentPage === 'all_users') {
                activeTabId = 'all-users';
            } else {
                activeTabId = 'active-users'; // Default
            }

            // Activate the correct tab
            document.getElementById(activeTabId).classList.add('active');

            // Find the corresponding tab element and activate it
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                const tabText = tab.textContent.trim();
                if (
                    (activeTabId === 'active-users' && tabText.includes('Active Users')) ||
                    (activeTabId === 'recordings' && tabText.includes('Recordings')) ||
                    (activeTabId === 'activities' && tabText.includes('Activities')) ||
                    (activeTabId === 'all-users' && tabText.includes('All Users'))
                ) {
                    tab.classList.add('active');
                }
            });
        }

        // Call the function when the page loads
        window.onload = function() {
            activateTabBasedOnURL();
        };
        
        function applyFilters() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            let url = '?page=activities&';
            if (startDate) {
                url += `start_date=${startDate}&`;
            }
            if (endDate) {
                url += `end_date=${endDate}&`;
            }

            // Remove trailing '&' if present
            if (url.endsWith('&')) {
                url = url.slice(0, -1);
            }

            // Reload the page with the filter parameters
            window.location.href = url;
        }

        function filterActiveUsers() {
            const statusFilter = document.getElementById('user_status_filter').value;
            const branchFilter = document.getElementById('branch_filter').value;
            const sortCol = '<?= $sort_column ?>';
            const sortDir = '<?= $sort_direction ?>';

            let url = '?page=active_users&';
            if (statusFilter) {
                url += `user_status=${statusFilter}&`;
            }
            if (branchFilter) {
                url += `branch_id=${branchFilter}&`;
            }
            if (sortCol) {
                url += `sort_col=${sortCol}&`;
            }
            if (sortDir) {
                url += `sort_dir=${sortDir}&`;
            }

            // Remove trailing '&'
            if (url.endsWith('&')) {
                url = url.slice(0, -1);
            }

            window.location.href = url;
        }

        function filterAllUsers() {
            const accountStatusFilter = document.getElementById('all_users_status_filter').value;
            const onlineStatusFilter = document.getElementById('all_users_online_status_filter').value;
            const branchFilter = document.getElementById('all_users_branch_filter').value;
            const sortCol = '<?= $all_users_sort_column ?>';
            const sortDir = '<?= $all_users_sort_direction ?>';

            let url = '?page=all_users&';
            if (accountStatusFilter) {
                url += `account_status=${accountStatusFilter}&`;
            }
            if (onlineStatusFilter) {
                url += `online_status=${onlineStatusFilter}&`;
            }
            if (branchFilter) {
                url += `all_users_branch=${branchFilter}&`;
            }
            if (sortCol) {
                url += `all_users_sort_col=${sortCol}&`;
            }
            if (sortDir) {
                url += `all_users_sort_dir=${sortDir}&`;
            }

            // Remove trailing '&'
            if (url.endsWith('&')) {
                url = url.slice(0, -1);
            }

            window.location.href = url;
        }
    </script>
</body>
</html>