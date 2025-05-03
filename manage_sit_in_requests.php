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

// Step 1: Fetch all pending sit-in requests with student info
$sql = "
    SELECT r.*, u.firstname, u.lastname, u.course, u.year, s.subject_name, s.lab_number
    FROM sit_in_requests r
    JOIN users u ON r.student_id = u.idno
    LEFT JOIN subjects s ON r.subject_id = s.id
    WHERE r.status = 'pending'
    ORDER BY r.start_time";

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
        $update_sql = "UPDATE sit_in_requests SET status = 'approved', is_active = 1 WHERE id = ?";
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
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
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
            padding: 20px;
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            border-radius: 10px;
            box-shadow: var(--shadow);
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .title-container i {
            margin-right: 10px;
            font-size: 28px;
        }

        .container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        .requests-table th, .requests-table td {
            border: 1px solid #eee;
            padding: 12px 15px;
            text-align: left;
            color: #333;
        }

        .requests-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .requests-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .requests-table tr:hover {
            background-color: #f1f5f9;
        }

        .requests-table td button {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            margin: 0 2px;
        }

        .approve-btn {
            background-color: var(--success-color);
            color: white;
        }

        .reject-btn {
            background-color: var(--danger-color);
            color: white;
        }

        .approve-btn:hover {
            background-color: #219652;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }

        .reject-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        }
        
        .purpose-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            background-color: #f0f0f0;
            color: #333;
        }

        .error-message {
            color: var(--danger-color);
            margin: 40px 0;
            font-size: 18px;
            text-align: center;
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            color: #777;
            font-size: 18px;
            text-align: center;
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
                padding: 15px;
            }
            
            .requests-table th, .requests-table td {
                padding: 8px;
                font-size: 14px;
            }
            
            body.sidebar-active .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .title-container {
                font-size: 20px;
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
            <a href="upload_lab_schedules.php"><i class="fas fa-calendar-alt"></i> Lab Schedules</a>
            <a href="#" id="searchBtn"><i class="fas fa-search"></i> Search</a>
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
                <i class="fas fa-clipboard-list"></i> Manage Sit-in Requests
            </div>

            <!-- Main content container for sit-in request table -->
            <div class="container">
                <?php if ($result && $result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Course/Year</th>
                                    <th>Lab</th>
                                    <th>Purpose</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                        <td><?php echo htmlspecialchars($row['course'] . ' - ' . $row['year']); ?></td>
                                        <td><?php echo htmlspecialchars($row['lab_number']); ?></td>
                                        <td>
                                            <span class="purpose-badge">
                                                <?php echo htmlspecialchars($row['purpose']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            // Format the date and time from start_time
                                            $dateTime = new DateTime($row['start_time']);
                                            echo $dateTime->format('M d, Y - h:i A'); 
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Buttons for Approve and Reject -->
                                            <form method="POST" action="manage_sit_in_requests.php" style="display:inline-block;">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="approve-btn">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>

                                            <form method="POST" action="manage_sit_in_requests.php" style="display:inline-block;">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="action" value="reject" class="reject-btn">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <p>No pending sit-in requests at the moment.</p>
                    </div>
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