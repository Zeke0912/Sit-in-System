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
            width: 100%;
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
            .sessions-table {
                font-size: 14px;
            }
            
            .sessions-table th,
            .sessions-table td {
                padding: 8px 10px;
            }
            
            body.sidebar-active .main-content {
                margin-left: 0;
                width: 100%;
            }
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
            <a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a>
            <a href="todays_sit_in_records.php"><i class="fas fa-calendar-day"></i> Today's Records</a>
            <a href="approved_sit_in_sessions.php"><i class="fas fa-history"></i> Sit in Records</a>
            <a href="active_sitin.php" class="active"><i class="fas fa-user-clock"></i> Active Sit-ins</a>
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
                            <form method="post" onsubmit="return confirm(\'Are you sure you want to end this session?\');">
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
    </div>

    <script>
        // Sidebar Toggle Functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.body.classList.toggle('sidebar-active');
        });
    </script>
</body>
</html> 