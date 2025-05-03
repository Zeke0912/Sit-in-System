<?php
session_start();
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Debug mode
$debug = false;  // Set to true to enable debugging
if ($debug) {
    // Enable error reporting
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    // Test query for today's records
    $testSql = "SELECT COUNT(*) as count, 
                DATE(end_time) as end_date,
                DATE('$today') as today_date
                FROM sit_in_requests 
                WHERE is_active = 0 
                GROUP BY DATE(end_time)
                ORDER BY end_date DESC
                LIMIT 10";
    $testResult = $conn->query($testSql);
    
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px; border-radius: 5px;'>";
    echo "<h3>Debug Information:</h3>";
    echo "Today's date: $today<br>";
    echo "Records by date:<br>";
    echo "<ul>";
    while ($row = $testResult->fetch_assoc()) {
        echo "<li>Date: {$row['end_date']} - Count: {$row['count']}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Debug information - add this temporarily to see what's happening
date_default_timezone_set('Asia/Manila'); // Or your correct timezone
$today = date('Y-m-d');
$todayStart = $today . ' 00:00:00';
$todayEnd = $today . ' 23:59:59';

// For debugging only - you can remove this later
error_log("Today's date: $today, Start: $todayStart, End: $todayEnd");

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit();
}

// Default entries per page
$entriesPerPage = isset($_GET['entries']) ? (int)$_GET['entries'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $entriesPerPage;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$searchCondition = '';
if (!empty($search)) {
    $searchCondition = " AND (u.firstname LIKE '%$search%' OR 
                              u.lastname LIKE '%$search%' OR 
                              r.purpose LIKE '%$search%' OR 
                              u.idno LIKE '%$search%' OR
                              s.lab_number LIKE '%$search%')";
}

// Get total records count for pagination - only Today's records
$countSql = "SELECT COUNT(*) as total FROM sit_in_requests r
             JOIN users u ON r.student_id = u.idno
             JOIN subjects s ON r.subject_id = s.id
             WHERE r.is_active = 0 AND DATE(r.end_time) = '$today'" . $searchCondition;
$countResult = $conn->query($countSql);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $entriesPerPage);

// Fetch completed sit-in sessions - only Today's records
$sql = "SELECT r.id, r.student_id, r.subject_id, r.purpose, r.start_time, r.end_time, r.pc_number,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.is_active = 0 AND DATE(r.end_time) = '$today'" . $searchCondition . "
        ORDER BY r.end_time DESC
        LIMIT $offset, $entriesPerPage";

$result = $conn->query($sql);
if (!$result) {
    echo "Error in query: " . $conn->error;
    exit;
}

// Get purpose statistics for pie chart - only Today's records
$purposeStatsSql = "SELECT r.purpose, COUNT(*) as count
                    FROM sit_in_requests r
                    WHERE r.is_active = 0 AND DATE(r.end_time) = '$today'
                    GROUP BY r.purpose
                    ORDER BY count DESC";
$purposeStatsResult = $conn->query($purposeStatsSql);
$purposeData = [];
while ($row = $purposeStatsResult->fetch_assoc()) {
    $purposeData[$row['purpose']] = (int)$row['count'];
}

// Get lab statistics for pie chart - only Today's records
$labStatsSql = "SELECT s.lab_number, COUNT(*) as count
                FROM sit_in_requests r
                JOIN subjects s ON r.subject_id = s.id
                WHERE r.is_active = 0 AND DATE(r.end_time) = '$today'
                GROUP BY s.lab_number
                ORDER BY count DESC";
$labStatsResult = $conn->query($labStatsSql);
$labData = [];
while ($row = $labStatsResult->fetch_assoc()) {
    $labData[$row['lab_number']] = (int)$row['count'];
}

// Get total sit-in count for today
$totalSitIns = $totalRecords;

// Get top programming language for today
$topPurposeSql = "SELECT purpose, COUNT(*) as count 
                FROM sit_in_requests 
                WHERE is_active = 0 AND DATE(end_time) = '$today'
                GROUP BY purpose 
                ORDER BY count DESC 
                LIMIT 1";
$topPurposeResult = $conn->query($topPurposeSql);
$topPurpose = $topPurposeResult->num_rows > 0 ? $topPurposeResult->fetch_assoc()['purpose'] : 'None';

// Get most used lab for today
$topLabSql = "SELECT s.lab_number, COUNT(*) as count 
             FROM sit_in_requests r
             JOIN subjects s ON r.subject_id = s.id
             WHERE r.is_active = 0 AND DATE(r.end_time) = '$today'
             GROUP BY s.lab_number 
             ORDER BY count DESC 
             LIMIT 1";
$topLabResult = $conn->query($topLabSql);
$topLab = $topLabResult->num_rows > 0 ? $topLabResult->fetch_assoc()['lab_number'] : 'None';

// Get average session duration for today
$avgDurationSql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration 
                  FROM sit_in_requests 
                  WHERE is_active = 0 AND DATE(end_time) = '$today' AND end_time IS NOT NULL";
$avgDurationResult = $conn->query($avgDurationSql);
$avgDurationMinutes = $avgDurationResult->fetch_assoc()['avg_duration'];
$avgDurationFormatted = floor($avgDurationMinutes / 60) . 'h ' . ($avgDurationMinutes % 60) . 'm';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Sit-in Records</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            position: relative;
            display: flex;
        }

        /* Left Sidebar Navigation */
        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: #2c3e50;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: #ecf0f1;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            color: #ecf0f1;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            color: #bdc3c7;
            font-size: 12px;
        }
        
        .nav-links {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .nav-links a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 20px;
            transition: background-color 0.3s, border-left 0.3s;
            border-left: 3px solid transparent;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(26, 188, 156, 0.2);
            border-left: 3px solid #1abc9c;
        }
        
        .nav-links a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .logout-container {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .logout-container a {
            display: block;
            padding: 10px;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .logout-container a:hover {
            background-color: #c0392b;
        }
        
        /* Toggle button for mobile */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #2c3e50;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            z-index: 1001;
            cursor: pointer;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            transition: margin-left 0.3s, width 0.3s;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .content {
            width: 100%;
            text-align: center;
            flex: 1;
            padding-bottom: 20px;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-250px);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            body.sidebar-active .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
            
            body.sidebar-active .sidebar-toggle {
                left: 265px;
            }
        }
        
        @media (max-width: 768px) {
            .chart-container {
                width: 100%;
                margin-bottom: 20px;
            }
            
            .table-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .search-control {
                width: 100%;
            }
            
            .search-control input {
                width: 100%;
            }
            
            .stat-card {
                min-width: 45%;
            }
            
            body.sidebar-active .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        /* Stats Cards */
        .stats-overview {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
            color: #2980b9;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Chart Containers */
        .charts-container {
            display: flex;
            justify-content: space-around;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .chart-container {
            width: 45%;
            min-width: 300px;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
        }
        
        .chart-title {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        
        /* Date Display */
        .date-display {
            font-size: 1.2rem;
            color: #27ae60;
            margin-bottom: 20px;
            font-weight: bold;
        }
        
        /* Table Styles */
        .records-table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            overflow-x: auto;
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .records-table th,
        .records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .records-table th {
            background-color: #2980b9;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        
        .records-table th:hover {
            background-color: #3498db;
        }
        
        .records-table tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Pagination controls */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        
        .pagination {
            display: flex;
            list-style: none;
        }
        
        .pagination li {
            margin: 0 5px;
        }
        
        .pagination a {
            display: block;
            padding: 8px 12px;
            background-color: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background-color: #3498db;
        }
        
        /* Search and entries control */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .entries-control {
            display: flex;
            align-items: center;
        }
        
        .entries-control select {
            margin: 0 5px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .search-control {
            display: flex;
            align-items: center;
        }
        
        .search-control input {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-left: 5px;
            width: 200px;
        }

        /* Points System */
        .points-control {
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
        }
        
        .points-input {
            width: 60px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        .award-btn {
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            width: 30px;
            height: 30px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .award-btn:hover {
            background-color: #2ecc71;
        }
        
        .points-control.awarded .points-input,
        .points-control.awarded .award-btn {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .points-display {
            background-color: #3498db;
            color: white;
            border-radius: 4px;
            padding: 6px 10px;
            font-weight: bold;
            text-align: center;
        }
        
        /* Point Award Modal */
        .point-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1010;
            justify-content: center;
            align-items: center;
        }
        
        .point-modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        .point-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .point-message {
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .point-award {
            font-weight: bold;
            color: #27ae60;
        }
        
        .point-modal-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .point-modal-btn:hover {
            background-color: #2980b9;
        }
        
        /* Stats Additions */
        .point-info {
            display: inline-block;
            margin-left: 5px;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            margin-top: 30px;
            width: 100%;
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Left Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Sit-in Monitoring</h3>
            <p>Admin Panel</p>
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="todays_sit_in_records.php" class="active"><i class="fas fa-calendar-day"></i> Today's Records</a>
            <a href="approved_sit_in_sessions.php"><i class="fas fa-history"></i> Sit in Records</a>
            <a href="active_sitin.php"><i class="fas fa-user-clock"></i> Active Sit-ins</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Sit-in Reports</a>
            <a href="feedback_reports.php"><i class="fas fa-comments"></i> Feedback Reports</a>
            <a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a>
            <a href="add_subject.php"><i class="fas fa-book"></i> Add Subject</a>
            <a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
        </div>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content">
            <h1>Today's Sit-in Records</h1>
            
            <!-- Today's Date Display -->
            <div class="date-display">
                <?php echo date('F d, Y'); ?> <span style="font-size: 0.8em;">(<?php echo $totalSitIns; ?> session<?php echo $totalSitIns != 1 ? 's' : ''; ?> today)</span>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-container">
                <!-- Programming Languages Chart -->
                <div class="chart-container">
                    <div class="chart-title">Today's Programming Languages</div>
                    <canvas id="purposeChart"></canvas>
                </div>
                
                <!-- Labs Chart -->
                <div class="chart-container">
                    <div class="chart-title">Today's Laboratory Usage</div>
                    <canvas id="labChart"></canvas>
                </div>
            </div>
            
            <!-- Table Section -->
            <div class="records-table-container">
                <!-- Table Controls -->
                <div class="table-controls">
                    <div class="entries-control">
                        <label for="entriesPerPage">Show</label>
                        <select id="entriesPerPage" onchange="changeEntries(this.value)">
                            <option value="10" <?php echo $entriesPerPage == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $entriesPerPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $entriesPerPage == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $entriesPerPage == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                        <span>entries per page</span>
                    </div>
                    
                    <div class="search-control">
                        <label for="search">Search:</label>
                        <input type="text" id="search" value="<?php echo htmlspecialchars($search); ?>" onkeyup="if(event.keyCode === 13) searchRecords()">
                    </div>
                </div>
                
                <!-- Records Table -->
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Sit-in Number</th>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Lab</th>
                            <th>PC#</th>
                            <th>Login</th>
                            <th>Logout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            $counter = $offset + 1;
                            while($row = $result->fetch_assoc()) {
                                $fullName = $row['firstname'] . ' ' . $row['lastname'];
                                $startTime = date('h:i:sa', strtotime($row['start_time']));
                                $endTime = date('h:i:sa', strtotime($row['end_time']));
                                
                                echo "<tr>
                                    <td>{$counter}</td>
                                    <td>{$row['student_id']}</td>
                                    <td>{$fullName}</td>
                                    <td>{$row['purpose']}</td>
                                    <td>{$row['lab_number']}</td>
                                    <td>" . ($row['pc_number'] ? $row['pc_number'] : 'N/A') . "</td>
                                    <td>{$startTime}</td>
                                    <td>{$endTime}</td>
                                </tr>";
                                $counter++;
                            }
                        } else {
                            echo "<tr><td colspan='8' style='text-align:center;'>No records found for today</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination-container">
                    <div>
                        Showing <?php echo min($totalRecords, $offset + 1); ?> to <?php echo min($totalRecords, $offset + $entriesPerPage); ?> of <?php echo $totalRecords; ?> entries
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li><a href="?page=1&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>">First</a></li>
                            <li><a href="?page=<?php echo $page-1; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>">Previous</a></li>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li><a href="?page=<?php echo $i; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li><a href="?page=<?php echo $page+1; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>">Next</a></li>
                            <li><a href="?page=<?php echo $totalPages; ?>&entries=<?php echo $entriesPerPage; ?>&search=<?php echo urlencode($search); ?>">Last</a></li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
        </footer>
    </div>

    <script>
        // Sidebar Toggle Functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.body.classList.toggle('sidebar-active');
        });

        // Chart.js setup for Purpose Pie Chart
        const purposeCtx = document.getElementById('purposeChart').getContext('2d');
        const purposeData = <?php echo json_encode(array_values($purposeData)); ?>;
        const purposeLabels = <?php echo json_encode(array_keys($purposeData)); ?>;
        
        // Colors for purpose chart
        const purposeColors = [
            '#1abc9c', // PHP - Turquoise
            '#3498db', // C# - Blue
            '#e67e22', // Java - Orange 
            '#f1c40f', // ASP.Net - Yellow
            '#9b59b6', // Python - Purple
            '#34495e', // Other - Dark blue
            '#2ecc71', // C Programming - Green
            '#16a085', // Database - Dark Turquoise
            '#8e44ad', // Digital & Logic Design - Purple
            '#d35400', // Embedded Systems & IoT - Dark Orange
            '#c0392b', // System Integration & Architecture - Red
            '#27ae60', // Computer Application - Medium Green
            '#2980b9', // Project Management - Medium Blue
            '#f39c12', // IT Trends - Mustard
            '#e74c3c', // Technopreneurship - Light Red
            '#7f8c8d'  // Capstone - Gray
        ];
        
        new Chart(purposeCtx, {
            type: 'pie',
            data: {
                labels: purposeLabels,
                datasets: [{
                    data: purposeData,
                    backgroundColor: purposeColors,
                    borderWidth: 1,
                    borderColor: '#fff',
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.formattedValue;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((context.raw / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            }
        });
        
        // Chart.js setup for Lab Pie Chart
        const labCtx = document.getElementById('labChart').getContext('2d');
        const labData = <?php echo json_encode(array_values($labData)); ?>;
        const labLabels = <?php echo json_encode(array_keys($labData)); ?>;
        
        // Colors for lab chart
        const labColors = [
            '#ff9ff3', // Lab 1 - Pink
            '#feca57', // Lab 2 - Yellow
            '#ff6b6b', // Lab 3 - Red
            '#1dd1a1', // Lab 4 - Green
            '#54a0ff', // Lab 5 - Blue
            '#5f27cd'  // Lab 6 - Purple
        ];
        
        new Chart(labCtx, {
            type: 'pie',
            data: {
                labels: labLabels,
                datasets: [{
                    data: labData,
                    backgroundColor: labColors,
                    borderWidth: 1,
                    borderColor: '#fff',
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.formattedValue;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((context.raw / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            }
        });
        
        // Change entries per page
        function changeEntries(entries) {
            window.location.href = '?page=1&entries=' + entries + '&search=<?php echo urlencode($search); ?>';
        }
        
        // Search records
        function searchRecords() {
            const searchTerm = document.getElementById('search').value;
            window.location.href = '?page=1&entries=<?php echo $entriesPerPage; ?>&search=' + encodeURIComponent(searchTerm);
        }
        
        // Animate stat cards on page load
        document.addEventListener('DOMContentLoaded', () => {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html> 