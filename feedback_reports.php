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
                              r.feedback LIKE '%$search%' OR 
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

// Fetch feedback data
$sql = "SELECT r.id, r.student_id, r.feedback as message, r.end_time as date,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.is_active = 0" . $searchCondition . "
        ORDER BY r.end_time DESC
        LIMIT $offset, $entriesPerPage";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reports</title>
    <link rel="stylesheet" href="style.css">
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
            margin: 80px auto 30px;
            width: 95%;
            text-align: center;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
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
            position: relative;
            cursor: pointer;
        }
        
        .records-table th:hover {
            background-color: #3498db;
        }
        
        .records-table tr:hover {
            background-color: #f5f5f5;
        }
        
        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            justify-content: flex-start;
        }
        
        .export-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
        }
        
        .export-btn:hover {
            opacity: 0.9;
        }
        
        .print-btn {
            background-color: #2c3e50;
            color: white;
        }
        
        .csv-btn {
            background-color: #27ae60;
            color: white;
        }
        
        .excel-btn {
            background-color: #16a085;
            color: white;
        }
        
        .pdf-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        /* Search and filter */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-control {
            display: flex;
            align-items: center;
        }
        
        .search-control input {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            width: 200px;
        }
        
        /* Pagination */
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
                margin-top: 150px;
                width: 95%;
            }

            .navbar .nav-links {
                flex-direction: column;
                gap: 10px;
            }
            
            .table-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .export-buttons {
                flex-wrap: wrap;
            }
            
            .search-control {
                width: 100%;
            }
            
            .search-control input {
                width: 100%;
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
        <h1>Feedback Report</h1>
        
        <!-- Export Buttons -->
        <div class="export-buttons">
            <button class="export-btn print-btn" onclick="window.print()">Print</button>
            <button class="export-btn csv-btn" onclick="exportToCSV()">CSV</button>
            <button class="export-btn excel-btn" onclick="exportToExcel()">Excel</button>
            <button class="export-btn pdf-btn" onclick="exportToPDF()">PDF</button>
        </div>
        
        <!-- Table Section -->
        <div class="records-table-container">
            <!-- Table Controls -->
            <div class="table-controls">
                <div class="search-control">
                    <label for="search">Filter:</label>
                    <input type="text" id="search" value="<?php echo htmlspecialchars($search); ?>" onkeyup="if(event.keyCode === 13) searchRecords()">
                </div>
            </div>
            
            <!-- Records Table -->
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Student ID Number <span class="sort-icon">▲</span></th>
                        <th>Laboratory <span class="sort-icon">▲</span></th>
                        <th>Date <span class="sort-icon">▲</span></th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>
                                <td>{$row['student_id']}</td>
                                <td>{$row['lab_number']}</td>
                                <td>" . date('Y-M-d', strtotime($row['date'])) . "</td>
                                <td>{$row['message']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' style='text-align:center;'>No feedback records found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div>
                    Showing <?php echo min($totalRecords, $offset + 1); ?> to <?php echo min($totalRecords, $offset + $entriesPerPage); ?> of <?php echo $totalRecords; ?> entries
                </div>
                
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a href="?page=1&search=<?php echo urlencode($search); ?>">First</a></li>
                        <li><a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">Previous</a></li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <li><a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li><a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next</a></li>
                        <li><a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>">Last</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
    </footer>

    <script>
        // Search records
        function searchRecords() {
            const searchTerm = document.getElementById('search').value;
            window.location.href = '?page=1&search=' + encodeURIComponent(searchTerm);
        }
        
        // Export to CSV
        function exportToCSV() {
            window.location.href = 'export_feedback.php?type=csv&search=<?php echo urlencode($search); ?>';
        }
        
        // Export to Excel
        function exportToExcel() {
            window.location.href = 'export_feedback.php?type=excel&search=<?php echo urlencode($search); ?>';
        }
        
        // Export to PDF
        function exportToPDF() {
            window.location.href = 'export_feedback.php?type=pdf&search=<?php echo urlencode($search); ?>';
        }
    </script>
</body>
</html> 