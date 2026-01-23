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
    WHERE ua.rDateTime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY s.ID
    ORDER BY last_activity DESC
");
$stmt->execute();
$active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent recordings
$stmt = $pdo->prepare("
    SELECT w.*, s.Name as user_name, s.RepID
    FROM web_images w
    LEFT JOIN salesrep s ON w.user_id = s.ID
    WHERE w.type = 'recording' AND w.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY w.date DESC, w.time DESC
    LIMIT 50
");
$stmt->execute();
$recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent user activities
$stmt = $pdo->prepare("
    SELECT ua.*, s.Name as user_name, s.RepID
    FROM user_activity ua
    LEFT JOIN salesrep s ON ua.salesrepTb = s.ID
    ORDER BY ua.rDateTime DESC
    LIMIT 50
");
$stmt->execute();
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
    ORDER BY s.ID DESC
");
$stmt->execute();
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get date range for filtering
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Remote Work Monitoring</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        
        .header {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.5em;
        }
        
        .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background-color: #c82333;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #555;
            font-size: 1.1em;
        }
        
        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        
        .section {
            background-color: white;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background-color: #007bff;
            color: white;
            padding: 15px 20px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-header h2 {
            margin: 0;
            font-size: 1.2em;
        }
        
        .filters {
            padding: 10px 20px;
            background-color: #f1f1f1;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
        }
        
        .filter-item label {
            font-size: 0.9em;
            margin-bottom: 3px;
            color: #555;
        }
        
        .filter-item input {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        
        .apply-filters {
            padding: 8px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            align-self: flex-end;
            margin-bottom: 5px;
        }
        
        .section-content {
            padding: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .recording-actions {
            display: flex;
            gap: 5px;
        }
        
        .view-btn, .download-btn, .edit-btn, .delete-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            color: white;
        }
        
        .view-btn {
            background-color: #28a745;
        }
        
        .download-btn {
            background-color: #17a2b8;
        }
        
        .edit-btn {
            background-color: #ffc107;
            color: #212529;
        }
        
        .delete-btn {
            background-color: #dc3545;
        }
        
        .view-btn:hover, .download-btn:hover, .edit-btn:hover, .delete-btn:hover {
            opacity: 0.9;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .activity-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
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
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        
        .tab.active {
            background-color: #007bff;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .user-status-active {
            color: green;
            font-weight: bold;
        }
        
        .user-status-inactive {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Dashboard</h1>
        <a href="./admin_logout.php" class="logout-btn">Logout</a>
    </div>
    
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Today</h3>
                <div class="number"><?php echo $active_today; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Recordings</h3>
                <div class="number"><?php echo $total_recordings; ?></div>
            </div>
            <div class="stat-card">
                <h3>Recordings Today</h3>
                <div class="number"><?php echo $recordings_today; ?></div>
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
                    <h2>Active Users (Last 24 Hours)</h2>
                </div>
                <div class="section-content">
                    <?php if (count($active_users) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Rep ID</th>
                                    <th>Name</th>
                                    <th>Branch ID</th>
                                    <th>Last Activity</th>
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
                    <?php else: ?>
                        <div class="no-data">No active users in the last 24 hours</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="recordings" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2>Recent Recordings (Last 7 Days)</h2>
                </div>
                <div class="section-content">
                    <?php if (count($recordings) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Rep ID</th>
                                    <th>Recording Name</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>
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
                    <?php else: ?>
                        <div class="no-data">No recordings found in the last 7 days</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="activities" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2>Recent User Activities</h2>
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
                </div>
                <div class="section-content">
                    <?php if (count($user_activities) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Rep ID</th>
                                    <th>Activity Type</th>
                                    <th>Date/Time</th>
                                    <th>Duration</th>
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
                    <?php else: ?>
                        <div class="no-data">No user activities found</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="all-users" class="tab-content">
            <div class="section">
                <div class="section-header">
                    <h2>All Users</h2>
                </div>
                <div class="section-content">
                    <?php if (count($all_users) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Rep ID</th>
                                    <th>Name</th>
                                    <th>Branch ID</th>
                                    <th>Email</th>
                                    <th>Join Date</th>
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
        }
        
        function applyFilters() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;

            // Reload the page with the filter parameters
            window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>