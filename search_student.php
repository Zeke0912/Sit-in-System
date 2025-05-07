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
    echo "Unauthorized access";
    exit();
}

// Check if student ID is provided
if (isset($_POST['studentId']) && !empty($_POST['studentId'])) {
    $studentId = $_POST['studentId'];
    
    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT idno, lastname, firstname, middlename, course, year, email, photo, remaining_sessions FROM users WHERE idno = ? AND role = 'student'");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Student found
        $student = $result->fetch_assoc();
        
        // Get sit-in request history
        $requestStmt = $conn->prepare("
            SELECT sir.id, sir.status, sir.feedback, sir.purpose, s.lab_number, s.date, s.start_time, s.end_time 
            FROM sit_in_requests sir
            LEFT JOIN subjects s ON sir.subject_id = s.id
            WHERE sir.student_id = ?
            ORDER BY sir.id DESC
        ");
        $requestStmt->bind_param("i", $studentId);
        $requestStmt->execute();
        $requestResult = $requestStmt->get_result();
        
        // Build the student card
        echo '<div class="student-card">';
        echo '<img src="' . $student['photo'] . '" alt="Student Photo" class="student-photo">';
        echo '<div class="student-info">';
        echo '<h3>' . $student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'] . '</h3>';
        echo '<p><strong>ID Number:</strong> ' . $student['idno'] . '</p>';
        echo '<p><strong>Course:</strong> ' . $student['course'] . '</p>';
        echo '<p><strong>Year:</strong> ' . $student['year'] . '</p>';
        echo '<p><strong>Email:</strong> ' . $student['email'] . '</p>';
        echo '<p><strong>Remaining Sessions:</strong> ' . $student['remaining_sessions'] . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Display sit-in request history
        if ($requestResult->num_rows > 0) {
            echo '<h3 class="history-title">Sit-in Request History</h3>';
            echo '<div class="history-table-container">';
            echo '<table class="history-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Purpose</th>';
            echo '<th>Lab</th>';
            echo '<th>Date</th>';
            echo '<th>Time</th>';
            echo '<th>Status</th>';
            echo '<th>Feedback</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            while ($request = $requestResult->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $request['purpose'] . '</td>';
                echo '<td>' . $request['lab_number'] . '</td>';
                echo '<td>' . $request['date'] . '</td>';
                echo '<td>' . $request['start_time'] . ' - ' . $request['end_time'] . '</td>';
                
                // Format status with appropriate color
                $statusClass = '';
                switch ($request['status']) {
                    case 'approved':
                        $statusClass = 'status-approved';
                        break;
                    case 'rejected':
                        $statusClass = 'status-rejected';
                        break;
                    case 'pending':
                        $statusClass = 'status-pending';
                        break;
                    case 'logged_out':
                        $statusClass = 'status-logged-out';
                        break;
                }
                
                echo '<td><span class="status ' . $statusClass . '">' . ucfirst($request['status']) . '</span></td>';
                echo '<td>' . ($request['feedback'] ? $request['feedback'] : 'N/A') . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p class="no-history">No sit-in request history found for this student.</p>';
        }
        
        $requestStmt->close();
    } else {
        // Student not found
        echo '<div class="error-message">No student found with ID: ' . $studentId . '</div>';
    }
    
    $stmt->close();
} else {
    // No student ID provided
    echo '<div class="error-message">Please enter a student ID number.</div>';
}

$conn->close();
?>

<style>
    .history-title {
        margin-top: 20px;
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 5px;
    }
    
    .history-table-container {
        margin-top: 15px;
        overflow-x: auto;
    }
    
    .history-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    
    .history-table th, .history-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .history-table th {
        background-color: #f2f2f2;
        color: #333;
    }
    
    .history-table tr:hover {
        background-color: #f5f5f5;
    }
    
    .status {
        padding: 5px 10px;
        border-radius: 4px;
        font-weight: bold;
    }
    
    .status-approved {
        background-color: #2ecc71;
        color: white;
    }
    
    .status-rejected {
        background-color: #e74c3c;
        color: white;
    }
    
    .status-pending {
        background-color: #f39c12;
        color: white;
    }
    
    .status-logged-out {
        background-color: #95a5a6;
        color: white;
    }
    
    .no-history, .error-message {
        margin-top: 15px;
        padding: 10px;
        border-radius: 4px;
    }
    
    .no-history {
        background-color: #f8f9fa;
        color: #6c757d;
    }
    
    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style> 
