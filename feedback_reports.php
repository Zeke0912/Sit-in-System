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

// Count feedback
$countSql = "SELECT COUNT(*) as total FROM sit_in_requests r
             JOIN users u ON r.student_id = u.idno
             JOIN subjects s ON r.subject_id = s.id
             WHERE r.is_active = 0 AND r.feedback IS NOT NULL 
             AND r.feedback != '' AND r.feedback != 'Looking forward to the session!'" . $searchCondition;
$countResult = $conn->query($countSql);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $entriesPerPage);

// Fetch feedback records
$sql = "SELECT r.id, r.student_id, r.subject_id, r.purpose, r.start_time, r.end_time, r.feedback,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.is_active = 0 AND r.feedback IS NOT NULL 
        AND r.feedback != '' AND r.feedback != 'Looking forward to the session!'" . $searchCondition . "
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
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
        }
        
        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Table styles */
        .reports-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        .feedback-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .feedback-table th,
        .feedback-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .feedback-table th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        
        .feedback-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .feedback-content {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .view-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .view-btn:hover {
            background-color: #2980b9;
        }
        
        .export-btn {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .export-btn:hover {
            background-color: #2ecc71;
        }
        
        .export-btn i {
            margin-right: 8px;
        }
        
        /* Export buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 8px 15px;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .export-btn i {
            font-size: 16px;
        }
        
        .print-btn {
            background-color: #3498db;
        }
        
        .print-btn:hover {
            background-color: #2980b9;
        }
        
        .csv-btn {
            background-color: #27ae60;
        }
        
        .csv-btn:hover {
            background-color: #219653;
        }
        
        .excel-btn {
            background-color: #2ecc71;
        }
        
        .excel-btn:hover {
            background-color: #27ae60;
        }
        
        .pdf-btn {
            background-color: #e74c3c;
        }
        
        .pdf-btn:hover {
            background-color: #c0392b;
        }
        
        /* Table controls */
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
        
        /* Feedback Modal */
        .feedback-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border: 1px solid #e0e0e0;
            width: 60%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        
        .close:hover,
        .close:focus {
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }
        
        .feedback-text {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 16px;
            line-height: 1.6;
            border-left: 4px solid #3498db;
        }
        
        .student-info {
            margin-bottom: 15px;
        }
        
        .student-name {
            font-weight: bold;
            color: #2980b9;
        }
        
        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            width: 100%;
            margin-top: 30px;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-250px);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
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
            
            .modal-content {
                width: 90%;
                margin: 20% auto;
            }
        }
    </style>
</head>
<body>
    <!-- Left Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Sit-in Monitoring</h3>
            <p>Admin Panel</p>
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="computer_control.php"><i class="fas fa-desktop"></i> Computer Control</a>
            <a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a>
            <a href="todays_sit_in_records.php"><i class="fas fa-clipboard-list"></i> Today's Records</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Sit-in Reports</a>
            <a href="feedback_reports.php" class="active"><i class="fas fa-comments"></i> Feedback Reports</a>
            <a href="add_subject.php"><i class="fas fa-book"></i> Add Subject</a>
            <a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
        </div>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Student Feedback Reports</h1>
        
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
        
        <!-- Export Button -->
        <div class="export-buttons">
            <button class="export-btn print-btn" onclick="printReport()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="export-btn csv-btn" onclick="exportToCSV()">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
            <button class="export-btn excel-btn" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="export-btn pdf-btn" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
        
        <!-- Reports Container -->
        <div class="reports-container">
            <?php if ($result && $result->num_rows > 0): ?>
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Lab</th>
                            <th>Purpose</th>
                            <th>Date</th>
                            <th>Feedback</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = $offset + 1;
                        while ($row = $result->fetch_assoc()): 
                            $fullName = $row['firstname'] . ' ' . $row['lastname'];
                            $date = !empty($row['end_time']) ? date('Y-m-d', strtotime($row['end_time'])) : 'N/A';
                            $feedback = htmlspecialchars($row['feedback']);
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><?php echo $row['student_id']; ?></td>
                            <td><?php echo $fullName; ?></td>
                            <td><?php echo $row['lab_number']; ?></td>
                            <td><?php echo $row['purpose']; ?></td>
                            <td><?php echo $date; ?></td>
                            <td class="feedback-content"><?php echo substr($feedback, 0, 50) . (strlen($feedback) > 50 ? '...' : ''); ?></td>
                            <td>
                                <button class="view-btn" onclick="viewFeedback('<?php echo addslashes($feedback); ?>', '<?php echo addslashes($fullName); ?>', '<?php echo $row['student_id']; ?>', '<?php echo $row['course']; ?> - Year <?php echo $row['year']; ?>', '<?php echo $row['lab_number']; ?>', '<?php echo $date; ?>')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
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
            <?php else: ?>
                <div style="text-align: center; padding: 30px;">
                    <i class="fas fa-comments fa-3x" style="color: #bdc3c7; margin-bottom: 15px;"></i>
                    <p>No feedback found. Students haven't submitted any feedback yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
        </footer>
    </div>
    
    <!-- Feedback Modal -->
    <div id="feedbackModal" class="feedback-modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Student Feedback</h2>
            <div class="student-info">
                <p><strong>Student:</strong> <span id="studentName" class="student-name"></span> (<span id="studentId"></span>)</p>
                <p><strong>Course/Year:</strong> <span id="studentCourse"></span></p>
                <p><strong>Laboratory:</strong> <span id="labNumber"></span></p>
                <p><strong>Date:</strong> <span id="feedbackDate"></span></p>
            </div>
            <h3>Feedback:</h3>
            <div id="feedbackText" class="feedback-text"></div>
        </div>
    </div>
    
    <script>
        // Feedback Modal
        const modal = document.getElementById("feedbackModal");
        const closeBtn = document.getElementsByClassName("close")[0];
        
        function viewFeedback(feedback, name, id, course, lab, date) {
            document.getElementById("feedbackText").textContent = feedback;
            document.getElementById("studentName").textContent = name;
            document.getElementById("studentId").textContent = id;
            document.getElementById("studentCourse").textContent = course;
            document.getElementById("labNumber").textContent = lab;
            document.getElementById("feedbackDate").textContent = date;
            modal.style.display = "block";
        }
        
        closeBtn.onclick = function() {
            modal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
        
        // Table Controls
        function changeEntries(entries) {
            window.location.href = '?page=1&entries=' + entries + '&search=<?php echo urlencode($search); ?>';
        }
        
        function searchRecords() {
            const searchTerm = document.getElementById('search').value;
            window.location.href = '?page=1&entries=<?php echo $entriesPerPage; ?>&search=' + encodeURIComponent(searchTerm);
        }
        
        // Export Feedback
        function exportFeedback() {
            window.location.href = 'export_feedback.php';
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
        
        // Print report with headers
        function printReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create content with university headers
            let content = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Feedback Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .report-header { text-align: center; margin-bottom: 30px; }
                        .report-header h2 { margin: 5px 0; color: #2c3e50; }
                        .report-header h3 { margin: 5px 0; color: #2c3e50; }
                        .report-title { margin-top: 20px; color: #2980b9; text-align: center; font-size: 24px; font-weight: bold; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #2980b9; color: white; }
                        tr:nth-child(even) { background-color: #f2f2f2; }
                        .generated-date { text-align: right; margin-top: 20px; font-style: italic; color: #777; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h2>University of Cebu Main Campus</h2>
                        <h3>College of Computer Studies</h3>
                        <h3>Computer Laboratory Sit-in Monitoring System</h3>
                    </div>
                    <div class="report-title">Feedback Report</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Laboratory</th>
                                <th>Purpose</th>
                                <th>Date</th>
                                <th>Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Get all rows from the original table
            const table = document.querySelector('.feedback-table');
            const rows = table.querySelectorAll('tbody tr');
            
            // Add rows to content
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                content += '<tr>';
                
                // Student ID (cells[1])
                content += `<td>${cells[1].textContent}</td>`;
                
                // Name (cells[2])
                content += `<td>${cells[2].textContent}</td>`;
                
                // Laboratory (cells[3])
                content += `<td>${cells[3].textContent}</td>`;
                
                // Purpose (cells[4])
                content += `<td>${cells[4].textContent}</td>`;
                
                // Date (cells[5])
                content += `<td>${cells[5].textContent}</td>`;
                
                // Feedback (cells[6])
                content += `<td>${cells[6].textContent}</td>`;
                
                content += '</tr>';
            });
            
            // Complete the content
            content += `
                        </tbody>
                    </table>
                    <div class="generated-date">
                        Generated on: ${new Date().toLocaleString()}
                    </div>
                    <script>
                        window.onload = function() { window.print(); }
                    <\/script>
                </body>
                </html>
            `;
            
            // Write content to the new window
            printWindow.document.open();
            printWindow.document.write(content);
            printWindow.document.close();
        }
    </script>
</body>
</html>
<?php $conn->close(); ?> 
