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

// Get total records count for pagination
$countSql = "SELECT COUNT(*) as total FROM sit_in_requests r
             JOIN users u ON r.student_id = u.idno
             JOIN subjects s ON r.subject_id = s.id
             WHERE r.is_active = 0" . $searchCondition;
$countResult = $conn->query($countSql);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $entriesPerPage);

// Fetch completed sit-in sessions
$sql = "SELECT r.id, r.student_id, r.subject_id, r.purpose, r.start_time, r.end_time,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.is_active = 0" . $searchCondition . "
        ORDER BY r.end_time DESC
        LIMIT $offset, $entriesPerPage";

$result = $conn->query($sql);

// Get purpose statistics for pie chart
$purposeStatsSql = "SELECT r.purpose, COUNT(*) as count
                    FROM sit_in_requests r
                    WHERE r.is_active = 0
                    GROUP BY r.purpose
                    ORDER BY count DESC";
$purposeStatsResult = $conn->query($purposeStatsSql);
$purposeData = [];
while ($row = $purposeStatsResult->fetch_assoc()) {
    $purposeData[$row['purpose']] = (int)$row['count'];
}

// Get lab statistics for pie chart
$labStatsSql = "SELECT s.lab_number, COUNT(*) as count
                FROM sit_in_requests r
                JOIN subjects s ON r.subject_id = s.id
                WHERE r.is_active = 0
                GROUP BY s.lab_number
                ORDER BY count DESC";
$labStatsResult = $conn->query($labStatsSql);
$labData = [];
while ($row = $labStatsResult->fetch_assoc()) {
    $labData[$row['lab_number']] = (int)$row['count'];
}

// Get total sit-in count
$totalSitIns = $totalRecords;

// Get top programming language
$topPurposeSql = "SELECT purpose, COUNT(*) as count 
                FROM sit_in_requests 
                WHERE is_active = 0 
                GROUP BY purpose 
                ORDER BY count DESC 
                LIMIT 1";
$topPurposeResult = $conn->query($topPurposeSql);
$topPurpose = $topPurposeResult->num_rows > 0 ? $topPurposeResult->fetch_assoc()['purpose'] : 'None';

// Get most used lab
$topLabSql = "SELECT s.lab_number, COUNT(*) as count 
             FROM sit_in_requests r
             JOIN subjects s ON r.subject_id = s.id
             WHERE r.is_active = 0 
             GROUP BY s.lab_number 
             ORDER BY count DESC 
             LIMIT 1";
$topLabResult = $conn->query($topLabSql);
$topLab = $topLabResult->num_rows > 0 ? $topLabResult->fetch_assoc()['lab_number'] : 'None';

// Get average session duration
$avgDurationSql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration 
                  FROM sit_in_requests 
                  WHERE is_active = 0 AND end_time IS NOT NULL";
$avgDurationResult = $conn->query($avgDurationSql);
$avgDurationMinutes = $avgDurationResult->fetch_assoc()['avg_duration'];
$avgDurationFormatted = floor($avgDurationMinutes / 60) . 'h ' . ($avgDurationMinutes % 60) . 'm';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in Records</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        /* Top Navbar */
        .navbar {
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;  /* Ensure navbar stays on top */
            background-color: #2c3e50;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 16px;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .navbar a:hover {
            background-color: #1abc9c;  /* New hover color */
            color: white;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
        }

        /* Main Content */
        .content {
            margin-top: 100px; /* Account for the height of the navbar */
            padding: 30px;
            margin: 30px auto;
            width: 95%;
            text-align: center;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
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

        /* Logout Button */
        .logout-container a {
            color: white;
            background-color: #e74c3c;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }

        .logout-container a:hover {
            background-color: #c0392b;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: center;
            }

            .content {
                margin-top: 130px;
                width: 95%;
            }

            .navbar .nav-links {
                flex-direction: column;
                gap: 10px;
            }
            
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
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="approved_sit_in_sessions.php">Sit in Records</a>
            <a href="active_sitin.php">Active Sit-ins</a>
            <a href="reports.php">Sit-in Reports</a>
            <a href="feedback_reports.php">Feedback Reports</a>
        </div>
        <div class="logout-container">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="content">
        <h1>Current Sit-in Records</h1>
        
        <!-- Stats Overview Section -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-label">Total Sessions</div>
                <div class="stat-value"><?php echo $totalSitIns; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Top Language</div>
                <div class="stat-value"><?php echo $topPurpose; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Most Used Lab</div>
                <div class="stat-value"><?php echo $topLab; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg. Session</div>
                <div class="stat-value"><?php echo $avgDurationFormatted; ?></div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-container">
            <!-- Programming Languages Chart -->
            <div class="chart-container">
                <div class="chart-title">Programming Languages</div>
                <canvas id="purposeChart"></canvas>
            </div>
            
            <!-- Labs Chart -->
            <div class="chart-container">
                <div class="chart-title">Laboratory Usage</div>
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
                        <th>Login</th>
                        <th>Logout</th>
                        <th>Date</th>
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
                            $date = date('Y-m-d', strtotime($row['end_time']));
                            
                            echo "<tr>
                                <td>{$counter}</td>
                                <td>{$row['student_id']}</td>
                                <td>{$fullName}</td>
                                <td>{$row['purpose']}</td>
                                <td>{$row['lab_number']}</td>
                                <td>{$startTime}</td>
                                <td>{$endTime}</td>
                                <td>{$date}</td>
                            </tr>";
                            $counter++;
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align:center;'>No records found</td></tr>";
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

    <script>
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
            '#34495e'  // Other - Dark blue
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
            '#ff9ff3', // Lab 524 - Pink
            '#feca57', // Lab 526 - Yellow
            '#ff6b6b', // Lab 528 - Red
            '#1dd1a1', // Lab 530 - Green
            '#54a0ff', // Lab 542 - Blue
            '#5f27cd'  // Mac - Purple
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