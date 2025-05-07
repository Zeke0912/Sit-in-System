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

// Get date parameter
$filterDate = isset($_GET['date']) ? $_GET['date'] : '';

// Get laboratory filter
$labFilter = isset($_GET['lab']) ? $conn->real_escape_string($_GET['lab']) : '';

// Get purpose filter
$purposeFilter = isset($_GET['purpose']) ? $conn->real_escape_string($_GET['purpose']) : '';

// Get status filter
$statusFilter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Search condition
$searchCondition = '';
if (!empty($filterDate)) {
    $searchCondition = " AND DATE(r.start_time) = '$filterDate'";
}

// Apply lab filter if selected
if (!empty($labFilter)) {
    $searchCondition .= " AND s.lab_number = '$labFilter'";
}

// Apply purpose filter if selected
if (!empty($purposeFilter)) {
    $searchCondition .= " AND r.purpose = '$purposeFilter'";
}

// Apply status filter if selected
if (!empty($statusFilter)) {
    $searchCondition .= " AND r.status = '$statusFilter'";
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $conn->real_escape_string($_GET['filter']) : '';
if (!empty($filter)) {
    $searchCondition .= " AND (u.firstname LIKE '%$filter%' OR 
                          u.lastname LIKE '%$filter%' OR 
                          r.purpose LIKE '%$filter%' OR 
                          u.idno LIKE '%$filter%' OR
                          s.lab_number LIKE '%$filter%')";
}

// Fetch all available labs and purposes for filter dropdowns
$labsQuery = "SELECT DISTINCT lab_number FROM subjects ORDER BY lab_number";
$labsResult = $conn->query($labsQuery);
$labs = [];
if ($labsResult && $labsResult->num_rows > 0) {
    while ($row = $labsResult->fetch_assoc()) {
        $labs[] = $row['lab_number'];
    }
}

$purposesQuery = "SELECT DISTINCT purpose FROM sit_in_requests WHERE purpose IS NOT NULL AND purpose != '' ORDER BY purpose";
$purposesResult = $conn->query($purposesQuery);
$purposes = [];
if ($purposesResult && $purposesResult->num_rows > 0) {
    while ($row = $purposesResult->fetch_assoc()) {
        $purposes[] = $row['purpose'];
    }
}

// Fetch sit-in sessions (all statuses)
$sql = "SELECT r.id, r.student_id, r.subject_id, r.purpose, r.start_time, r.end_time,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number, r.status, r.is_active
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE 1=1" . $searchCondition . "
        ORDER BY r.start_time DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding-top: 20px;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 10px 20px;
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h3 {
            overflow: hidden;
            white-space: nowrap;
            opacity: 1;
            transition: opacity 0.3s;
        }

        .sidebar.collapsed .sidebar-header h3 {
            opacity: 0;
            width: 0;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            overflow: hidden;
            white-space: nowrap;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #1abc9c;
        }

        .sidebar-menu a i {
            margin-right: 15px;
            font-size: 18px;
            min-width: 24px;
            text-align: center;
        }

        .sidebar.collapsed .sidebar-menu a span {
            opacity: 0;
            width: 0;
        }

        .sidebar-menu a span {
            opacity: 1;
            transition: opacity 0.3s;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .main-content.expanded {
            margin-left: 70px;
        }

        .content {
            flex: 1;
            padding: 20px;
            padding-bottom: 20px;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
        }

        /* Report Controls */
        .report-controls {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: bold;
            font-size: 14px;
            color: #555;
        }

        .filter-group select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: white;
        }

        .date-control {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .date-control .buttons {
            display: flex;
            gap: 10px;
        }

        .date-control input {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .date-control button, 
        .filter-group button {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            color: white;
            font-weight: bold;
        }

        .search-btn {
            background-color: #2980b9;
        }

        .search-btn:hover {
            background-color: #3498db;
        }

        .reset-btn {
            background-color: #e74c3c;
        }

        .reset-btn:hover {
            background-color: #c0392b;
        }

        .export-controls {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .export-controls button {
            padding: 8px 15px;
            background-color: #4CAF50;
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

        .export-controls button:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .export-controls button i {
            font-size: 16px;
        }

        /* Filter Section */
        .filter-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .filter-input {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            width: 250px;
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
        }

        .records-table tr:hover {
            background-color: #f5f5f5;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .sidebar-header h3,
            .sidebar .sidebar-menu a span {
                opacity: 0;
                width: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .sidebar.collapsed {
                width: 0;
                padding: 0;
            }
            
            .main-content.expanded {
                margin-left: 0;
            }

            .report-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .export-controls {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="todays_sit_in_records.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Sit-in Records</span>
                </a>
            </li>
            <li>
                <a href="active_sitin.php">
                    <i class="fas fa-user-clock"></i>
                    <span>Active Sit-ins</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Sit-in Reports</span>
                </a>
            </li>
            <li>
                <a href="feedback_reports.php">
                    <i class="fas fa-comments"></i>
                    <span>Feedback Reports</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
    <div class="content">
        <h1>Generate Reports</h1>
        
        <!-- Report Controls -->
        <div class="report-controls">
                <div class="filter-group">
                    <label for="lab-filter">Laboratory:</label>
                    <select id="lab-filter" name="lab" onchange="applyLabFilter()">
                        <option value="">All Laboratories</option>
                        <?php
                        foreach ($labs as $lab) {
                            echo "<option value='$lab'>$lab</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="purpose-filter">Purpose:</label>
                    <select id="purpose-filter" name="purpose" onchange="applyPurposeFilter()">
                        <option value="">All Purposes</option>
                        <?php
                        foreach ($purposes as $purpose) {
                            echo "<option value='$purpose'>$purpose</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="status-filter">Status:</label>
                    <select id="status-filter" name="status" onchange="applyStatusFilter()">
                        <option value="">All Statuses</option>
                        <option value="approved" <?php echo $statusFilter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
            <div class="date-control">
                <input type="date" id="report-date" value="<?php echo $filterDate; ?>">
                    <div class="buttons">
                <button class="search-btn" onclick="searchByDate()">Search</button>
                <button class="reset-btn" onclick="resetFilters()">Reset</button>
                    </div>
            </div>
            
            <div class="export-controls">
                    <button onclick="exportTableToCSV()" class="export-btn"><i class="fas fa-file-csv"></i> Export to CSV</button>
                    <button onclick="exportTableToExcel()" class="export-btn"><i class="fas fa-file-excel"></i> Export to Excel</button>
                    <button onclick="exportTableToPDF()" class="export-btn"><i class="fas fa-file-pdf"></i> Export to PDF</button>
                    <button onclick="printTable()" class="export-btn"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <input type="text" class="filter-input" id="filter-input" placeholder="Filter..." value="<?php echo $filter; ?>">
        </div>
        
        <!-- Report Table -->
        <div class="records-table-container">
            <table class="records-table" id="report-table">
                <thead>
                    <tr>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Purpose</th>
                        <th>Laboratory</th>
                        <th>Login</th>
                        <th>Logout</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $fullName = $row['firstname'] . ' ' . $row['lastname'];
                            $startTime = date('h:i:sa', strtotime($row['start_time']));
                            $endTime = $row['end_time'] ? date('h:i:sa', strtotime($row['end_time'])) : 'N/A';
                            $date = date('Y-m-d', strtotime($row['start_time']));
                            
                            // Format status
                            $statusText = '';
                            if ($row['is_active'] == 1 && $row['status'] == 'approved') {
                                $statusText = '<span style="color: #27ae60; font-weight: bold;">Active</span>';
                            } elseif ($row['status'] == 'approved' && $row['is_active'] == 0) {
                                $statusText = '<span style="color: #3498db; font-weight: bold;">Completed</span>';
                            } elseif ($row['status'] == 'rejected') {
                                $statusText = '<span style="color: #e74c3c; font-weight: bold;">Rejected</span>';
                            } elseif ($row['status'] == 'pending') {
                                $statusText = '<span style="color: #f39c12; font-weight: bold;">Pending</span>';
                            } else {
                                $statusText = $row['status'];
                            }
                            
                            echo "<tr>
                                <td>{$row['student_id']}</td>
                                <td>{$fullName}</td>
                                <td>{$row['purpose']}</td>
                                <td>{$row['lab_number']}</td>
                                <td>{$startTime}</td>
                                <td>{$endTime}</td>
                                <td>{$date}</td>
                                <td>{$statusText}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align:center;'>No records found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
    </footer>
    </div>

    <script>
        // Sidebar toggle
        const toggleBtn = document.querySelector('.toggle-btn');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    
        // Select current values in dropdowns
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Set lab filter if in URL
            const labValue = urlParams.get('lab');
            if (labValue) {
                document.getElementById('lab-filter').value = labValue;
            }
            
            // Set purpose filter if in URL
            const purposeValue = urlParams.get('purpose');
            if (purposeValue) {
                document.getElementById('purpose-filter').value = purposeValue;
            }
            
            // Set status filter if in URL
            const statusValue = urlParams.get('status');
            if (statusValue) {
                document.getElementById('status-filter').value = statusValue;
            }
        };
        
        // Apply lab filter
        function applyLabFilter() {
            const labValue = document.getElementById('lab-filter').value;
            const dateValue = document.getElementById('report-date').value;
            const filterValue = document.getElementById('filter-input').value.trim();
            const purposeValue = document.getElementById('purpose-filter').value;
            const statusValue = document.getElementById('status-filter').value;
            
            window.location.href = `reports.php?date=${dateValue}&filter=${encodeURIComponent(filterValue)}&lab=${encodeURIComponent(labValue)}&purpose=${encodeURIComponent(purposeValue)}&status=${encodeURIComponent(statusValue)}`;
        }
        
        // Apply purpose filter
        function applyPurposeFilter() {
            const purposeValue = document.getElementById('purpose-filter').value;
            const dateValue = document.getElementById('report-date').value;
            const filterValue = document.getElementById('filter-input').value.trim();
            const labValue = document.getElementById('lab-filter').value;
            const statusValue = document.getElementById('status-filter').value;
            
            window.location.href = `reports.php?date=${dateValue}&filter=${encodeURIComponent(filterValue)}&lab=${encodeURIComponent(labValue)}&purpose=${encodeURIComponent(purposeValue)}&status=${encodeURIComponent(statusValue)}`;
        }
        
        // Apply status filter
        function applyStatusFilter() {
            const statusValue = document.getElementById('status-filter').value;
            const dateValue = document.getElementById('report-date').value;
            const filterValue = document.getElementById('filter-input').value.trim();
            const labValue = document.getElementById('lab-filter').value;
            const purposeValue = document.getElementById('purpose-filter').value;
            
            window.location.href = `reports.php?date=${dateValue}&filter=${encodeURIComponent(filterValue)}&lab=${encodeURIComponent(labValue)}&purpose=${encodeURIComponent(purposeValue)}&status=${encodeURIComponent(statusValue)}`;
        }
        
        // Search by date (updated to keep lab, purpose, and status filters)
        function searchByDate() {
            const dateValue = document.getElementById('report-date').value;
            const filterValue = document.getElementById('filter-input').value.trim();
            const labValue = document.getElementById('lab-filter').value;
            const purposeValue = document.getElementById('purpose-filter').value;
            const statusValue = document.getElementById('status-filter').value;
            
            window.location.href = `reports.php?date=${dateValue}&filter=${encodeURIComponent(filterValue)}&lab=${encodeURIComponent(labValue)}&purpose=${encodeURIComponent(purposeValue)}&status=${encodeURIComponent(statusValue)}`;
        }
        
        // Reset filters
        function resetFilters() {
            window.location.href = 'reports.php';
        }
        
        // Filter functionality (updated to keep lab, purpose, and status filters)
        document.getElementById('filter-input').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const filterValue = this.value.trim();
                const dateValue = document.getElementById('report-date').value;
                const labValue = document.getElementById('lab-filter').value;
                const purposeValue = document.getElementById('purpose-filter').value;
                const statusValue = document.getElementById('status-filter').value;
                
                window.location.href = `reports.php?date=${dateValue}&filter=${encodeURIComponent(filterValue)}&lab=${encodeURIComponent(labValue)}&purpose=${encodeURIComponent(purposeValue)}&status=${encodeURIComponent(statusValue)}`;
            }
        });
        
        // Export to CSV
        function exportTableToCSV() {
            const table = document.getElementById('report-table');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Replace any commas in the cell content to avoid CSV issues
                    let data = cols[j].innerText.replace(/,/g, ' ');
                    // Wrap in quotes to handle special characters
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(','));
            }
            
            // Download CSV file
            downloadFile(csv.join('\n'), 'sit_in_report.csv', 'text/csv');
        }
        
        // Export to Excel
        function exportTableToExcel() {
            const table = document.getElementById('report-table');
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Report');
            XLSX.writeFile(wb, 'sit_in_report.xlsx');
        }
        
        // Export to PDF
        function exportTableToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4');
            
            // Add university headers
            doc.setFontSize(16);
            doc.text('University of Cebu Main Campus', doc.internal.pageSize.width / 2, 30, { align: 'center' });
            doc.setFontSize(14);
            doc.text('College of Computer Studies', doc.internal.pageSize.width / 2, 50, { align: 'center' });
            doc.text('Computer Laboratory Sit-in Monitoring System', doc.internal.pageSize.width / 2, 70, { align: 'center' });
            
            // Add report title
            doc.setFontSize(18);
            doc.text('Sit-in Report', doc.internal.pageSize.width / 2, 100, { align: 'center' });
            
            // Add date and any filters
            const today = new Date();
            const dateStr = today.toLocaleDateString();
            let startY = 120;
            doc.setFontSize(12);
            doc.text(`Generated on: ${dateStr}`, 40, startY);
            startY += 20;
            
            // Add filters information
            const dateFilter = document.getElementById('report-date').value;
            const filterInput = document.getElementById('filter-input').value;
            const labFilter = document.getElementById('lab-filter').value;
            const purposeFilter = document.getElementById('purpose-filter').value;
            const statusFilter = document.getElementById('status-filter').value;
            
            if (dateFilter || filterInput || labFilter || purposeFilter || statusFilter) {
                if (dateFilter) {
                    doc.text(`Date: ${dateFilter}`, 40, startY);
                    startY += 15;
                }
                if (filterInput) {
                    doc.text(`Filter: ${filterInput}`, 40, startY);
                    startY += 15;
                }
                if (labFilter) {
                    const labElement = document.getElementById('lab-filter');
                    const labText = labElement.options[labElement.selectedIndex].text;
                    doc.text(`Laboratory: ${labText}`, 40, startY);
                    startY += 15;
                }
                if (purposeFilter) {
                    const purposeElement = document.getElementById('purpose-filter');
                    const purposeText = purposeElement.options[purposeElement.selectedIndex].text;
                    doc.text(`Purpose: ${purposeText}`, 40, startY);
                    startY += 15;
                }
                if (statusFilter) {
                    const statusElement = document.getElementById('status-filter');
                    const statusText = statusElement.options[statusElement.selectedIndex].text;
                    doc.text(`Status: ${statusText}`, 40, startY);
                    startY += 15;
                }
            }
            
            // Add the table
            doc.autoTable({
                html: '#report-table',
                startY: startY,
                theme: 'grid',
                headStyles: {
                    fillColor: [41, 128, 185],
                    textColor: 255
                },
                alternateRowStyles: {
                    fillColor: [240, 240, 240]
                }
            });
            
            // Save PDF
            doc.save('sit_in_report.pdf');
        }
        
        // Print report
        function printTable() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create content with university headers
            let content = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Sit-in Report</title>
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
                    <div class="report-title">Sit-in Activity Report</div>
            `;
            
            // Add filters if present
            const dateFilter = document.getElementById('report-date').value;
            const filterInput = document.getElementById('filter-input').value;
            const labFilter = document.getElementById('lab-filter').value;
            const purposeFilter = document.getElementById('purpose-filter').value;
            const statusFilter = document.getElementById('status-filter').value;
            
            if (dateFilter || filterInput || labFilter || purposeFilter || statusFilter) {
                content += `<div style="margin: 15px 0; text-align: center;">`;
                if (dateFilter) {
                    content += `<p><strong>Date:</strong> ${dateFilter}</p>`;
                }
                if (filterInput) {
                    content += `<p><strong>Filter:</strong> ${filterInput}</p>`;
                }
                if (labFilter) {
                    const labElement = document.getElementById('lab-filter');
                    const labText = labElement.options[labElement.selectedIndex].text;
                    content += `<p><strong>Laboratory:</strong> ${labText}</p>`;
                }
                if (purposeFilter) {
                    const purposeElement = document.getElementById('purpose-filter');
                    const purposeText = purposeElement.options[purposeElement.selectedIndex].text;
                    content += `<p><strong>Purpose:</strong> ${purposeText}</p>`;
                }
                if (statusFilter) {
                    const statusElement = document.getElementById('status-filter');
                    const statusText = statusElement.options[statusElement.selectedIndex].text;
                    content += `<p><strong>Status:</strong> ${statusText}</p>`;
                }
                content += `</div>`;
            }
            
            // Add table
            content += `<table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Purpose</th>
                        <th>Laboratory</th>
                        <th>Login</th>
                        <th>Logout</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            // Get all rows from the original table
            const table = document.getElementById('report-table');
            const rows = table.querySelectorAll('tbody tr');
            
            // Add rows to content
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                content += '<tr>';
                cells.forEach(cell => {
                    content += `<td>${cell.textContent}</td>`;
                });
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
        
        // Helper function to download file
        function downloadFile(data, filename, type) {
            const file = new Blob([data], {type: type});
            const a = document.createElement('a');
            const url = URL.createObjectURL(file);
            
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            
            setTimeout(function() {
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            }, 0);
        }
    </script>
</body>
</html>
