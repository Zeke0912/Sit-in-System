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

// Handle ending a sit-in session
if (isset($_POST['end_session']) && isset($_POST['request_id'])) {
    $requestId = $_POST['request_id'];
    
    // Start transaction to ensure both operations succeed or fail together
    $conn->begin_transaction();
    
    try {
        // Get student ID before updating
        $getSessionInfo = $conn->prepare("SELECT student_id, start_time FROM sit_in_requests WHERE id = ? AND is_active = 1");
        $getSessionInfo->bind_param("i", $requestId);
        $getSessionInfo->execute();
        $sessionResult = $getSessionInfo->get_result();
        
        if ($sessionResult->num_rows > 0) {
            $sessionInfo = $sessionResult->fetch_assoc();
            $studentId = $sessionInfo['student_id'];
            
            // Update the sit-in request to mark it as inactive and set end time
            $updateRequest = $conn->prepare("UPDATE sit_in_requests SET is_active = 0, end_time = NOW() WHERE id = ?");
            $updateRequest->bind_param("i", $requestId);
            $updateRequest->execute();
            
            // Decrement the student's remaining sessions
            $updateSessions = $conn->prepare("UPDATE users SET remaining_sessions = remaining_sessions - 1 WHERE idno = ? AND remaining_sessions > 0");
            $updateSessions->bind_param("i", $studentId);
            $updateSessions->execute();
            
            $conn->commit();
            
            // Set success message
            $_SESSION['message'] = "Sit-in session ended successfully and remaining sessions decreased.";
            $_SESSION['message_type'] = "success";
        } else {
            // Session not found or already inactive
            $_SESSION['message'] = "Session not found or already ended.";
            $_SESSION['message_type'] = "error";
        }
        
        $getSessionInfo->close();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['message'] = "Error ending session: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect to prevent resubmission
    header("Location: active_sitin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Sit-in Sessions</title>
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
            padding: 15px;
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
            width: 85%;
            text-align: center;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
        }
        
        /* Table Styles */
        .sessions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .sessions-table th,
        .sessions-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .sessions-table th {
            background-color: #2980b9;
            color: white;
            font-weight: bold;
        }
        
        .sessions-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .end-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .end-btn:hover {
            background-color: #c0392b;
        }
        
        .no-sessions {
            padding: 30px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        /* Timer */
        .timer {
            font-weight: bold;
            color: #e67e22;
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
            
            .sessions-table {
                font-size: 14px;
            }
            
            .sessions-table th,
            .sessions-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_sit_in_requests.php">Manage Sit-in Requests</a>
            <a href="approved_sit_in_sessions.php">Sit in Records</a>
            <a href="active_sitin.php">Active Sit-ins</a>
            <a href="add_subject.php">Add Subject</a>
            <a href="announcements.php">Announcements</a>
        </div>
        <div class="logout-container">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="content">
        <h1>Current Sit-in Sessions</h1>
        
        <!-- Display alert messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo $_SESSION['message'];
                    // Clear message after displaying
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Fetch active sit-in sessions
        $sql = "SELECT r.id, r.student_id, r.subject_id, r.purpose, r.start_time,
                u.firstname, u.lastname, u.course, u.year, u.remaining_sessions,
                s.lab_number
                FROM sit_in_requests r
                JOIN users u ON r.student_id = u.idno
                JOIN subjects s ON r.subject_id = s.id
                WHERE r.is_active = 1
                ORDER BY r.start_time DESC";
        
        $result = $conn->query($sql);
        
        if ($result->num_rows > 0) {
            echo '<table class="sessions-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>ID Number</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Lab</th>
                        <th>Purpose</th>
                        <th>Start Time</th>
                        <th>Remaining Sessions</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
            
            while($row = $result->fetch_assoc()) {
                echo '<tr>
                    <td>' . $row['firstname'] . ' ' . $row['lastname'] . '</td>
                    <td>' . $row['student_id'] . '</td>
                    <td>' . $row['course'] . '</td>
                    <td>' . $row['year'] . '</td>
                    <td>' . $row['lab_number'] . '</td>
                    <td>' . $row['purpose'] . '</td>
                    <td>' . date('M d, Y g:i A', strtotime($row['start_time'])) . '</td>
                    <td>' . $row['remaining_sessions'] . '</td>
                    <td>
                        <form method="post" onsubmit="return confirm(\'Are you sure you want to end this session? This will decrease the student\\\'s remaining sessions by 1.\');">
                            <input type="hidden" name="request_id" value="' . $row['id'] . '">
                            <button type="submit" name="end_session" class="end-btn">End Session</button>
                        </form>
                    </td>
                </tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<div class="no-sessions">No active sit-in sessions found.</div>';
        }
        ?>
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
    </footer>
</body>
</html> 