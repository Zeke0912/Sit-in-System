<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";  // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php"); // Redirect if not logged in
    exit();
}

// Step 1: Fetch all pending sit-in requests, join with subjects for date and time
$sql = "
    SELECT r.*, u.firstname, u.lastname, u.course, u.year, s.subject_name, s.lab_number, s.date, s.start_time as subject_start, s.end_time as subject_end
    FROM sit_in_requests r
    JOIN users u ON r.student_id = u.idno
    JOIN subjects s ON r.subject_id = s.id
    WHERE r.status = 'pending'
    ORDER BY s.date, s.start_time";

$result = $conn->query($sql);

// Check if the query was successful
if ($result === false) {
    die("Error: " . $conn->error); // Display error if the query fails
}

// Step 2: Process approval/rejection actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        // Update the request to 'approved' and activate the session
        $update_sql = "UPDATE sit_in_requests SET status = 'approved', is_active = 1, start_time = NOW() WHERE id = ?";
    } elseif ($action == 'reject') {
        // Update the status to 'rejected'
        $update_sql = "UPDATE sit_in_requests SET status = 'rejected' WHERE id = ?";
    }

    // Prepare and execute the update query
    if ($stmt = $conn->prepare($update_sql)) {
        $stmt->bind_param("i", $request_id);  // 'i' for integer type
        $stmt->execute();
        $stmt->close();
    }
    
    // If approved, get the subject information for the approval
    if ($action == 'approve') {
        // Get subject_id to update its status
        $get_subject_sql = "SELECT subject_id FROM sit_in_requests WHERE id = ?";
        $get_subject_stmt = $conn->prepare($get_subject_sql);
        $get_subject_stmt->bind_param("i", $request_id);
        $get_subject_stmt->execute();
        $subject_result = $get_subject_stmt->get_result();
        
        if ($row = $subject_result->fetch_assoc()) {
            $subject_id = $row['subject_id'];
            
            // Update subject status if needed
            $update_subject_sql = "UPDATE subjects SET status = 'approved' WHERE id = ?";
            $update_subject_stmt = $conn->prepare($update_subject_sql);
            $update_subject_stmt->bind_param("i", $subject_id);
            $update_subject_stmt->execute();
            $update_subject_stmt->close();
        }
        $get_subject_stmt->close();
    }
    
    // Redirect back to this page after performing the action
    header("Location: manage_sit_in_requests.php");  // Refresh the page to show updated status
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sit-in Requests</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            text-align: left;
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

        /* Title Container for Manage Sit-in Requests */
        .title-container {
            width: 100%;
            margin: 0px auto 20px;
            padding: 15px;
            background-color: white;  /* White background */
            color: black; /* Dark blue text color */
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow to make the container stand out */
            font-size: 24px;
        }

        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table th, .requests-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            color: #333; /* Set text color to a darker color for readability */
        }

        .requests-table th {
            background-color: #2980B9;
            color: white;
        }

        .requests-table td button {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .approve-btn {
            background-color: #2C3E50;
            color: white;
        }

        .reject-btn {
            background-color: #e74c3c;
            color: white;
        }

        .approve-btn:hover {
            background-color: #2980B9;
        }

        .reject-btn:hover {
            background-color: #c0392b;
        }

        .error-message {
            color: red;
            margin-top: 20px;
            font-size: 16px;
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
            .container, .title-container {
                width: 100%;
            }
            
            .requests-table th, .requests-table td {
                padding: 8px;
                font-size: 14px;
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
            <a href="manage_sit_in_requests.php" class="active"><i class="fas fa-tasks"></i> Manage Requests</a>
            <a href="todays_sit_in_records.php"><i class="fas fa-calendar-day"></i> Today's Records</a>
            <a href="approved_sit_in_sessions.php"><i class="fas fa-history"></i> Sit in Records</a>
            <a href="active_sitin.php"><i class="fas fa-user-clock"></i> Active Sit-ins</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Sit-in Reports</a>
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
            <!-- Title container -->
            <div class="title-container">
                Manage Sit-in Requests
            </div>

            <!-- Main content container for sit-in request table -->
            <div class="container">
                <?php if ($result && $result->num_rows > 0): ?>
                    <table class="requests-table">
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Course/Year</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                <td><?php echo htmlspecialchars($row['course'] . ' - Year ' . $row['year']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject_start']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject_end']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td>
                                    <!-- Buttons for Approve and Reject -->
                                    <form method="POST" action="manage_sit_in_requests.php" style="display:inline-block;">
                    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="action" value="approve" class="approve-btn">Approve</button>
                </form>

                <form method="POST" action="manage_sit_in_requests.php" style="display:inline-block;">
                    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                    <button type="submit" name="action" value="reject" class="reject-btn">Reject</button>
                </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <p class="error-message">No pending sit-in requests at the moment.</p>
                <?php endif; ?>
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
    </script>
</body>
</html>