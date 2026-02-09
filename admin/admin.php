<?php
// Main admin file that handles all admin functionality
// Determine the action based on the request
$action = $_GET['action'] ?? 'dashboard';

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle different admin actions
switch ($action) {
    case 'auth':
        handleAuth();
        break;
    case 'login':
        showLogin();
        break;
    case 'dashboard':
        showDashboard();
        break;
    case 'view':
        handleView();
        break;
    case 'download':
        handleDownload();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'edit_user':
        showEditUserForm();
        break;
    case 'update_user':
        updateUser();
        break;
    case 'delete_user':
        deleteUser();
        break;
    case 'bulk_delete':
        handleBulkDelete();
        break;
    case 'add_user':
        showAddUserForm();
        break;
    case 'create_user':
        createUser();
        break;
    case 'reports':
        showReports();
        break;
    case 'watch_live':
        showLiveWatching();
        break;
    case 'get_new_uploads':
        getNewUploads();
        break;
    case 'get_latest_video':
        getLatestVideo();
        break;
    case 'combine_recordings':
        showCombineRecordings();
        break;
    case 'generate_combined_video':
        generateCombinedVideo();
        break;
    case 'watch_combined':
        showWatchCombined();
        break;
    case 'serve_combined_video':
        serveCombinedVideo();
        break;
    default:
        // Default to dashboard if action is not recognized
        showDashboard();
        break;
}

// Authentication handler
function handleAuth() {
    session_start();

    // Simple admin credentials - in a real application, these should be securely stored
    $admin_username = 'admin';
    $admin_password = 'admin123'; // Change this in production!

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($csrf_token)) {
            header('Location: ?action=login&error=invalid_request');
            exit;
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($username === $admin_username && $password === $admin_password) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['login_time'] = time(); // Track login time
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR']; // Track IP address

            header('Location: ?action=dashboard');
            exit;
        } else {
            header('Location: ?action=login&error=1');
            exit;
        }
    } else {
        header('Location: ?action=login');
        exit;
    }
}

// Show login form
function showLogin() {
    session_start();

    // Check if admin is already logged in
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Location: ?action=dashboard');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Remote Work Monitoring</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }

            .login-container {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                width: 350px;
            }

            .login-container h2 {
                text-align: center;
                margin-bottom: 20px;
                color: #333;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-group label {
                display: block;
                margin-bottom: 5px;
                color: #555;
            }

            .form-group input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-sizing: border-box;
            }

            .btn {
                width: 100%;
                padding: 12px;
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }

            .btn:hover {
                background-color: #0056b3;
            }

            .error {
                color: red;
                text-align: center;
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Admin Login</h2>
            <form method="post" action="?action=auth">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === 'session_expired'): ?>
                    <div class="error">Your session has expired. Please log in again.</div>
                <?php elseif ($_GET['error'] === 'security_violation'): ?>
                    <div class="error">Security violation detected. Please contact the administrator.</div>
                <?php elseif ($_GET['error'] === 'invalid_request'): ?>
                    <div class="error">Invalid request. Please try again.</div>
                <?php else: ?>
                    <div class="error">Invalid credentials. Please try again.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Create a centralized database connection function
function getDatabaseConnection() {
    // Database connection parameters
    $host = 'localhost'; // Database server name
    $dbname = 'stcloudb_104'; // Database name
    $username = 'stcloudb_104u'; // Database username
    $password = '104-2019-08-10'; // Database password
    $port = 3306; // Database port

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Show admin dashboard
function showDashboard() {
    checkAdminSession();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Connection failed: Could not establish database connection.");
    }

    // Fetch active users (users who have logged in recently) - each user only once
    $sort_column = $_GET['sort_col'] ?? 'last_activity';
    $sort_direction = $_GET['sort_dir'] ?? 'DESC';
    $user_status_filter = $_GET['user_status'] ?? '';
    $branch_filter = $_GET['branch_id'] ?? '';
    $page = (isset($_GET['page']) && $_GET['page'] == 'active_users' && isset($_GET['active_users_page'])) ? max(1, (int)$_GET['active_users_page']) : 1;
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
    $rec_page = (isset($_GET['page']) && $_GET['page'] == 'recordings' && isset($_GET['recordings_page'])) ? max(1, (int)$_GET['recordings_page']) : 1;
    $rec_limit = 10; // Number of records per page
    $rec_offset = ($rec_page - 1) * $rec_limit;

    // Validate sort column to prevent SQL injection
    $allowed_rec_columns = ['w.ID', 's.Name', 's.RepID', 'w.imgName', 'w.date', 'w.time', 'w.status'];
    $rec_sort_column = in_array($rec_sort_column, $allowed_rec_columns) ? $rec_sort_column : 'w.date';

    // Validate sort direction
    $rec_sort_direction = strtoupper($rec_sort_direction) === 'ASC' ? 'ASC' : 'DESC';

    // Build the WHERE clause dynamically to allow for date range filtering
    $rec_where_conditions = ["w.type = 'recording'"];
    $rec_params = [];

    // Get date range for recordings specifically
    $rec_start_date = $_GET['rec_start_date'] ?? '';
    $rec_end_date = $_GET['rec_end_date'] ?? '';

    // Get user filter for recordings
    $rec_user_filter = $_GET['rec_user_filter'] ?? '';

    // Add date range condition only if dates are provided
    if (!empty($rec_start_date) && !empty($rec_end_date)) {
        $rec_where_conditions[] = "w.date BETWEEN ? AND ?";
        $rec_params[] = $rec_start_date;
        $rec_params[] = $rec_end_date;
    }

    // Add user filter condition if user is selected
    if (!empty($rec_user_filter)) {
        $rec_where_conditions[] = "w.user_id = ?";
        $rec_params[] = $rec_user_filter;
    }

    $rec_where_clause = !empty($rec_where_conditions) ? "WHERE " . implode(" AND ", $rec_where_conditions) : "";

    // Get total count for pagination
    $rec_count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM web_images w
        LEFT JOIN salesrep s ON w.user_id = s.ID
        {$rec_where_clause}
    ");
    $rec_count_stmt->execute($rec_params);
    $rec_total_records = $rec_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $rec_total_pages = ceil($rec_total_records / $rec_limit);

    $stmt = $pdo->prepare("
        SELECT w.*, s.Name as user_name, s.RepID
        FROM web_images w
        LEFT JOIN salesrep s ON w.user_id = s.ID
        {$rec_where_clause}
        ORDER BY {$rec_sort_column} {$rec_sort_direction}, w.time DESC
        LIMIT {$rec_limit} OFFSET {$rec_offset}
    ");
    $stmt->execute($rec_params);
    $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get date range for filtering (moved before the user activities query)
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    
    // Get search term for activities
    $act_search_term = $_GET['act_search'] ?? '';

    // Fetch recent user activities
    $act_sort_column = $_GET['act_sort_col'] ?? 'ua.rDateTime';
    $act_sort_direction = $_GET['act_sort_dir'] ?? 'DESC';
    $act_page = (isset($_GET['page']) && $_GET['page'] == 'activities' && isset($_GET['activities_page'])) ? max(1, (int)$_GET['activities_page']) : 1;
    $act_limit = 10; // Number of records per page
    $act_offset = ($act_page - 1) * $act_limit;

    // Validate sort column to prevent SQL injection
    $allowed_act_columns = ['ua.ID', 's.Name', 's.RepID', 'ua.activity_type', 'ua.rDateTime', 'ua.duration'];
    $act_sort_column = in_array($act_sort_column, $allowed_act_columns) ? $act_sort_column : 'ua.rDateTime';

    // Validate sort direction
    $act_sort_direction = strtoupper($act_sort_direction) === 'ASC' ? 'ASC' : 'DESC';

    // Build the WHERE clause dynamically to allow for date range filtering and search
    $where_conditions = [];
    $params = [];

    // Add date range condition only if dates are provided
    if (!empty($start_date) && !empty($end_date)) {
        $where_conditions[] = "ua.rDateTime BETWEEN ? AND ?";
        $params[] = $start_date . ' 00:00:00';
        $params[] = $end_date . ' 23:59:59';
    }

    // Add search condition for Rep ID or Name
    if (!empty($act_search_term)) {
        // Check if the search term is in "RepID - Name" format
        if (preg_match('/^(.*?)\s*-\s*(.*)$/', $act_search_term, $matches)) {
            // Extract RepID and Name parts
            $repIdPart = trim($matches[1]);
            $namePart = trim($matches[2]);
            
            // Search for records matching both RepID and Name parts
            $where_conditions[] = "(s.RepID LIKE ? AND s.Name LIKE ?)";
            $params[] = "%{$repIdPart}%";
            $params[] = "%{$namePart}%";
        } else {
            // Regular search for Rep ID or Name
            $where_conditions[] = "(s.RepID LIKE ? OR s.Name LIKE ?)";
            $params[] = "%{$act_search_term}%";
            $params[] = "%{$act_search_term}%";
        }
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
    $all_users_page = (isset($_GET['page']) && $_GET['page'] == 'all_users' && isset($_GET['all_users_page'])) ? max(1, (int)$_GET['all_users_page']) : 1;
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
                min-height: 400px; /* Added minimum height to accommodate dropdown */
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

            .filter-item {
                position: relative;
                display: inline-block;
            }

            .dropdown-content {
                display: none;
                position: absolute;
                background-color: white;
                min-width: 200px;
                box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
                z-index: 1000;
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 5px;
                top: 100%;
                left: 0;
                margin-top: 2px;
            }

            .dropdown-content .user-option {
                color: black;
                padding: 10px;
                text-decoration: none;
                display: block;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .dropdown-content .user-option:hover {
                background-color: #f1f1f1;
            }

            .dropdown-content .user-option.selected {
                background-color: #4361ee;
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
            <div style="display: flex; gap: 15px;">
                <a href="?action=logout" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="container">
            <?php if (isset($_GET['success'])): ?>
                <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

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
                <div class="tab" onclick="switchTab('reports')">Reports</div>
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
                                        <th><a href="?action=dashboard&page=active_users&active_users_page=<?= $page ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">ID <?= $sort_column === 'ID' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=active_users&active_users_page=<?= $page ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Rep ID <?= $sort_column === 'RepID' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=active_users&active_users_page=<?= $page ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Name <?= $sort_column === 'Name' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=active_users&active_users_page=<?= $page ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Branch ID <?= $sort_column === 'br_id' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=active_users&active_users_page=<?= $page ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Last Activity <?= $sort_column === 'last_activity' ? ($sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th>Status</th>
                                        <th>Actions</th>
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
                                            <td class="recording-actions">
                                                <a href="?action=watch_live&user_id=<?php echo $user['ID']; ?>" class="view-btn">Watch Live</a>
                                                <a href="?action=combine_recordings&user_id=<?php echo $user['ID']; ?>" class="download-btn">Combine Recordings</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?action=dashboard&page=active_users&active_users_page=<?= $page - 1 ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">&laquo; Previous</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?action=dashboard&page=active_users&active_users_page=<?= $i ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>"
                                       class="<?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?action=dashboard&page=active_users&active_users_page=<?= $page + 1 ?>&sort_col=<?= $sort_column ?>&sort_dir=<?= $sort_direction ?><?php if (!empty($user_status_filter)): ?>&user_status=<?= $user_status_filter ?><?php endif; ?><?php if (!empty($branch_filter)): ?>&branch_id=<?= $branch_filter ?><?php endif; ?>">Next &raquo;</a>
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
                        <h2><span class="icon">📹</span> All Recordings</h2>
                    </div>
                    <div class="filters">
                        <div class="filter-item" style="position: relative; display: inline-block;">
                            <label for="rec_user_filter">User (Rep ID):</label>
                            <input type="text" id="rec_user_filter_input" placeholder="Search by Rep ID or Name"
                                   value="<?php
                                       $selected_user = '';
                                       if (!empty($rec_user_filter)) {
                                           $user_stmt = $pdo->prepare("SELECT Name, RepID FROM salesrep WHERE ID = ?");
                                           $user_stmt->execute([$rec_user_filter]);
                                           $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                           if ($user_row) {
                                               $selected_user = $user_row['RepID'] . ' - ' . $user_row['Name'];
                                           }
                                       }
                                       echo htmlspecialchars($selected_user);
                                   ?>"
                                   onclick="toggleUserDropdown()"
                                   onkeyup="filterUserOptions()" />
                            <div id="user-dropdown" class="dropdown-content">
                                <div style="padding: 10px; background-color: #f1f1f1; font-weight: bold; border-bottom: 1px solid #ddd;" onclick="selectAllUsers()">Select All Users</div>
                                <div style="padding: 10px; background-color: #f1f1f1; font-weight: bold;" onclick="clearUserSelection()">Clear Selection</div>
                                <?php
                                // Fetch all users for the filter dropdown
                                $user_filter_stmt = $pdo->query("SELECT ID, Name, RepID FROM salesrep WHERE Actives = 'YES' ORDER BY RepID");
                                $filter_users = $user_filter_stmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($filter_users as $filter_user): ?>
                                    <div class="user-option"
                                         data-id="<?php echo $filter_user['ID']; ?>"
                                         data-repid="<?php echo htmlspecialchars($filter_user['RepID']); ?>"
                                         data-name="<?php echo htmlspecialchars($filter_user['Name']); ?>"
                                         onclick="selectUser(<?php echo $filter_user['ID']; ?>, '<?php echo addslashes($filter_user['RepID']); ?>', '<?php echo addslashes($filter_user['Name']); ?>')">
                                        <?php echo htmlspecialchars($filter_user['RepID']); ?> - <?php echo htmlspecialchars($filter_user['Name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="rec_user_filter" name="rec_user_filter" value="<?php echo $rec_user_filter; ?>" />
                        </div>
                        <div class="filter-item">
                            <label for="rec_start_date">Start Date:</label>
                            <input type="date" id="rec_start_date" name="rec_start_date" value="<?php echo $rec_start_date; ?>">
                        </div>
                        <div class="filter-item">
                            <label for="rec_end_date">End Date:</label>
                            <input type="date" id="rec_end_date" name="rec_end_date" value="<?php echo $rec_end_date; ?>">
                        </div>
                        <button class="apply-filters" onclick="filterRecordings()">Apply Filters</button>
                        <small style="align-self: center; color: #666;">Leave empty to show all records</small>
                    </div>
                    <div class="section-content">
                        <?php if (count($recordings) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all-recordings" onclick="toggleSelectAll(this, 'recording-checkbox')"></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">ID <?= $rec_sort_column === 'w.ID' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">User <?= $rec_sort_column === 's.Name' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">Rep ID <?= $rec_sort_column === 's.RepID' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">Recording Name <?= $rec_sort_column === 'w.imgName' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">Date <?= $rec_sort_column === 'w.date' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">Time <?= $rec_sort_column === 'w.time' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">Status <?= $rec_sort_column === 'w.status' ? ($rec_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recordings as $recording): ?>
                                        <tr>
                                            <td><input type="checkbox" class="recording-checkbox" name="selected_recordings[]" value="<?php echo $recording['ID']; ?>"></td>
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
                                                    <a href="?action=view&file=<?php echo urlencode($recording['imgName']); ?>" target="_blank" class="view-btn">View</a>
                                                    <a href="?action=download&file=<?php echo urlencode($recording['imgName']); ?>" class="download-btn">Download</a>
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
                            
                            <div class="bulk-actions" style="margin-top: 15px;">
                                <button class="delete-btn" onclick="confirmBulkDelete('recording-checkbox', 'recordings')">Delete Selected</button>
                            </div>

                            <!-- Pagination -->
                            <div class="pagination">
                                <?php if ($rec_page > 1): ?>
                                    <a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page - 1 ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">&laquo; Previous</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $rec_page - 2); $i <= min($rec_total_pages, $rec_page + 2); $i++): ?>
                                    <a href="?action=dashboard&page=recordings&recordings_page=<?= $i ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>"
                                       class="<?= $i == $rec_page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($rec_page < $rec_total_pages): ?>
                                    <a href="?action=dashboard&page=recordings&recordings_page=<?= $rec_page + 1 ?>&rec_sort_col=<?= $rec_sort_column ?>&rec_sort_dir=<?= $rec_sort_direction ?><?php if (!empty($rec_start_date)): ?>&rec_start_date=<?= $rec_start_date ?><?php endif; ?><?php if (!empty($rec_end_date)): ?>&rec_end_date=<?= $rec_end_date ?><?php endif; ?><?php if (!empty($rec_user_filter)): ?>&rec_user_filter=<?= $rec_user_filter ?><?php endif; ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">No recordings found</div>
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
                        <div class="filter-item" style="position: relative; display: inline-block; min-width: 200px;">
                            <label for="act_search_input">Search by Rep ID or Name:</label>
                            <input type="text" id="act_search_input" placeholder="Search by Rep ID or Name"
                                   value="<?php echo htmlspecialchars($act_search_term); ?>"
                                   onclick="toggleActUserDropdown()"
                                   onkeyup="filterActUserOptions()" />
                            <div id="act-user-dropdown" class="dropdown-content">
                                <div style="padding: 10px; background-color: #f1f1f1; font-weight: bold; border-bottom: 1px solid #ddd;" onclick="selectAllActUsers()">Select All Users</div>
                                <div style="padding: 10px; background-color: #f1f1f1; font-weight: bold;" onclick="clearActUserSelection()">Clear Selection</div>
                                <?php
                                // Fetch all users for the filter dropdown
                                $act_user_filter_stmt = $pdo->query("SELECT ID, Name, RepID FROM salesrep WHERE Actives = 'YES' ORDER BY RepID");
                                $act_filter_users = $act_user_filter_stmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($act_filter_users as $filter_user): ?>
                                    <div class="user-option"
                                         data-id="<?php echo $filter_user['ID']; ?>"
                                         data-repid="<?php echo htmlspecialchars($filter_user['RepID']); ?>"
                                         data-name="<?php echo htmlspecialchars($filter_user['Name']); ?>"
                                         onclick="selectActUser(<?php echo $filter_user['ID']; ?>, '<?php echo addslashes($filter_user['RepID']); ?>', '<?php echo addslashes($filter_user['Name']); ?>')">
                                        <?php echo htmlspecialchars($filter_user['RepID']); ?> - <?php echo htmlspecialchars($filter_user['Name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="act_search" name="act_search" value="<?php echo $act_search_term; ?>" />
                        </div>
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
                                        <th><a href="?action=dashboard&page=activities&activities_page=<?= $act_page ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">ID <?= $act_sort_column === 'ua.ID' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=activities&activities_page=<?= $act_page ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">User <?= $act_sort_column === 's.Name' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=activities&activities_page=<?= $act_page ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">Rep ID <?= $act_sort_column === 's.RepID' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=activities&activities_page=<?= $act_page ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">Activity Type <?= $act_sort_column === 'ua.activity_type' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=activities&activities_page=<?= $act_page ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">Date/Time <?= $act_sort_column === 'ua.rDateTime' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=activities&activities_page=<?= $act_page ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">Duration <?= $act_sort_column === 'ua.duration' ? ($act_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
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
                                            <td><?php echo htmlspecialchars($activity['duration'] ?? 0); ?> sec</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div class="pagination">
                                <?php if ($act_page > 1): ?>
                                    <a href="?action=dashboard&page=activities&activities_page=<?= $act_page - 1 ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">&laquo; Previous</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $act_page - 2); $i <= min($act_total_pages, $act_page + 2); $i++): ?>
                                    <a href="?action=dashboard&page=activities&activities_page=<?= $i ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>"
                                       class="<?= $i == $act_page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($act_page < $act_total_pages): ?>
                                    <a href="?action=dashboard&page=activities&activities_page=<?= $act_page + 1 ?>&act_sort_col=<?= $act_sort_column ?>&act_sort_dir=<?= $act_sort_direction ?><?php if (!empty($start_date)): ?>&start_date=<?= $start_date ?><?php endif; ?><?php if (!empty($end_date)): ?>&end_date=<?= $end_date ?><?php endif; ?><?php if (!empty($act_search_term)): ?>&act_search=<?= urlencode($act_search_term) ?><?php endif; ?>">Next &raquo;</a>
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
                        <button class="action-btn refresh-btn" onclick="addUser()">Add New User</button>
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
                                        <th><a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">ID <?= $all_users_sort_column === 's.ID' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Rep ID <?= $all_users_sort_column === 's.RepID' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Name <?= $all_users_sort_column === 's.Name' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Branch ID <?= $all_users_sort_column === 's.br_id' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Email <?= $all_users_sort_column === 's.emailAddress' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                                        <th><a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Join Date <?= $all_users_sort_column === 's.join_date' ? ($all_users_sort_direction === 'ASC' ? '↑' : '↓') : '' ?></a></th>
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
                                                <a href="?action=watch_live&user_id=<?php echo $user['ID']; ?>" class="view-btn">Watch Live</a>
                                                <a href="?action=combine_recordings&user_id=<?php echo $user['ID']; ?>" class="download-btn">Combine Recordings</a>
                                                <button class="edit-btn" onclick="editUser(<?= $user['ID'] ?>)">Edit</button>
                                                <button class="delete-btn" onclick="deleteUser(<?= $user['ID'] ?>, '<?= addslashes(htmlspecialchars($user['Name'])) ?>')">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <div class="pagination">
                                <?php if ($all_users_page > 1): ?>
                                    <a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page - 1 ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">&laquo; Previous</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $all_users_page - 2); $i <= min($all_users_total_pages, $all_users_page + 2); $i++): ?>
                                    <a href="?action=dashboard&page=all_users&all_users_page=<?= $i ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>"
                                       class="<?= $i == $all_users_page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($all_users_page < $all_users_total_pages): ?>
                                    <a href="?action=dashboard&page=all_users&all_users_page=<?= $all_users_page + 1 ?>&all_users_sort_col=<?= $all_users_sort_column ?>&all_users_sort_dir=<?= $all_users_sort_direction ?><?php if (!empty($account_status_filter)): ?>&account_status=<?= $account_status_filter ?><?php endif; ?><?php if (!empty($online_status_filter)): ?>&online_status=<?= $online_status_filter ?><?php endif; ?><?php if (!empty($all_users_branch_filter)): ?>&all_users_branch=<?= $all_users_branch_filter ?><?php endif; ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">No users found</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="reports" class="tab-content">
                <div class="section">
                    <div class="section-header">
                        <h2><span class="icon">📊</span> Reports & Analytics</h2>
                    </div>
                    <div class="section-content">
                        <p>Access detailed reports and analytics about user activity and system usage.</p>
                        <div style="margin-top: 20px;">
                            <a href="?action=reports" class="action-btn export-btn" style="display: inline-block; text-decoration: none; color: white;">View Full Reports</a>
                        </div>
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
                    'all-users': 'all_users',
                    'reports': 'reports'
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
                } else if (currentPage === 'reports') {
                    activeTabId = 'reports';
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
                        (activeTabId === 'all-users' && tabText.includes('All Users')) ||
                        (activeTabId === 'reports' && tabText.includes('Reports'))
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
                const searchInput = document.getElementById('act_search_input').value;
                const hiddenSearchValue = document.getElementById('act_search').value;
                
                let url = '?action=dashboard&page=activities&';
                if (startDate) {
                    url += `start_date=${startDate}&`;
                }
                if (endDate) {
                    url += `end_date=${endDate}&`;
                }
                // Use the hidden value if it exists (for dropdown selections), otherwise use the visible input
                if (hiddenSearchValue) {
                    url += `act_search=${encodeURIComponent(hiddenSearchValue)}&`;
                } else if (searchInput) {
                    url += `act_search=${encodeURIComponent(searchInput)}&`;
                }

                // Remove trailing '&' if present
                if (url.endsWith('&')) {
                    url = url.slice(0, -1);
                }

                // Reload the page with the filter parameters
                window.location.href = url;
            }
            
            function applyFiltersWithTerm(searchTerm) {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                
                let url = '?action=dashboard&page=activities&';
                if (startDate) {
                    url += `start_date=${startDate}&`;
                }
                if (endDate) {
                    url += `end_date=${endDate}&`;
                }
                if (searchTerm) {
                    url += `act_search=${encodeURIComponent(searchTerm)}&`;
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

                let url = '?action=dashboard&page=active_users&';
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

                let url = '?action=dashboard&page=all_users&';
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

            function filterRecordings() {
                const startDate = document.getElementById('rec_start_date').value;
                const endDate = document.getElementById('rec_end_date').value;
                const userFilter = document.getElementById('rec_user_filter').value;
                const sortCol = '<?= $rec_sort_column ?>';
                const sortDir = '<?= $rec_sort_direction ?>';

                let url = '?action=dashboard&page=recordings&';
                if (startDate) {
                    url += `rec_start_date=${startDate}&`;
                }
                if (endDate) {
                    url += `rec_end_date=${endDate}&`;
                }
                if (userFilter) {
                    url += `rec_user_filter=${userFilter}&`;
                }
                if (sortCol) {
                    url += `rec_sort_col=${sortCol}&`;
                }
                if (sortDir) {
                    url += `rec_sort_dir=${sortDir}&`;
                }

                // Remove trailing '&'
                if (url.endsWith('&')) {
                    url = url.slice(0, -1);
                }

                window.location.href = url;
            }

            function selectAllUsers() {
                const input = document.getElementById('rec_user_filter_input');
                const hiddenInput = document.getElementById('rec_user_filter');

                input.value = 'All Users Selected';
                hiddenInput.value = ''; // Empty value means no specific user filter

                // Close the dropdown
                document.getElementById('user-dropdown').style.display = 'none';

                // Trigger the filter function
                filterRecordings();
            }

            function toggleUserDropdown() {
                const dropdown = document.getElementById('user-dropdown');
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }

            function selectUser(userId, repId, name) {
                const input = document.getElementById('rec_user_filter_input');
                const hiddenInput = document.getElementById('rec_user_filter');

                input.value = repId + ' - ' + name;
                hiddenInput.value = userId;

                // Close the dropdown
                document.getElementById('user-dropdown').style.display = 'none';

                // Trigger the filter function
                filterRecordings();
            }

            function clearUserSelection() {
                const input = document.getElementById('rec_user_filter_input');
                const hiddenInput = document.getElementById('rec_user_filter');

                input.value = '';
                hiddenInput.value = '';

                // Close the dropdown
                document.getElementById('user-dropdown').style.display = 'none';

                // Trigger the filter function
                filterRecordings();
            }

            function filterUserOptions() {
                const input = document.getElementById('rec_user_filter_input');
                const filter = input.value.toLowerCase();
                const div = document.getElementById('user-dropdown');
                const options = div.getElementsByClassName('user-option');

                for (let i = 0; i < options.length; i++) {
                    const repId = options[i].getAttribute('data-repid').toLowerCase();
                    const name = options[i].getAttribute('data-name').toLowerCase();

                    if (repId.indexOf(filter) > -1 || name.indexOf(filter) > -1) {
                        options[i].style.display = '';
                    } else {
                        options[i].style.display = 'none';
                    }
                }
            }

            // Close dropdown if clicked outside
            document.addEventListener('click', function(event) {
                const input = document.getElementById('rec_user_filter_input');
                const dropdown = document.getElementById('user-dropdown');

                if (event.target !== input && !input.contains(event.target) &&
                    event.target !== dropdown && !dropdown.contains(event.target)) {
                    if (dropdown.style.display === 'block') {
                        dropdown.style.display = 'none';
                    }
                }
            });

            function editUser(userId) {
                // Open a modal or redirect to edit page
                alert('Edit user functionality would open for user ID: ' + userId);
                // In a real implementation, you would open a modal or redirect to an edit page
                window.location.href = '?action=edit_user&id=' + userId;
            }

            function deleteUser(userId, userName) {
                if (confirm('Are you sure you want to delete user "' + userName + '"? This action cannot be undone.')) {
                    // In a real implementation, you would make an AJAX call or submit a form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '?action=delete_user&id=' + userId;

                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'confirm_delete';
                    input.value = '1';

                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function addUser() {
                // Redirect to add user page
                window.location.href = '?action=add_user';
            }

            // Functions for the user dropdown in activities search
            function selectAllActUsers() {
                const input = document.getElementById('act_search_input');
                const hiddenInput = document.getElementById('act_search');

                input.value = 'All Users Selected';
                hiddenInput.value = ''; // Empty value means no specific user filter

                // Close the dropdown
                document.getElementById('act-user-dropdown').style.display = 'none';

                // Trigger the filter function
                applyFilters();
            }

            function toggleActUserDropdown() {
                const dropdown = document.getElementById('act-user-dropdown');
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }

            function selectActUser(userId, repId, name) {
                const input = document.getElementById('act_search_input');
                const hiddenInput = document.getElementById('act_search');

                const displayValue = repId + ' - ' + name;
                input.value = displayValue;
                hiddenInput.value = displayValue; // Store the full display value for search

                // Close the dropdown
                document.getElementById('act-user-dropdown').style.display = 'none';

                // Trigger the filter function
                applyFilters();
            }

            function clearActUserSelection() {
                const input = document.getElementById('act_search_input');
                const hiddenInput = document.getElementById('act_search');

                input.value = '';
                hiddenInput.value = '';

                // Close the dropdown
                document.getElementById('act-user-dropdown').style.display = 'none';

                // Trigger the filter function
                applyFilters();
            }

            function filterActUserOptions() {
                const input = document.getElementById('act_search_input');
                const filter = input.value.toLowerCase();
                const div = document.getElementById('act-user-dropdown');
                const options = div.getElementsByClassName('user-option');

                for (let i = 0; i < options.length; i++) {
                    const repId = options[i].getAttribute('data-repid').toLowerCase();
                    const name = options[i].getAttribute('data-name').toLowerCase();

                    if (repId.indexOf(filter) > -1 || name.indexOf(filter) > -1) {
                        options[i].style.display = '';
                    } else {
                        options[i].style.display = 'none';
                    }
                }
            }

            // Close dropdown if clicked outside
            document.addEventListener('click', function(event) {
                const input = document.getElementById('act_search_input');
                const dropdown = document.getElementById('act-user-dropdown');

                if (event.target !== input && !input.contains(event.target) &&
                    event.target !== dropdown && !dropdown.contains(event.target)) {
                    if (dropdown.style.display === 'block') {
                        dropdown.style.display = 'none';
                    }
                }
            });
            
            // Function to toggle all checkboxes in a group
            function toggleSelectAll(sourceCheckbox, className) {
                const checkboxes = document.querySelectorAll('.' + className);
                checkboxes.forEach(checkbox => {
                    checkbox.checked = sourceCheckbox.checked;
                });
            }
            
            // Function to confirm bulk delete
            function confirmBulkDelete(className, type) {
                const selectedCheckboxes = document.querySelectorAll('.' + className + ':checked');
                if (selectedCheckboxes.length === 0) {
                    alert('Please select at least one item to delete.');
                    return;
                }
                
                const count = selectedCheckboxes.length;
                const userConfirmed = confirm(`Are you sure you want to delete ${count} selected ${type} record(s)? This action cannot be undone.`);
                
                if (userConfirmed) {
                    // Create a form to submit the selected IDs
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '?action=bulk_delete';
                    
                    // Add all selected IDs as hidden inputs
                    selectedCheckboxes.forEach(checkbox => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_ids[]';
                        input.value = checkbox.value;
                        form.appendChild(input);
                    });
                    
                    // Add a hidden input for the type of records
                    const typeInput = document.createElement('input');
                    typeInput.type = 'hidden';
                    typeInput.name = 'type';
                    typeInput.value = type;
                    form.appendChild(typeInput);
                    
                    // Submit the form
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        </script>
    </body>
    </html>
    <?php
}

// Handle viewing recordings
function handleView() {
    checkAdminSession();

    // This function handles viewing recordings
    // Since the original application stores recordings in uploads/ directory
    // we'll create a simple viewer that serves the file with appropriate content type

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
}

// Handle downloading recordings
function handleDownload() {
    checkAdminSession();

    // This function handles downloading recordings
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
}

// Handle logout
function handleLogout() {
    session_start();

    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header('Location: ?action=login');
    exit;
}

// Check if admin is logged in and session is valid
function checkAdminSession() {
    session_start();

    // Check if admin is logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ?action=login');
        exit;
    }

    // Check if session has timed out (30 minutes of inactivity)
    $timeout_duration = 30 * 60; // 30 minutes in seconds

    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout_duration)) {
        // Session has expired
        session_destroy();
        header('Location: ?action=login&error=session_expired');
        exit;
    }

    // Check if IP address has changed (possible session hijacking)
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        header('Location: ?action=login&error=security_violation');
        exit;
    }

    // Refresh login time to prevent timeout
    $_SESSION['login_time'] = time();
}

// Show edit user form
function showEditUserForm() {
    checkAdminSession();

    // Get user ID from GET parameter
    $userId = $_GET['id'] ?? null;

    if (!$userId) {
        header('Location: ?action=dashboard');
        exit;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Connection failed: Could not establish database connection.");
    }

    // Fetch user data
    $stmt = $pdo->prepare("SELECT * FROM salesrep WHERE ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: ?action=dashboard');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit User - Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .form-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #555;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 1em;
                transition: var(--transition);
            }

            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            }

            .submit-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
                border: none;
                padding: 14px 25px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1.1em;
                font-weight: 500;
                width: 100%;
                transition: var(--transition);
            }

            .submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(76, 201, 240, 0.4);
            }

            .error {
                color: var(--danger-color);
                margin-top: 10px;
                text-align: center;
            }

            .success {
                color: var(--success-color);
                margin-top: 10px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Edit User</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="form-container">
            <form method="post" action="?action=update_user&id=<?= $user['ID'] ?>">
                <div class="form-group">
                    <label for="RepID">Rep ID:</label>
                    <input type="text" id="RepID" name="RepID" value="<?= htmlspecialchars($user['RepID']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="Name">Name:</label>
                    <input type="text" id="Name" name="Name" value="<?= htmlspecialchars($user['Name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="br_id">Branch ID:</label>
                    <input type="text" id="br_id" name="br_id" value="<?= htmlspecialchars($user['br_id']) ?>">
                </div>

                <div class="form-group">
                    <label for="emailAddress">Email Address:</label>
                    <input type="email" id="emailAddress" name="emailAddress" value="<?= htmlspecialchars($user['emailAddress']) ?>">
                </div>

                <div class="form-group">
                    <label for="Actives">Account Status:</label>
                    <select id="Actives" name="Actives">
                        <option value="YES" <?= $user['Actives'] === 'YES' ? 'selected' : '' ?>>Active</option>
                        <option value="NO" <?= $user['Actives'] === 'NO' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password (leave blank to keep current):</label>
                    <input type="password" id="password" name="password">
                </div>

                <button type="submit" class="submit-btn">Update User</button>
            </form>

            <?php if (isset($_GET['error'])): ?>
                <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Update user in database
function updateUser() {
    checkAdminSession();

    // Get user ID from GET parameter
    $userId = $_GET['id'] ?? null;

    if (!$userId) {
        header('Location: ?action=dashboard');
        exit;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Connection failed: Could not establish database connection.");
    }

    // Get form data
    $repId = $_POST['RepID'] ?? '';
    $name = $_POST['Name'] ?? '';
    $brId = $_POST['br_id'] ?? '';
    $email = $_POST['emailAddress'] ?? '';
    $status = $_POST['Actives'] ?? 'YES';
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($repId) || empty($name)) {
        header('Location: ?action=edit_user&id=' . $userId . '&error=Rep ID and Name are required');
        exit;
    }

    try {
        if (!empty($password)) {
            // Hash the password if provided
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            // Update user with new password
            $stmt = $pdo->prepare("UPDATE salesrep SET RepID = ?, Name = ?, br_id = ?, emailAddress = ?, Actives = ?, password = ? WHERE ID = ?");
            $stmt->execute([$repId, $name, $brId, $email, $status, $hashedPassword, $userId]);
        } else {
            // Update user without changing password
            $stmt = $pdo->prepare("UPDATE salesrep SET RepID = ?, Name = ?, br_id = ?, emailAddress = ?, Actives = ? WHERE ID = ?");
            $stmt->execute([$repId, $name, $brId, $email, $status, $userId]);
        }

        header('Location: ?action=dashboard&success=User updated successfully');
        exit;
    } catch(PDOException $e) {
        header('Location: ?action=edit_user&id=' . $userId . '&error=Error updating user: ' . $e->getMessage());
        exit;
    }
}

// Delete user from database
function deleteUser() {
    checkAdminSession();

    // Get user ID from GET parameter
    $userId = $_GET['id'] ?? null;

    if (!$userId) {
        header('Location: ?action=dashboard');
        exit;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Connection failed: Could not establish database connection.");
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT Name FROM salesrep WHERE ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: ?action=dashboard&error=User not found');
        exit;
    }

    // Perform deletion
    try {
        $stmt = $pdo->prepare("DELETE FROM salesrep WHERE ID = ?");
        $stmt->execute([$userId]);

        // Also delete associated user activities
        $stmt = $pdo->prepare("DELETE FROM user_activity WHERE salesrepTb = ?");
        $stmt->execute([$userId]);

        // Also delete associated web images
        $stmt = $pdo->prepare("DELETE FROM web_images WHERE user_id = ?");
        $stmt->execute([$userId]);

        header('Location: ?action=dashboard&success=User deleted successfully');
        exit;
    } catch(PDOException $e) {
        header('Location: ?action=dashboard&error=Error deleting user: ' . $e->getMessage());
        exit;
    }
}

// Handle bulk deletion of users
function handleBulkDelete() {
    checkAdminSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=dashboard&error=Invalid request method');
        exit;
    }

    $selectedIds = $_POST['selected_ids'] ?? [];
    $type = $_POST['type'] ?? '';

    if (empty($selectedIds)) {
        header('Location: ?action=dashboard&error=No items selected for deletion');
        exit;
    }

    // Sanitize IDs
    $selectedIds = array_map('intval', $selectedIds);
    $selectedIds = array_filter($selectedIds, function($id) { return $id > 0; });

    if (empty($selectedIds)) {
        header('Location: ?action=dashboard&error=No valid IDs provided');
        exit;
    }

    // Database connection
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        header('Location: ?action=dashboard&error=Database connection failed');
        exit;
    }

    try {
        $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
        
        if ($type === 'active' || $type === 'all-users') {
            // Delete users and associated data
            $stmt = $pdo->prepare("DELETE FROM salesrep WHERE ID IN ($placeholders)");
            $stmt->execute($selectedIds);

            // Also delete associated user activities
            $stmt = $pdo->prepare("DELETE FROM user_activity WHERE salesrepTb IN ($placeholders)");
            $stmt->execute($selectedIds);

            // Also delete associated web images
            $stmt = $pdo->prepare("DELETE FROM web_images WHERE user_id IN ($placeholders)");
            $stmt->execute($selectedIds);

            $deletedCount = count($selectedIds);
            header("Location: ?action=dashboard&success=$deletedCount user(s) deleted successfully");
        } elseif ($type === 'recordings') {
            // Delete recordings
            $stmt = $pdo->prepare("DELETE FROM web_images WHERE ID IN ($placeholders)");
            $stmt->execute($selectedIds);

            $deletedCount = count($selectedIds);
            header("Location: ?action=dashboard&success=$deletedCount recording(s) deleted successfully");
        } else {
            header('Location: ?action=dashboard&error=Invalid type specified');
            exit;
        }
        
        exit;
    } catch(PDOException $e) {
        header('Location: ?action=dashboard&error=Error deleting records: ' . $e->getMessage());
        exit;
    }
}

// Show add user form
function showAddUserForm() {
    checkAdminSession();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Add New User - Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .form-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #555;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 1em;
                transition: var(--transition);
            }

            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            }

            .submit-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
                border: none;
                padding: 14px 25px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1.1em;
                font-weight: 500;
                width: 100%;
                transition: var(--transition);
            }

            .submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(76, 201, 240, 0.4);
            }

            .error {
                color: var(--danger-color);
                margin-top: 10px;
                text-align: center;
            }

            .success {
                color: var(--success-color);
                margin-top: 10px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Add New User</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="form-container">
            <form method="post" action="?action=create_user">
                <div class="form-group">
                    <label for="RepID">Rep ID:</label>
                    <input type="text" id="RepID" name="RepID" required>
                </div>

                <div class="form-group">
                    <label for="Name">Name:</label>
                    <input type="text" id="Name" name="Name" required>
                </div>

                <div class="form-group">
                    <label for="br_id">Branch ID:</label>
                    <input type="text" id="br_id" name="br_id">
                </div>

                <div class="form-group">
                    <label for="emailAddress">Email Address:</label>
                    <input type="email" id="emailAddress" name="emailAddress">
                </div>

                <div class="form-group">
                    <label for="Actives">Account Status:</label>
                    <select id="Actives" name="Actives">
                        <option value="YES">Active</option>
                        <option value="NO">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="submit-btn">Add User</button>
            </form>

            <?php if (isset($_GET['error'])): ?>
                <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

// Create new user in database
function createUser() {
    checkAdminSession();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Connection failed: Could not establish database connection.");
    }

    // Get form data
    $repId = $_POST['RepID'] ?? '';
    $name = $_POST['Name'] ?? '';
    $brId = $_POST['br_id'] ?? '';
    $email = $_POST['emailAddress'] ?? '';
    $status = $_POST['Actives'] ?? 'YES';
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($repId) || empty($name) || empty($password)) {
        header('Location: ?action=add_user&error=Rep ID, Name, and Password are required');
        exit;
    }

    // Check if RepID already exists
    $stmt = $pdo->prepare("SELECT ID FROM salesrep WHERE RepID = ?");
    $stmt->execute([$repId]);
    if ($stmt->fetch()) {
        header('Location: ?action=add_user&error=Rep ID already exists');
        exit;
    }

    try {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO salesrep (RepID, Name, br_id, emailAddress, Actives, password, join_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$repId, $name, $brId, $email, $status, $hashedPassword]);

        header('Location: ?action=dashboard&success=User created successfully');
        exit;
    } catch(PDOException $e) {
        header('Location: ?action=add_user&error=Error creating user: ' . $e->getMessage());
        exit;
    }
}

// Show reports and analytics
function showReports() {
    checkAdminSession();

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Connection failed: Could not establish database connection.");
    }

    // Get date range for reports
    $start_date = $_GET['report_start_date'] ?? date('Y-m-01'); // First day of current month
    $end_date = $_GET['report_end_date'] ?? date('Y-m-d');     // Current date

    // Total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM salesrep WHERE Actives = 'YES'");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Active users in date range
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT salesrepTb) as active_count
        FROM user_activity
        WHERE rDateTime BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $active_users = $stmt->fetch(PDO::FETCH_ASSOC)['active_count'];

    // Total recordings in date range
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_recordings
        FROM web_images
        WHERE type = 'recording' AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $total_recordings = $stmt->fetch(PDO::FETCH_ASSOC)['total_recordings'];

    // Recordings by user in date range
    $stmt = $pdo->prepare("
        SELECT s.Name, s.RepID, COUNT(w.ID) as recording_count
        FROM web_images w
        LEFT JOIN salesrep s ON w.user_id = s.ID
        WHERE w.type = 'recording' AND w.date BETWEEN ? AND ?
        GROUP BY w.user_id
        ORDER BY recording_count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $recordings_by_user = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Activity types distribution
    $stmt = $pdo->prepare("
        SELECT activity_type, COUNT(*) as count
        FROM user_activity
        WHERE rDateTime BETWEEN ? AND ?
        GROUP BY activity_type
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $activity_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Daily activity trend
    $stmt = $pdo->prepare("
        SELECT DATE(rDateTime) as activity_date, COUNT(*) as daily_count
        FROM user_activity
        WHERE rDateTime BETWEEN ? AND ?
        GROUP BY DATE(rDateTime)
        ORDER BY activity_date ASC
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // User activity duration (if duration is tracked)
    $stmt = $pdo->prepare("
        SELECT s.Name, s.RepID, SUM(ua.duration) as total_duration
        FROM user_activity ua
        LEFT JOIN salesrep s ON ua.salesrepTb = s.ID
        WHERE ua.rDateTime BETWEEN ? AND ? AND ua.duration IS NOT NULL
        GROUP BY ua.salesrepTb
        ORDER BY total_duration DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $user_durations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reports & Analytics - Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .filters {
                background-color: white;
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                margin-bottom: 20px;
            }

            .filter-row {
                display: flex;
                gap: 15px;
                align-items: end;
            }

            .filter-item {
                display: flex;
                flex-direction: column;
                flex: 1;
            }

            .filter-item label {
                margin-bottom: 5px;
                font-weight: 500;
                color: #555;
            }

            .filter-item input {
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 1em;
            }

            .apply-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-weight: 500;
                height: fit-content;
            }

            .apply-btn:hover {
                opacity: 0.9;
            }

            .reports-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .report-card {
                background: white;
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                transition: var(--transition);
            }

            .report-card h3 {
                margin-top: 0;
                color: var(--primary-color);
                border-bottom: 2px solid #f0f0f0;
                padding-bottom: 10px;
            }

            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }

            .stat-box {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 15px;
                border-radius: var(--border-radius);
                text-align: center;
            }

            .stat-box .number {
                font-size: 2em;
                font-weight: bold;
                display: block;
            }

            .stat-box .label {
                font-size: 0.9em;
                opacity: 0.9;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }

            th, td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }

            th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #495057;
            }

            tr:hover {
                background-color: #f0f5ff;
            }

            .chart-placeholder {
                background: #f8f9fa;
                height: 300px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: var(--border-radius);
                margin: 10px 0;
                color: #6c757d;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Reports & Analytics</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="filters">
            <div class="filter-row">
                <div class="filter-item">
                    <label for="report_start_date">Start Date:</label>
                    <input type="date" id="report_start_date" name="report_start_date" value="<?= $start_date ?>">
                </div>
                <div class="filter-item">
                    <label for="report_end_date">End Date:</label>
                    <input type="date" id="report_end_date" name="report_end_date" value="<?= $end_date ?>">
                </div>
                <button class="apply-btn" onclick="applyReportFilters()">Apply Filters</button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <span class="number"><?= $total_users ?></span>
                <span class="label">Total Users</span>
            </div>
            <div class="stat-box">
                <span class="number"><?= $active_users ?></span>
                <span class="label">Active in Period</span>
            </div>
            <div class="stat-box">
                <span class="number"><?= $total_recordings ?></span>
                <span class="label">Recordings in Period</span>
            </div>
        </div>

        <div class="reports-container">
            <div class="report-card">
                <h3>Recordings by User</h3>
                <?php if (count($recordings_by_user) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Rep ID</th>
                                <th>Recordings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recordings_by_user as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['RepID'] ?? 'N/A') ?></td>
                                    <td><?= $row['recording_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No recordings found in the selected period.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>Activity Types Distribution</h3>
                <?php if (count($activity_types) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Activity Type</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activity_types as $row): ?>
                                <tr>
                                    <td><?= ucfirst(str_replace('-', ' ', $row['activity_type'])) ?></td>
                                    <td><?= $row['count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No activities found in the selected period.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="reports-container">
            <div class="report-card">
                <h3>Daily Activity Trend</h3>
                <?php if (count($daily_activity) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activities</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_activity as $row): ?>
                                <tr>
                                    <td><?= $row['activity_date'] ?></td>
                                    <td><?= $row['daily_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No daily activity data found in the selected period.</p>
                <?php endif; ?>
            </div>

            <div class="report-card">
                <h3>Top Users by Duration</h3>
                <?php if (count($user_durations) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Rep ID</th>
                                <th>Total Duration (sec)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_durations as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['Name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['RepID'] ?? 'N/A') ?></td>
                                    <td><?= round($row['total_duration'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No duration data found in the selected period. (Duration tracking may not be enabled)</p>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function applyReportFilters() {
                const startDate = document.getElementById('report_start_date').value;
                const endDate = document.getElementById('report_end_date').value;

                let url = '?action=reports&';
                if (startDate) {
                    url += `report_start_date=${startDate}&`;
                }
                if (endDate) {
                    url += `report_end_date=${endDate}&`;
                }

                // Remove trailing '&' if present
                if (url.endsWith('&')) {
                    url = url.slice(0, -1);
                }

                window.location.href = url;
            }
        </script>
    </body>
    </html>
    <?php
}

// Add a notification
function addNotification($title, $message, $type = 'info', $priority = 'normal') {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log("Database connection failed in addNotification");
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (title, message, type, priority, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $message, $type, $priority]);
        return true;
    } catch(PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

// Get notifications
function getNotifications($limit = 10, $offset = 0) {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log("Database connection failed in getNotifications");
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

// Mark notification as read
function markNotificationAsRead($notificationId) {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log("Database connection failed in markNotificationAsRead");
        return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$notificationId]);
        return true;
    } catch(PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// Get unread notifications count
function getUnreadNotificationsCount() {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log("Database connection failed in getUnreadNotificationsCount");
        return 0;
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch(PDOException $e) {
        error_log("Error getting unread notifications count: " . $e->getMessage());
        return 0;
    }
}

// Get latest recordings for a specific user
function getUserLatestRecordings($userId, $limit = 10) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Database connection failed in getUserLatestRecordings");
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT w.*
            FROM web_images w
            WHERE w.user_id = ? AND w.type = 'recording' AND w.date = CURDATE()
            ORDER BY w.date DESC, w.time DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error getting user recordings: " . $e->getMessage());
        return [];
    }
}

// Get all user recordings for continuous playback
function getAllUserRecordings($userId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Database connection failed in getAllUserRecordings");
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT w.*
            FROM web_images w
            WHERE w.user_id = ? AND w.type = 'recording' AND w.date = CURDATE()
            ORDER BY w.date DESC, w.time DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error getting all user recordings: " . $e->getMessage());
        return [];
    }
}

// Create notifications table if it doesn't exist
function createNotificationsTable() {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        error_log("Database connection failed in createNotificationsTable");
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            CREATE TABLE IF NOT EXISTS admin_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute();
        return true;
    } catch(PDOException $e) {
        error_log("Error creating notifications table: " . $e->getMessage());
        return false;
    }
}

// Show notifications page
function showNotifications() {
    checkAdminSession();

    // Get notifications
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $notifications = getNotifications($limit, $offset);

    // Get total count for pagination
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            $totalPages = 1;
        } else {
            $countStmt = $pdo->query("SELECT COUNT(*) as total FROM admin_notifications");
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPages = ceil($totalCount / $limit);
        }
    } catch(PDOException $e) {
        $totalPages = 1;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Notifications - Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .notification-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }

            .notification {
                background: white;
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                border-left: 4px solid var(--info-color);
                transition: var(--transition);
            }

            .notification.unread {
                border-left: 4px solid var(--primary-color);
                background-color: #f0f7ff;
            }

            .notification.info {
                border-left-color: var(--info-color);
            }

            .notification.success {
                border-left-color: var(--success-color);
            }

            .notification.warning {
                border-left-color: var(--warning-color);
            }

            .notification.error {
                border-left-color: var(--danger-color);
            }

            .notification-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }

            .notification-title {
                font-weight: 600;
                font-size: 1.1em;
                color: #333;
            }

            .notification-time {
                font-size: 0.8em;
                color: #6c757d;
            }

            .notification-body {
                color: #555;
                margin-bottom: 10px;
            }

            .notification-actions {
                display: flex;
                gap: 10px;
            }

            .mark-read-btn {
                background: var(--info-color);
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.85em;
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
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Notifications</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="notification-list">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification <?= $notification['is_read'] ? '' : 'unread' ?> <?= $notification['type'] ?>" data-notification-id="<?= $notification['id'] ?>">
                        <div class="notification-header">
                            <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                            <div class="notification-time"><?= $notification['created_at'] ?></div>
                        </div>
                        <div class="notification-body">
                            <?= htmlspecialchars($notification['message']) ?>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                                <button class="mark-read-btn" onclick="markAsRead(<?= $notification['id'] ?>)">Mark as Read</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #6c757d;">
                    <h3>No notifications</h3>
                    <p>You don't have any notifications at this time.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?action=notifications&page=<?= $page - 1 ?>">&laquo; Previous</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?action=notifications&page=<?= $i ?>"
                   class="<?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?action=notifications&page=<?= $page + 1 ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>

        <script>
            function markAsRead(notificationId) {
                // Send AJAX request to mark notification as read
                fetch(`?action=mark_notification_read&id=\${notificationId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI to reflect that notification is read
                        const notificationElement = document.querySelector(`.notification[data-notification-id="\${notificationId}"]`);
                        if (notificationElement) {
                            notificationElement.classList.remove('unread');
                            notificationElement.querySelector('.mark-read-btn').remove();
                        }
                    } else {
                        alert('Error marking notification as read');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error marking notification as read');
                });
            }
        </script>
    </body>
    </html>
    <?php
}

// Mark notification as read via AJAX
function markNotificationRead() {
    checkAdminSession();

    $notificationId = $_GET['id'] ?? null;

    if ($notificationId) {
        $result = markNotificationAsRead($notificationId);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }
    exit;
}

// Initialize notifications table on script load
createNotificationsTable();

// Add some sample notifications for testing
// addNotification('System Update', 'A new version of the system is available for update.', 'info', 'normal');
// addNotification('Security Alert', 'Unusual login activity detected from IP 192.168.1.100', 'warning', 'high');

// Backup and restore functionality

// Add backup/restore action cases to the switch statement
// Note: These were added earlier in the switch statement

// Create backup of database
function createBackup() {
    checkAdminSession();

    // Define backup directory
    $backup_dir = __DIR__ . '/../backups/';

    // Create backup directory if it doesn't exist
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    // Generate backup filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_{$timestamp}.sql";
    $filepath = $backup_dir . $filename;

    // Create tables list to backup
    $tables = ['salesrep', 'user_activity', 'web_images', 'admin_notifications'];

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [
                'success' => false,
                'error' => 'Database connection failed'
            ];
        }

        $backup_content = "-- Database Backup for remote-xwork\n";
        $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Get table structure
            $table_info = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $backup_content .= $table_info[1] . ";\n\n";

            // Get table data
            $result = $pdo->query("SELECT * FROM `$table`");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $values = array_map(function($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return $pdo->quote($value);
                }, $row);

                $backup_content .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
            }
            $backup_content .= "\n";
        }

        $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Write backup to file
        file_put_contents($filepath, $backup_content);

        // Return success
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
    } catch (PDOException $e) {
        error_log("Backup creation failed: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Get list of available backups
function getBackups() {
    checkAdminSession();

    $backup_dir = __DIR__ . '/../backups/';

    if (!is_dir($backup_dir)) {
        return [];
    }

    $files = scandir($backup_dir);
    $backups = [];

    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql' && strpos($file, 'backup_') === 0) {
            $filepath = $backup_dir . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
                'path' => $filepath
            ];
        }
    }

    // Sort by modification time (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });

    return $backups;
}

// Download backup file
function downloadBackup($filename) {
    checkAdminSession();

    $backup_dir = __DIR__ . '/../backups/';
    $filepath = $backup_dir . basename($filename);

    // Security check: ensure file exists and is in the backup directory
    if (!file_exists($filepath) || strpos(realpath($filepath), realpath($backup_dir)) !== 0) {
        header('HTTP/1.0 404 Not Found');
        exit('Backup file not found');
    }

    // Set headers for download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Content-Length: ' . filesize($filepath));

    // Output file content
    readfile($filepath);
    exit;
}

// Restore from backup
function restoreFromBackup($filename) {
    checkAdminSession();

    $backup_dir = __DIR__ . '/../backups/';
    $filepath = $backup_dir . basename($filename);

    // Security check: ensure file exists and is in the backup directory
    if (!file_exists($filepath) || strpos(realpath($filepath), realpath($backup_dir)) !== 0) {
        return [
            'success' => false,
            'error' => 'Backup file not found'
        ];
    }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [
                'success' => false,
                'error' => 'Database connection failed'
            ];
        }

        // Read the backup file
        $sql = file_get_contents($filepath);

        // Split the SQL into statements
        $statements = explode(";\n", $sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        // Add a notification about the restore
        addNotification(
            'Database Restored',
            "Database was successfully restored from backup: $filename",
            'success',
            'normal'
        );

        return [
            'success' => true,
            'message' => 'Database restored successfully from ' . $filename
        ];
    } catch (PDOException $e) {
        error_log("Restore failed: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Delete backup file
function deleteBackup($filename) {
    checkAdminSession();

    $backup_dir = __DIR__ . '/../backups/';
    $filepath = $backup_dir . basename($filename);

    // Security check: ensure file exists and is in the backup directory
    if (!file_exists($filepath) || strpos(realpath($filepath), realpath($backup_dir)) !== 0) {
        return [
            'success' => false,
            'error' => 'Backup file not found'
        ];
    }

    if (unlink($filepath)) {
        return [
            'success' => true,
            'message' => 'Backup file deleted successfully'
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Could not delete backup file'
        ];
    }
}

// Show backup management page
function showBackupPage() {
    checkAdminSession();

    $backups = getBackups();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Backup & Restore - Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .backup-section {
                background: white;
                padding: 30px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                margin-bottom: 30px;
            }

            .section-title {
                font-size: 1.4em;
                color: var(--primary-color);
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #eee;
            }

            .backup-actions {
                display: flex;
                gap: 15px;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }

            .btn {
                padding: 12px 25px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1em;
                font-weight: 500;
                transition: var(--transition);
                text-decoration: none;
                display: inline-block;
            }

            .create-backup-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
            }

            .create-backup-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(76, 201, 240, 0.4);
            }

            .backup-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            .backup-table th, .backup-table td {
                padding: 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
            }

            .backup-table th {
                background-color: #f8f9fa;
                font-weight: 600;
                color: #495057;
            }

            .backup-table tr:hover {
                background-color: #f0f5ff;
            }

            .action-buttons {
                display: flex;
                gap: 10px;
            }

            .action-btn {
                padding: 8px 15px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 0.9em;
                transition: var(--transition);
            }

            .download-btn {
                background: linear-gradient(to right, var(--info-color), #4361ee);
                color: white;
            }

            .restore-btn {
                background: linear-gradient(to right, var(--warning-color), #f8961e);
                color: white;
            }

            .delete-btn {
                background: linear-gradient(to right, var(--danger-color), #e63946);
                color: white;
            }

            .action-btn:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }

            .confirmation-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                align-items: center;
                justify-content: center;
            }

            .modal-content {
                background: white;
                padding: 30px;
                border-radius: var(--border-radius);
                width: 90%;
                max-width: 500px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            }

            .modal-header {
                margin-bottom: 20px;
            }

            .modal-actions {
                display: flex;
                gap: 10px;
                margin-top: 20px;
                justify-content: flex-end;
            }

            .cancel-btn {
                background: #6c757d;
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Backup & Restore</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="backup-section">
            <h2 class="section-title">Create New Backup</h2>
            <div class="backup-actions">
                <button class="btn create-backup-btn" onclick="createBackup()">Create New Backup</button>
            </div>
            <p>This will create a complete backup of the database including users, activities, recordings, and notifications.</p>
        </div>

        <div class="backup-section">
            <h2 class="section-title">Available Backups</h2>
            <?php if (count($backups) > 0): ?>
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><?= htmlspecialchars($backup['filename']) ?></td>
                                <td><?= formatFileSize($backup['size']) ?></td>
                                <td><?= $backup['modified'] ?></td>
                                <td class="action-buttons">
                                    <button class="action-btn download-btn" onclick="downloadBackup('<?= urlencode($backup['filename']) ?>')">Download</button>
                                    <button class="action-btn restore-btn" onclick="confirmRestore('<?= urlencode($backup['filename']) ?>')">Restore</button>
                                    <button class="action-btn delete-btn" onclick="confirmDelete('<?= urlencode($backup['filename']) ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No backups found. Create your first backup using the button above.</p>
            <?php endif; ?>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirmationModal" class="confirmation-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Confirmation</h3>
                    <p id="modalMessage">Are you sure you want to proceed?</p>
                </div>
                <div class="modal-actions">
                    <button class="btn cancel-btn" onclick="closeModal()">Cancel</button>
                    <button id="confirmButton" class="btn create-backup-btn">Confirm</button>
                </div>
            </div>
        </div>

        <script>
            function createBackup() {
                if (confirm('Are you sure you want to create a new backup? This may take a few moments.')) {
                    window.location.href = '?action=create_backup';
                }
            }

            function downloadBackup(filename) {
                window.location.href = '?action=download_backup&file=' + filename;
            }

            function confirmRestore(filename) {
                document.getElementById('modalTitle').textContent = 'Restore Database';
                document.getElementById('modalMessage').innerHTML = 'Are you sure you want to restore from backup: <strong>' + decodeURIComponent(filename) + '</strong>?<br><br><span style="color: red;">WARNING: This will overwrite all current data!</span>';

                document.getElementById('confirmButton').onclick = function() {
                    window.location.href = '?action=restore_backup&file=' + filename;
                };

                document.getElementById('confirmationModal').style.display = 'flex';
            }

            function confirmDelete(filename) {
                document.getElementById('modalTitle').textContent = 'Delete Backup';
                document.getElementById('modalMessage').textContent = 'Are you sure you want to delete backup: ' + decodeURIComponent(filename) + '?';

                document.getElementById('confirmButton').onclick = function() {
                    window.location.href = '?action=delete_backup&file=' + filename;
                };

                document.getElementById('confirmationModal').style.display = 'flex';
            }

            function closeModal() {
                document.getElementById('confirmationModal').style.display = 'none';
            }

            // Close modal if clicking outside of it
            window.onclick = function(event) {
                const modal = document.getElementById('confirmationModal');
                if (event.target === modal) {
                    closeModal();
                }
            }
        </script>
    </body>
    </html>
    <?php
}

// Format file size for display
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Handle create backup request
function handleCreateBackup() {
    checkAdminSession();

    $result = createBackup();

    if ($result['success']) {
        addNotification(
            'Backup Created',
            "Database backup created successfully: {$result['filename']}",
            'success',
            'normal'
        );
        header('Location: ?action=backup&success=Backup created successfully');
    } else {
        header('Location: ?action=backup&error=' . urlencode($result['error']));
    }
    exit;
}

// Handle download backup request
function handleDownloadBackup() {
    checkAdminSession();

    $filename = $_GET['file'] ?? null;

    if ($filename) {
        downloadBackup(urldecode($filename));
    } else {
        header('Location: ?action=backup&error=No backup file specified');
        exit;
    }
}

// Handle restore backup request
function handleRestoreBackup() {
    checkAdminSession();

    $filename = $_GET['file'] ?? null;

    if ($filename) {
        $result = restoreFromBackup(urldecode($filename));

        if ($result['success']) {
            header('Location: ?action=backup&success=' . urlencode($result['message']));
        } else {
            header('Location: ?action=backup&error=' . urlencode($result['error']));
        }
    } else {
        header('Location: ?action=backup&error=No backup file specified');
    }
    exit;
}

// Handle delete backup request
function handleDeleteBackup() {
    checkAdminSession();

    $filename = $_GET['file'] ?? null;

    if ($filename) {
        $result = deleteBackup(urldecode($filename));

        if ($result['success']) {
            header('Location: ?action=backup&success=' . urlencode($result['message']));
        } else {
            header('Location: ?action=backup&error=' . urlencode($result['error']));
        }
    } else {
        header('Location: ?action=backup&error=No backup file specified');
    }
    exit;
}

// Get new uploads for a user since a specific time
function getNewUploads() {
    checkAdminSession();

    $userId = $_GET['user_id'] ?? null;
    $sinceTime = $_GET['since'] ?? null;

    if (!$userId || !$sinceTime) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing user_id or since parameter']);
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit;
        }

        // Query for new recordings since the specified time (only from today)
        $stmt = $pdo->prepare("
            SELECT w.*
            FROM web_images w
            WHERE w.user_id = ?
            AND w.type = 'recording'
            AND w.date = CURDATE()
            AND CONCAT(w.date, ' ', w.time) > ?
            ORDER BY w.date DESC, w.time DESC
        ");

        $stmt->execute([$userId, $sinceTime]);
        $newRecordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'new_uploads' => $newRecordings,
            'count' => count($newRecordings)
        ]);
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get the latest video for a user
function getLatestVideo() {
    checkAdminSession();

    $userId = $_GET['user_id'] ?? null;

    if (!$userId) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing user_id parameter']);
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection failed']);
            exit;
        }

        // Query for the latest recording from today
        $stmt = $pdo->prepare("
            SELECT w.*
            FROM web_images w
            WHERE w.user_id = ?
            AND w.type = 'recording'
            AND w.date = CURDATE()
            ORDER BY w.date DESC, w.time DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $latestVideo = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'latest_video' => $latestVideo,
            'has_video' => $latestVideo !== false
        ]);
    } catch(PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Show combine recordings page for a user
function showCombineRecordings() {
    checkAdminSession();

    $userId = $_GET['user_id'] ?? null;

    if (!$userId) {
        header('Location: ?action=dashboard&error=No user specified');
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            header('Location: ?action=dashboard&error=Database connection failed');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM salesrep WHERE ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: ?action=dashboard&error=User not found');
            exit;
        }
    } catch(PDOException $e) {
        header('Location: ?action=dashboard&error=Database error occurred');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Combine Recordings - <?= htmlspecialchars($user['Name']) ?> | Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .form-container {
                background: white;
                padding: 30px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                margin-bottom: 20px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #555;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 1em;
                transition: var(--transition);
            }

            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            }

            .submit-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
                border: none;
                padding: 14px 25px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 1.1em;
                font-weight: 500;
                width: 100%;
                transition: var(--transition);
            }

            .submit-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(76, 201, 240, 0.4);
            }

            .info-text {
                margin-top: 10px;
                color: #666;
                font-size: 0.9em;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Combine Recordings - <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['RepID']) ?>)</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="form-container">
            <?php if (isset($_GET['error'])): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="?action=generate_combined_video&user_id=<?= $userId ?>">
                <div class="form-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" required>
                </div>

                <div class="form-group">
                    <label for="start_hour">Start Time:</label>
                    <div style="display: flex; gap: 10px;">
                        <select id="start_hour" name="start_hour" required>
                            <option value="">Hour</option>
                            <?php for ($i = 0; $i < 24; $i++): ?>
                                <option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="align-self: center;">:</span>
                        <select id="start_minute" name="start_minute" required>
                            <option value="">Minute</option>
                            <?php for ($i = 0; $i < 60; $i += 5): ?>
                                <option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" required>
                </div>

                <div class="form-group">
                    <label for="end_hour">End Time:</label>
                    <div style="display: flex; gap: 10px;">
                        <select id="end_hour" name="end_hour" required>
                            <option value="">Hour</option>
                            <?php for ($i = 0; $i < 24; $i++): ?>
                                <option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="align-self: center;">:</span>
                        <select id="end_minute" name="end_minute" required>
                            <option value="">Minute</option>
                            <?php for ($i = 0; $i < 60; $i += 5): ?>
                                <option value="<?= sprintf('%02d', $i) ?>"><?= sprintf('%02d', $i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="video_format">Video Format:</label>
                    <select id="video_format" name="video_format">
                        <option value="webm">WebM</option>
                        <option value="mp4">MP4</option>
                    </select>
                </div>

                <button type="submit" class="submit-btn">Generate Combined Video</button>

                <div class="info-text">
                    Select a date and time range to combine all recordings from this user into a single video file.
                </div>
            </form>
        </div>
    </body>
    </html>
    <?php
}

// Generate combined video from selected date/time range
function generateCombinedVideo() {
    checkAdminSession();

    $userId = $_GET['user_id'] ?? null;
    $startDate = $_POST['start_date'] ?? null;
    $startHour = $_POST['start_hour'] ?? null;
    $startMinute = $_POST['start_minute'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $endHour = $_POST['end_hour'] ?? null;
    $endMinute = $_POST['end_minute'] ?? null;
    $format = $_POST['video_format'] ?? 'webm';

    // Combine hour and minute into time format
    $startTime = ($startHour && $startMinute) ? $startHour . ':' . $startMinute . ':00' : null;
    $endTime = ($endHour && $endMinute) ? $endHour . ':' . $endMinute . ':00' : null;

    if (!$userId || !$startDate || !$startHour || !$startMinute || !$endDate || !$endHour || !$endMinute) {
        header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=Missing required parameters');
        exit;
    }

    // Validate date/time inputs
    $startDateTime = $startDate . ' ' . $startTime;
    $endDateTime = $endDate . ' ' . $endTime;

    if (strtotime($startDateTime) > strtotime($endDateTime)) {
        header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=Start time must be before end time');
        exit;
    }

    // Optionally, you can remove the future date restriction if you want to allow future dates
    // Or you can keep it but only check if start is after end, not if they're in the future
    $now = new DateTime();
    $start = new DateTime($startDateTime);
    $end = new DateTime($endDateTime);

    // Only check if end date is before start date, not if they're in the future
    // (Commenting out the future date restriction)
    // if ($start > $now || $end > $now) {
    //     header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=Cannot select future dates');
    //     exit;
    // }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=Database connection failed');
            exit;
        }

        // Get user info
        $stmt = $pdo->prepare("SELECT * FROM salesrep WHERE ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: ?action=dashboard&error=User not found');
            exit;
        }

        // Get recordings in the specified date/time range using a simpler approach
        $stmt = $pdo->prepare("
            SELECT w.*
            FROM web_images w
            WHERE w.user_id = ?
            AND w.type = 'recording'
            AND CONCAT(w.date, ' ', w.time) >= ?
            AND CONCAT(w.date, ' ', w.time) <= ?
            ORDER BY w.date ASC, w.time ASC
        ");
        $stmt->execute([
            $userId,
            $startDateTime,
            $endDateTime
        ]);

        $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no results, run a debug query to see what's available
        if (empty($recordings)) {
            $debugStmt = $pdo->prepare("
                SELECT w.*
                FROM web_images w
                WHERE w.user_id = ?
                AND w.type = 'recording'
                ORDER BY w.date DESC, w.time DESC
                LIMIT 10
            ");
            $debugStmt->execute([$userId]);
            $debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Debug - Available recordings for user " . $userId . ": " . count($debugResults));
            foreach ($debugResults as $rec) {
                error_log("Debug - Recording: " . $rec['date'] . " " . $rec['time'] . " - " . $rec['imgName']);
            }

            error_log("Debug - Searching for: " . $startDate . " " . $startTime . " to " . $endDate . " " . $endTime);
        }

        if (empty($recordings)) {
            header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=No valid recordings found in the specified time range');
            exit;
        }

        // Create a temporary directory for the combined video
        $tempDir = __DIR__ . '/../temp/';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Generate a unique filename for the combined video
        $timestamp = date('Y-m-d_H-i-s');
        $combinedFileName = "combined_" . $user['RepID'] . "_" . $timestamp . "." . $format;
        $combinedFilePath = $tempDir . $combinedFileName;

        // Create a temporary directory for input files
        $inputDir = $tempDir . 'inputs_' . uniqid() . '/';
        mkdir($inputDir, 0755, true);

        // Array to hold paths of video files to combine
        $inputFiles = [];

        // Process each recording in the range
        foreach ($recordings as $recording) {
            // First try the exact filename
            $sourcePath = __DIR__ . '/../uploads/' . $recording['imgName'];

            // If the exact file doesn't exist, search for files ending with the requested name
            if (!file_exists($sourcePath)) {
                $uploadsDir = __DIR__ . '/../uploads/';
                if (is_dir($uploadsDir)) {
                    $files = scandir($uploadsDir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            // Check if the file ends with the requested filename
                            if (preg_match('/' . preg_quote($recording['imgName'], '/') . '$/', $file)) {
                                $sourcePath = $uploadsDir . $file;
                                break;
                            }
                        }
                    }
                }
            }

            // Verify the source file exists
            if (file_exists($sourcePath)) {
                // Copy the file to the input directory with a sequential name
                $inputFile = $inputDir . 'input_' . sprintf('%03d', count($inputFiles)) . '.tmp';
                copy($sourcePath, $inputFile);
                $inputFiles[] = $inputFile;
            } else {
                error_log("Source file not found: " . $sourcePath);
            }
        }

        if (empty($inputFiles)) {
            // No valid input files found
            header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=No valid recordings found in the specified time range');
            exit;
        }

        // Create a text file listing all input files for FFmpeg
        $listFile = $inputDir . 'file_list.txt';
        $listContent = '';
        foreach ($inputFiles as $file) {
            // Escape special characters in file path for FFmpeg
            $escapedFile = str_replace("'", "'\\''", $file);
            $listContent .= "file '" . $escapedFile . "'\n";
        }
        file_put_contents($listFile, $listContent);

        // Build the FFmpeg command to concatenate videos
        // Using absolute path for FFmpeg if it's in a specific location
        $ffmpegCmd = 'ffmpeg -y -f concat -safe 0 -i "' . $listFile . '" -c copy -avoid_negative_ts make_zero "' . $combinedFilePath . '" 2>&1';

        // Execute the FFmpeg command
        $output = [];
        $returnCode = 0;
        exec($ffmpegCmd, $output, $returnCode);

        // Log the command and output for debugging
        error_log("FFmpeg command: " . $ffmpegCmd);
        error_log("FFmpeg return code: " . $returnCode);
        error_log("FFmpeg output: " . implode("\n", $output));

        // If the first method fails, try with a different approach
        if ($returnCode !== 0) {
            error_log("FFmpeg concat failed, trying alternative method");

            // Alternative method: use a temporary file list with absolute paths
            $altListFile = $inputDir . 'alt_file_list.txt';
            $altListContent = '';
            foreach ($inputFiles as $file) {
                $altListContent .= "file '" . realpath($file) . "'\n";
            }
            file_put_contents($altListFile, $altListContent);

            $altFfmpegCmd = 'ffmpeg -y -f concat -safe 0 -i "' . $altListFile . '" -c:v libx264 -c:a aac -strict experimental "' . $combinedFilePath . '" 2>&1';
            exec($altFfmpegCmd, $output, $returnCode);

            error_log("Alternative FFmpeg command: " . $altFfmpegCmd);
            error_log("Alternative FFmpeg return code: " . $returnCode);
            error_log("Alternative FFmpeg output: " . implode("\n", $output));

            // Clean up alternative list file
            if (file_exists($altListFile)) {
                unlink($altListFile);
            }
        }

        // Clean up temporary files
        foreach ($inputFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (file_exists($listFile)) {
            unlink($listFile);
        }
        if (is_dir($inputDir)) {
            rmdir($inputDir);
        }

        if ($returnCode !== 0) {
            // FFmpeg failed
            if (file_exists($combinedFilePath)) {
                unlink($combinedFilePath);
            }
            header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=Video combination failed. FFmpeg error occurred.');
            exit;
        }

        // Redirect to the combined video player
        header('Location: ?action=watch_combined&file=' . urlencode($combinedFileName) . '&user_id=' . $userId . '&start=' . urlencode($startDateTime) . '&end=' . urlencode($endDateTime));
        exit;

    } catch(PDOException $e) {
        header('Location: ?action=combine_recordings&user_id=' . $userId . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Show combined video watching page
function showWatchCombined() {
    checkAdminSession();

    $fileName = $_GET['file'] ?? null;
    $userId = $_GET['user_id'] ?? null;
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    if (!$fileName || !$userId) {
        header('Location: ?action=dashboard&error=Missing required parameters');
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            header('Location: ?action=dashboard&error=Database connection failed');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM salesrep WHERE ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: ?action=dashboard&error=User not found');
            exit;
        }
    } catch(PDOException $e) {
        header('Location: ?action=dashboard&error=Database error occurred');
        exit;
    }

    // In a real implementation, we would serve the actual combined video file
    // For this demo, we'll simulate the video player
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Combined Video - <?= htmlspecialchars($user['Name']) ?> | Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .player-container {
                background: white;
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                margin-bottom: 20px;
            }

            .video-player {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                display: block;
            }

            .video-info {
                margin-top: 15px;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 5px;
                text-align: center;
            }

            .controls {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin-top: 15px;
            }

            .control-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-weight: 500;
                transition: var(--transition);
            }

            .play-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
            }

            .pause-btn {
                background: linear-gradient(to right, var(--warning-color), #f8961e);
                color: white;
            }

            .stop-btn {
                background: linear-gradient(to right, var(--danger-color), #e63946);
                color: white;
            }

            .status-indicator {
                text-align: center;
                padding: 10px;
                margin: 10px 0;
                border-radius: 5px;
            }

            .status-playing {
                background-color: #d4edda;
                color: #155724;
            }

            .status-paused {
                background-color: #fff3cd;
                color: #856404;
            }

            .status-stopped {
                background-color: #f8d7da;
                color: #721c24;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Combined Video - <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['RepID']) ?>)</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="player-container">
            <div id="statusIndicator" class="status-indicator status-stopped">
                Combined Video Player
            </div>

            <video id="videoPlayer" class="video-player" controls>
                <source src="?action=serve_combined_video&file=<?= urlencode($fileName) ?>" type="video/<?= pathinfo($fileName, PATHINFO_EXTENSION) ?>">
                Your browser does not support the video tag.
            </video>

            <div class="video-info">
                <div>Combined video for: <?= htmlspecialchars($user['Name']) ?></div>
                <div>Time Range: <?= htmlspecialchars($start) ?> to <?= htmlspecialchars($end) ?></div>
                <div>File: <?= htmlspecialchars($fileName) ?></div>
            </div>

            <div class="controls">
                <button id="playBtn" class="control-btn play-btn">Play</button>
                <button id="pauseBtn" class="control-btn pause-btn">Pause</button>
                <button id="stopBtn" class="control-btn stop-btn">Stop</button>
            </div>
        </div>

        <script>
            // Simulate video player functionality
            const videoPlayer = document.getElementById('videoPlayer');
            const playBtn = document.getElementById('playBtn');
            const pauseBtn = document.getElementById('pauseBtn');
            const stopBtn = document.getElementById('stopBtn');
            const statusIndicator = document.getElementById('statusIndicator');

            // In a real implementation, we would load the actual video file
            // For this demo, we'll simulate the video player

            let isPlaying = false;

            playBtn.addEventListener('click', () => {
                isPlaying = true;
                statusIndicator.className = 'status-indicator status-playing';
                statusIndicator.textContent = 'Playing combined video...';
            });

            pauseBtn.addEventListener('click', () => {
                isPlaying = false;
                statusIndicator.className = 'status-indicator status-paused';
                statusIndicator.textContent = 'Paused';
            });

            stopBtn.addEventListener('click', () => {
                isPlaying = false;
                statusIndicator.className = 'status-indicator status-stopped';
                statusIndicator.textContent = 'Stopped';
            });

            // Simulate video ended event
            setTimeout(() => {
                if (isPlaying) {
                    statusIndicator.className = 'status-indicator status-stopped';
                    statusIndicator.textContent = 'Video completed';
                    isPlaying = false;
                }
            }, 30000); // Simulate 30-second video
        </script>
    </body>
    </html>
    <?php
}

// Serve combined video file
function serveCombinedVideo() {
    checkAdminSession();

    $fileName = $_GET['file'] ?? null;

    if (!$fileName) {
        http_response_code(400);
        echo "No file specified.";
        exit;
    }

    // Sanitize filename to prevent directory traversal
    $fileName = basename($fileName);
    $filePath = __DIR__ . '/../temp/' . $fileName;

    // Verify the file exists and is in the temp directory
    if (!file_exists($filePath) || strpos(realpath($filePath), realpath(__DIR__ . '/../temp/')) !== 0) {
        http_response_code(404);
        echo "File not found.";
        exit;
    }

    // Determine the content type based on file extension
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

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
        default:
            $contentType = 'application/octet-stream';
            break;
    }

    // Set headers for video streaming
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($filePath));
    header('Accept-Ranges: none');

    // Read and output the file
    readfile($filePath);
    exit;
}

// Show live watching page for a user
function showLiveWatching() {
    checkAdminSession();

    $userId = $_GET['user_id'] ?? null;

    if (!$userId) {
        header('Location: ?action=dashboard&error=No user specified');
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            header('Location: ?action=dashboard&error=Database connection failed');
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM salesrep WHERE ID = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: ?action=dashboard&error=User not found');
            exit;
        }

        // Get all recordings for this user
        $recordings = getAllUserRecordings($userId);
    } catch(PDOException $e) {
        header('Location: ?action=dashboard&error=Database error occurred');
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Live Watching - <?= htmlspecialchars($user['Name']) ?> | Admin Dashboard</title>
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
                padding: 20px;
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
                border-radius: var(--border-radius);
                margin-bottom: 20px;
            }

            .header h1 {
                margin: 0;
                font-size: 1.5em;
                font-weight: 600;
            }

            .back-btn {
                background: linear-gradient(to right, var(--info-color), var(--primary-color));
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 30px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                font-weight: 500;
                transition: var(--transition);
            }

            .back-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 10px rgba(67, 97, 238, 0.4);
            }

            .player-container {
                background: white;
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                margin-bottom: 20px;
            }

            .video-player {
                width: 100%;
                max-width: 800px;
                margin: 0 auto;
                display: block;
            }

            .video-info {
                margin-top: 15px;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 5px;
                text-align: center;
            }

            .controls {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin-top: 15px;
            }

            .control-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-weight: 500;
                transition: var(--transition);
            }

            .play-btn {
                background: linear-gradient(to right, var(--success-color), #4895ef);
                color: white;
            }

            .pause-btn {
                background: linear-gradient(to right, var(--warning-color), #f8961e);
                color: white;
            }

            .stop-btn {
                background: linear-gradient(to right, var(--danger-color), #e63946);
                color: white;
            }

            .playlist {
                background: white;
                padding: 20px;
                border-radius: var(--border-radius);
                box-shadow: var(--card-shadow);
                margin-top: 20px;
            }

            .playlist h3 {
                margin-top: 0;
                color: var(--primary-color);
                border-bottom: 2px solid #f0f0f0;
                padding-bottom: 10px;
            }

            .playlist-items {
                max-height: 300px;
                overflow-y: auto;
            }

            .playlist-item {
                padding: 10px;
                border-bottom: 1px solid #eee;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .playlist-item:hover {
                background-color: #f0f5ff;
            }

            .playlist-item.active {
                background-color: #e6f0ff;
                font-weight: bold;
            }

            .status-indicator {
                text-align: center;
                padding: 10px;
                margin: 10px 0;
                border-radius: 5px;
            }

            .status-playing {
                background-color: #d4edda;
                color: #155724;
            }

            .status-paused {
                background-color: #fff3cd;
                color: #856404;
            }

            .status-stopped {
                background-color: #f8d7da;
                color: #721c24;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Live Watching - <?= htmlspecialchars($user['Name']) ?> (<?= htmlspecialchars($user['RepID']) ?>)</h1>
            <a href="?action=dashboard" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="player-container">
            <div id="statusIndicator" class="status-indicator status-stopped">
                Player Stopped - Click Play to start
            </div>

            <video id="videoPlayer" class="video-player" controls>
                <source src="" type="video/webm">
                Your browser does not support the video tag.
            </video>

            <div class="video-info">
                <div id="currentVideoInfo">No video selected</div>
                <div id="currentTime">--:-- / --:--</div>
            </div>

            <div class="controls">
                <button id="playBtn" class="control-btn play-btn">Play</button>
                <button id="pauseBtn" class="control-btn pause-btn">Pause</button>
                <button id="stopBtn" class="control-btn stop-btn">Stop</button>
            </div>
        </div>

        <div class="playlist">
            <h3>Recording Playlist</h3>
            <div id="playlistItems" class="playlist-items">
                <?php if (count($recordings) > 0): ?>
                    <?php foreach ($recordings as $index => $recording): ?>
                        <div class="playlist-item" data-index="<?= $index ?>" data-filename="<?= htmlspecialchars($recording['imgName']) ?>">
                            <strong><?= htmlspecialchars($recording['imgName']) ?></strong><br>
                            <small>Date: <?= $recording['date'] ?> | Time: <?= $recording['time'] ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #6c757d;">
                        No recordings found for this user
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            // Global variables
            let playlist = <?php echo json_encode($recordings); ?>;
            let currentIndex = 0;
            let isPlaying = false;

            // DOM elements
            const videoPlayer = document.getElementById('videoPlayer');
            const playBtn = document.getElementById('playBtn');
            const pauseBtn = document.getElementById('pauseBtn');
            const stopBtn = document.getElementById('stopBtn');
            const statusIndicator = document.getElementById('statusIndicator');
            const currentVideoInfo = document.getElementById('currentVideoInfo');
            const currentTimeDisplay = document.getElementById('currentTime');
            const playlistItems = document.getElementById('playlistItems');

            // Initialize player
            function initPlayer() {
                if (playlist.length > 0) {
                    // Start from the first video (newest) which is at index 0
                    currentIndex = 0;
                    loadVideo(currentIndex);
                    updatePlaylistHighlight(currentIndex);

                    // Automatically start playing when the page loads
                    setTimeout(() => {
                        if (playlist.length > 0) {
                            videoPlayer.play().then(() => {
                                isPlaying = true;
                                statusIndicator.className = 'status-indicator status-playing';
                                statusIndicator.textContent = 'Playing: ' + playlist[currentIndex].imgName;

                                // Update button text to indicate it's playing
                                playBtn.textContent = 'Resume';
                            }).catch(e => {
                                console.log('Autoplay prevented: ', e);
                                statusIndicator.className = 'status-indicator status-paused';
                                statusIndicator.textContent = 'Autoplay blocked - click Play to start';
                            });
                        }
                    }, 500); // Small delay to ensure video is loaded

                    // Immediately check for the latest video to ensure we have the most recent one
                    setTimeout(() => {
                        checkForLatestVideo();
                    }, 2000); // Check after 2 seconds to allow initial load
                } else {
                    statusIndicator.className = 'status-indicator status-stopped';
                    statusIndicator.textContent = 'No recordings available';
                    currentVideoInfo.textContent = 'No recordings available';
                }
            }

            // Load video by index
            function loadVideo(index) {
                if (index < 0 || index >= playlist.length) {
                    console.log('Invalid index: ' + index);
                    return;
                }

                currentIndex = index;
                const recording = playlist[index];

                // Update video source
                const videoSrc = '?action=view&file=' + encodeURIComponent(recording.imgName);
                videoPlayer.src = videoSrc;

                // Update info display
                currentVideoInfo.innerHTML = '<strong>' + recording.imgName + '</strong><br>' +
                                           'Date: ' + recording.date + ' | Time: ' + recording.time;

                // Update playlist highlight
                updatePlaylistHighlight(index);

                // Update status
                if (isPlaying) {
                    statusIndicator.className = 'status-indicator status-playing';
                    statusIndicator.textContent = 'Playing: ' + recording.imgName;
                } else {
                    statusIndicator.className = 'status-indicator status-paused';
                    statusIndicator.textContent = 'Video loaded - Paused';
                }

                // Reset play button text based on current state
                if (isPlaying) {
                    playBtn.textContent = 'Resume';
                } else {
                    playBtn.textContent = 'Play';
                }
            }

            // Update playlist highlighting
            function updatePlaylistHighlight(index) {
                // Remove active class from all items
                document.querySelectorAll('.playlist-item').forEach(item => {
                    item.classList.remove('active');
                });

                // Add active class to current item
                const currentItem = document.querySelector(`.playlist-item[data-index="${index}"]`);
                if (currentItem) {
                    currentItem.classList.add('active');
                }
            }

            // Check for the latest video and update if needed
            let currentUserId = <?php echo $userId; ?>;
            let currentLatestVideo = playlist.length > 0 ? playlist[0] : null;
            let forceUpdateOnNextPlay = false; // Flag to force update on next play

            function checkForLatestVideo() {
                // Make an AJAX request to get the latest video
                fetch(`?action=get_latest_video&user_id=${currentUserId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.has_video) {
                            // Compare with the current latest video
                            const latestVideo = data.latest_video;

                            // Check if this is a new video by comparing date and time
                            if (!currentLatestVideo ||
                                latestVideo.date > currentLatestVideo.date ||
                                (latestVideo.date === currentLatestVideo.date && latestVideo.time > currentLatestVideo.time)) {

                                // This is a new latest video, update our reference
                                currentLatestVideo = latestVideo;

                                // Update the playlist array to put the new latest video first
                                const existingIndex = playlist.findIndex(v => v.ID === latestVideo.ID);
                                if (existingIndex === -1) {
                                    // New video not in playlist, add it to the beginning
                                    playlist.unshift(latestVideo);
                                } else if (existingIndex !== 0) {
                                    // Video exists but not at the beginning, move it
                                    playlist.splice(existingIndex, 1);
                                    playlist.unshift(latestVideo);
                                }

                                // Update the playlist UI
                                updatePlaylistUI();

                                // Set flag to force update on next play
                                forceUpdateOnNextPlay = true;

                                // Update status to notify user of new video
                                if (isPlaying) {
                                    statusIndicator.className = 'status-indicator status-playing';
                                    statusIndicator.textContent = 'New video available: ' + latestVideo.imgName + ' (will play next)';
                                } else {
                                    // If not playing, update immediately
                                    if (currentIndex === 0) {
                                        loadVideo(0);
                                        currentLatestVideo = playlist[0]; // Update reference
                                    }
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.log('Error checking for latest video:', error);
                    });
            }

            // Override the play button to check for latest video before playing
            const originalPlayBtn = playBtn.onclick;
            playBtn.onclick = function() {
                // Check for latest video before playing
                checkForLatestVideo();

                // If we have a forced update, load the latest video
                if (forceUpdateOnNextPlay) {
                    loadVideo(0);
                    forceUpdateOnNextPlay = false;
                }

                // Then execute the original play functionality
                if (playlist.length === 0) return;

                videoPlayer.play()
                    .then(() => {
                        isPlaying = true;
                        statusIndicator.className = 'status-indicator status-playing';
                        statusIndicator.textContent = 'Playing: ' + playlist[currentIndex].imgName;

                        // Update button text to indicate it's playing
                        playBtn.textContent = 'Resume';
                    })
                    .catch(e => {
                        console.log('Play failed: ', e);
                        statusIndicator.className = 'status-indicator status-paused';
                        statusIndicator.textContent = 'Playback failed - click Play again';
                    });
            };

            // Also update the video ended event to check for latest video
            videoPlayer.addEventListener('ended', () => {
                // Immediately check for latest video before playing next
                checkForLatestVideo();

                // Small delay to ensure the latest video is loaded
                setTimeout(() => {
                    // Always play the latest video when current one ends
                    loadVideo(0);

                    // If we were playing, continue playing the latest video
                    if (isPlaying) {
                        videoPlayer.play().catch(e => console.log('Autoplay prevented: ', e));
                    }
                }, 500); // Small delay to ensure data is updated
            });

            // Update the playlist UI
            function updatePlaylistUI() {
                const playlistContainer = document.getElementById('playlistItems');
                playlistContainer.innerHTML = '';

                playlist.forEach((recording, index) => {
                    const playlistItem = document.createElement('div');
                    playlistItem.className = 'playlist-item';
                    playlistItem.setAttribute('data-index', index);
                    playlistItem.setAttribute('data-filename', recording.imgName);
                    playlistItem.innerHTML = `
                        <strong>${recording.imgName}</strong><br>
                        <small>Date: ${recording.date} | Time: ${recording.time}</small>
                    `;
                    playlistContainer.appendChild(playlistItem);
                });

                // Update highlight
                updatePlaylistHighlight(currentIndex);
            }

            // Start checking for the latest video periodically
            setInterval(checkForLatestVideo, 5000); // Check every 5 seconds for faster updates

            // Add click event listener for playlist items
            playlistItems.addEventListener('click', (e) => {
                const playlistItem = e.target.closest('.playlist-item');
                if (playlistItem) {
                    const index = parseInt(playlistItem.getAttribute('data-index'));
                    if (!isNaN(index)) {
                        loadVideo(index);

                        // Auto-play if currently playing
                        if (isPlaying) {
                            videoPlayer.play().then(() => {
                                statusIndicator.className = 'status-indicator status-playing';
                                statusIndicator.textContent = 'Playing: ' + playlist[currentIndex].imgName;

                                // Update button text to indicate it's playing
                                playBtn.textContent = 'Resume';
                            }).catch(e => console.log('Autoplay prevented: ', e));
                        }
                    }
                }
            });

            // Play the same video again (always play the latest)
            function playNextVideo() {
                // Always stay on the first video (which should be the latest)
                // This ensures we keep playing the latest video
                loadVideo(0);

                // Automatically play the video if currently playing
                if (isPlaying) {
                    // Wait a moment for the video to load before playing
                    setTimeout(() => {
                        videoPlayer.play().catch(e => console.log('Autoplay prevented: ', e));
                    }, 100);
                }
            }

            // Event listeners
            playBtn.addEventListener('click', () => {
                if (playlist.length === 0) return;

                videoPlayer.play()
                    .then(() => {
                        isPlaying = true;
                        statusIndicator.className = 'status-indicator status-playing';
                        statusIndicator.textContent = 'Playing: ' + playlist[currentIndex].imgName;

                        // Update button text to indicate it's playing
                        playBtn.textContent = 'Resume';
                    })
                    .catch(e => {
                        console.log('Play failed: ', e);
                        statusIndicator.className = 'status-indicator status-paused';
                        statusIndicator.textContent = 'Playback failed - click Play again';
                    });
            });

            pauseBtn.addEventListener('click', () => {
                videoPlayer.pause();
                isPlaying = false;
                statusIndicator.className = 'status-indicator status-paused';
                statusIndicator.textContent = 'Paused: ' + playlist[currentIndex].imgName;

                // Update play button text to indicate it can resume
                playBtn.textContent = 'Resume';
            });

            stopBtn.addEventListener('click', () => {
                videoPlayer.pause();
                videoPlayer.currentTime = 0;
                isPlaying = false;
                statusIndicator.className = 'status-indicator status-stopped';
                statusIndicator.textContent = 'Stopped - Click Play to resume';

                // Update play button text to indicate it can start
                playBtn.textContent = 'Play';
            });

            // Video ended event - automatically play next video
            videoPlayer.addEventListener('ended', () => {
                // Always play the next video in sequence, with seamless looping
                playNextVideo();
            });

            // Time update event - update time display
            videoPlayer.addEventListener('timeupdate', () => {
                const current = Math.floor(videoPlayer.currentTime);
                const duration = Math.floor(videoPlayer.duration) || 0;

                const currentFormatted = new Date(current * 1000).toISOString().substr(14, 5);
                const durationFormatted = new Date(duration * 1000).toISOString().substr(14, 5);

                currentTimeDisplay.textContent = `${currentFormatted} / ${durationFormatted}`;
            });

            // Click on playlist item to play that video
            playlistItems.addEventListener('click', (e) => {
                const playlistItem = e.target.closest('.playlist-item');
                if (playlistItem) {
                    const index = parseInt(playlistItem.getAttribute('data-index'));
                    if (!isNaN(index)) {
                        loadVideo(index);

                        // Auto-play if currently playing
                        if (isPlaying) {
                            videoPlayer.play().then(() => {
                                statusIndicator.className = 'status-indicator status-playing';
                                statusIndicator.textContent = 'Playing: ' + playlist[currentIndex].imgName;

                                // Update button text to indicate it's playing
                                playBtn.textContent = 'Resume';
                            }).catch(e => console.log('Autoplay prevented: ', e));
                        }
                    }
                }
            });

            // Initialize the player when page loads
            window.addEventListener('DOMContentLoaded', initPlayer);
        </script>
    </body>
    </html>
    <?php
}
?>