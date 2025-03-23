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

// Search condition
$searchCondition = '';
if (!empty($filterDate)) {
    $searchCondition = " AND DATE(r.start_time) = '$filterDate'";
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

// Fetch completed sit-in sessions
$sql = "SELECT r.id, r.student_id, r.subject_id, r.purpose, r.start_time, r.end_time,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.is_active = 0" . $searchCondition . "
        ORDER BY r.end_time DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports</title>
    <link rel="stylesheet" href="style.css">
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
            z-index: 1000;
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
            background-color: #1abc9c;
            color: white;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
        }

        /* Main Content */
        .content {
            margin-top: 100px;
            padding: 30px;
            margin: 100px auto 30px;
            width: 95%;
            text-align: center;
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
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .date-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-control input {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .date-control button {
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
            display: flex;
            gap: 10px;
        }

        .export-btn {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            color: white;
            font-weight: bold;
        }

        .csv-btn {
            background-color: #27ae60;
        }

        .csv-btn:hover {
            background-color: #2ecc71;
        }

        .excel-btn {
            background-color: #16a085;
        }

        .excel-btn:hover {
            background-color: #1abc9c;
        }

        .pdf-btn {
            background-color: #e74c3c;
        }

        .pdf-btn:hover {
            background-color: #c0392b;
        }

        .print-btn {
            background-color: #f39c12;
        }

        .print-btn:hover {
            background-color: #f1c40f;
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

    <!-- Top Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="approved_sit_in_sessions.php">Sit in Records</a>
            <a href="active_sitin.php">Active Sit-ins</a>
            <a href="reports.php">Sit-in Reports</a>
        </div>
        <div class="logout-container">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="content">
        <h1>Generate Reports</h1>
        
        <!-- Report Controls -->
        <div class="report-controls">
            <div class="date-control">
                <input type="date" id="report-date" value="<?php echo $filterDate; ?>">
                <button class="search-btn" onclick="searchByDate()">Search</button>
                <button class="reset-btn" onclick="resetFilters()">Reset</button>
            </div>
            
            <div class="export-controls">
                <button class="export-btn csv-btn" onclick="exportCSV()">CSV</button>
                <button class="export-btn excel-btn" onclick="exportExcel()">Excel</button>
                <button class="export-btn pdf-btn" onclick="exportPDF()">PDF</button>
                <button class="export-btn print-btn" onclick="printReport()">Print</button>
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
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $fullName = $row['firstname'] . ' ' . $row['lastname'];
                            $startTime = date('h:i:sa', strtotime($row['start_time']));
                            $endTime = date('h:i:sa', strtotime($row['end_time']));
                            $date = date('Y-m-d', strtotime($row['end_time']));
                            
                            echo "<tr>
                                <td>{$row['student_id']}</td>
                                <td>{$fullName}</td>
                                <td>{$row['purpose']}</td>
                                <td>{$row['lab_number']}</td>
                                <td>{$startTime}</td>
                                <td>{$endTime}</td>
                                <td>{$date}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center;'>No records found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
    </footer>

    <script>
        // Filter functionality
        document.getElementById('filter-input').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const filterValue = this.value.trim();
                window.location.href = `reports.php?date=${document.getElementById('report-date').value}&filter=${encodeURIComponent(filterValue)}`;
            }
        });
        
        // Search by date
        function searchByDate() {
            const dateValue = document.getElementById('report-date').value;
            const filterValue = document.getElementById('filter-input').value.trim();
            window.location.href = `reports.php?date=${dateValue}&filter=${encodeURIComponent(filterValue)}`;
        }
        
        // Reset filters
        function resetFilters() {
            window.location.href = 'reports.php';
        }
        
        // Export to CSV
        function exportCSV() {
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
        function exportExcel() {
            const table = document.getElementById('report-table');
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Report');
            XLSX.writeFile(wb, 'sit_in_report.xlsx');
        }
        
        // Export to PDF
        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4');
            
            // Add title
            doc.setFontSize(18);
            doc.text('Sit-in Monitoring System - Report', 40, 40);
            
            // Add date
            const today = new Date();
            const dateStr = today.toLocaleDateString();
            doc.setFontSize(12);
            doc.text(`Generated on: ${dateStr}`, 40, 60);
            
            // Add the table
            doc.autoTable({
                html: '#report-table',
                startY: 70,
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
        function printReport() {
            window.print();
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
