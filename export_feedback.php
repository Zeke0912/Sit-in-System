<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION["admin_id"])) {
    // Return error as JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection
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

// Get export type
$type = isset($_GET['type']) ? $_GET['type'] : 'csv';

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

// Fetch all feedback data
$sql = "SELECT r.id, r.student_id, r.feedback as message, r.end_time as date,
        u.firstname, u.lastname, u.course, u.year,
        s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN users u ON r.student_id = u.idno
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.is_active = 0 AND r.feedback IS NOT NULL 
        AND r.feedback != '' AND r.feedback != 'Looking forward to the session!'" . $searchCondition . "
        ORDER BY r.end_time DESC";

$result = $conn->query($sql);

// Set filename
$filename = 'feedback_report_' . date('Y-m-d') . '.' . ($type == 'excel' ? 'xlsx' : $type);

// Process based on export type
switch ($type) {
    case 'csv':
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel to correctly display UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add CSV header row
        fputcsv($output, ['ID', 'Student ID', 'Name', 'Laboratory', 'Date', 'Message']);
        
        // Add data rows
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $fullName = $row['firstname'] . ' ' . $row['lastname'];
                fputcsv($output, [
                    $row['id'],
                    $row['student_id'],
                    $fullName,
                    $row['lab_number'],
                    date('Y-m-d', strtotime($row['date'])),
                    $row['message']
                ]);
            }
        }
        
        // Close output stream
        fclose($output);
        break;
        
    case 'excel':
        // For Excel, we'll use CSV format with .xlsx extension
        // In a production environment, you'd use a library like PhpSpreadsheet
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add Excel header row
        fputcsv($output, ['ID', 'Student ID', 'Name', 'Laboratory', 'Date', 'Message']);
        
        // Add data rows
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $fullName = $row['firstname'] . ' ' . $row['lastname'];
                fputcsv($output, [
                    $row['id'],
                    $row['student_id'],
                    $fullName,
                    $row['lab_number'],
                    date('Y-m-d', strtotime($row['date'])),
                    $row['message']
                ]);
            }
        }
        
        // Close output stream
        fclose($output);
        break;
        
    case 'pdf':
        // For PDF, we'll output HTML that can be printed to PDF
        // In a production environment, you'd use a library like TCPDF or mPDF
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Feedback Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                }
                .report-header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .report-header h2 {
                    margin: 5px 0;
                    color: #2c3e50;
                }
                .report-header h3 {
                    margin: 5px 0;
                    color: #2c3e50;
                }
                .report-title {
                    margin-top: 25px;
                    color: #2980b9;
                    text-align: center;
                    font-size: 24px;
                    font-weight: bold;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th, td {
                    padding: 8px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                th {
                    background-color: #2980b9;
                    color: white;
                }
                tr:nth-child(even) {
                    background-color: #f2f2f2;
                }
                .generated-date {
                    text-align: right;
                    margin-top: 20px;
                    font-style: italic;
                    color: #777;
                }
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
                        <th>Date</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $fullName = $row['firstname'] . ' ' . $row['lastname'];
                            echo "<tr>
                                <td>{$row['student_id']}</td>
                                <td>{$fullName}</td>
                                <td>{$row['lab_number']}</td>
                                <td>" . date('Y-m-d', strtotime($row['date'])) . "</td>
                                <td>{$row['message']}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center;'>No feedback records found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="generated-date">
                Generated on: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            
            <script>
                window.onload = function() {
                    window.print();
                }
            </script>
        </body>
        </html>
        <?php
        break;
        
    default:
        // Invalid export type
        header('Location: feedback_reports.php');
        exit();
}

// Close connection
$conn->close();
?> 