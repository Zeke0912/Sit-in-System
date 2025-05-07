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

// Get most active sit-in participants
$mostActiveSql = "SELECT 
                    u.idno, 
                    u.firstname, 
                    u.lastname, 
                    u.course, 
                    COUNT(r.id) as session_count 
                  FROM sit_in_requests r
                  JOIN users u ON r.student_id = u.idno
                  GROUP BY r.student_id
                  ORDER BY session_count DESC
                  LIMIT 5";
$mostActiveResult = $conn->query($mostActiveSql);
$activeParticipants = [];

if ($mostActiveResult && $mostActiveResult->num_rows > 0) {
    while ($row = $mostActiveResult->fetch_assoc()) {
        $activeParticipants[] = $row;
    }
}

// Get top performing sit-in participants (using time spent as a proxy for performance)
$topPerformersSql = "SELECT 
                      u.idno, 
                      u.firstname, 
                      u.lastname, 
                      u.course, 
                      SUM(TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time)) as total_minutes,
                      ROUND(SUM(TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time))/60, 1) as total_hours
                    FROM sit_in_requests r
                    JOIN users u ON r.student_id = u.idno
                    WHERE r.end_time IS NOT NULL
                    GROUP BY r.student_id
                    ORDER BY total_minutes DESC
                    LIMIT 5";
$topPerformersResult = $conn->query($topPerformersSql);
$topPerformers = [];

if ($topPerformersResult && $topPerformersResult->num_rows > 0) {
    while ($row = $topPerformersResult->fetch_assoc()) {
        $topPerformers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            margin-bottom: 10px;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            margin-top: 30px;
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
            .chart-container {
                width: 100%;
                max-width: 300px;
            }
            
            body.sidebar-active .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border: 1px solid #888;
            width: 60%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease-in-out;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover,
        .close:focus {
            color: #e74c3c;
            text-decoration: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 16px;
            color: #2c3e50;
        }

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .search-btn, 
        .fetch-btn, 
        .submit-btn {
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.2s;
        }

        .search-btn:hover, 
        .fetch-btn:hover, 
        .submit-btn:hover {
            background-color: #3498db;
            transform: translateY(-2px);
        }
        
        .search-btn:active,
        .fetch-btn:active,
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .fetch-btn {
            margin-top: 12px;
            width: 100%;
        }
        
        .submit-btn {
            margin-top: 25px;
            width: 100%;
            padding: 14px;
            font-size: 16px;
            background-color: #27ae60;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        .submit-btn:hover {
            background-color: #2ecc71;
        }

        /* Enhanced student info display */
        .student-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border-left: 4px solid #3498db;
        }
        
        .student-profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2980b9;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        }
        
        .student-info-details {
            flex: 1;
        }
        
        .student-summary p {
            margin: 8px 0;
            font-size: 15px;
        }
        
        .student-name {
            font-weight: bold;
            font-size: 20px !important;
            color: #2c3e50;
            margin-bottom: 12px !important;
        }
        
        .sessions-count {
            font-weight: bold;
            color: #27ae60;
            font-size: 16px !important;
        }
        
        .modal h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        #studentResults, 
        #sitInResults {
            margin-top: 20px;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .student-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-top: 15px;
        }

        .student-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #2980b9;
        }

        .student-info {
            flex: 1;
            text-align: left;
        }

        .student-info h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .student-info p {
            margin: 5px 0;
            color: #555;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        /* Stats Container Styles */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-bottom: 25px;
        }
        
        .stats-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            min-width: 120px;
            max-width: 150px;
            flex: 1;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            background-color: #f8f9fa;
            color: #3498db;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 10px;
            font-size: 18px;
        }
        
        .stats-info h3 {
            font-size: 12px;
            margin: 0 0 8px 0;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-count {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        
        /* Charts Container */
        .charts-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            width: 300px; /* Increased from 250px */
            height: 300px; /* Increased from 250px */
            position: relative;
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .chart-title {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 10px;
            text-align: center;
            font-weight: bold;
        }

        /* PC Selection Styling */
        .pc-selection-header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .pc-selection-header h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .pc-item {
            text-align: center;
            position: relative;
        }
        
        .pc-item input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .pc-label {
            display: block;
            padding: 10px 5px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .pc-available .pc-label {
            background-color: #e8f5e9;
            border-color: #c8e6c9;
            color: #388e3c;
        }
        
        .pc-occupied .pc-label {
            background-color: #ffebee;
            border-color: #ffcdd2;
            color: #d32f2f;
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .pc-label.selected,
        .pc-available input[type="radio"]:checked + .pc-label {
            background-color: #4caf50;
            color: white;
            border-color: #388e3c;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.5);
            transform: scale(1.05);
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
            <a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="computer_control.php"><i class="fas fa-desktop"></i> Computer Control</a>
            <a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a>
            <a href="active_sitin.php"><i class="fas fa-tasks"></i> Active Sit-ins</a>
            <a href="todays_sit_in_records.php"><i class="fas fa-clipboard-list"></i> Today's Records</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Sit-in Reports</a>
            <a href="feedback_reports.php"><i class="fas fa-comments"></i> Feedback Reports</a>
            <a href="add_subject.php"><i class="fas fa-book"></i> Add Subject</a>
            <a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="lab_schedules_admin.php"><i class="fas fa-calendar-alt"></i> Lab Schedules</a>
            <a href="admin_resources.php"><i class="fas fa-book-open"></i> Resources</a>
            <a href="#" id="searchBtn"><i class="fas fa-search"></i> Search</a>
            <a href="#" id="sitInBtn"><i class="fas fa-sign-in-alt"></i> Register Sit-in</a>
            <a href="#" id="studentsBtn"><i class="fas fa-users"></i> Students</a>
        </div>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content">
            <h1>Admin Dashboard</h1>
            
            <!-- Current Sit-in Statistics -->
            <div class="stats-container">
                <?php
                // Query to count active sit-ins
                $activeSitInQuery = "SELECT COUNT(*) as active_count FROM sit_in_requests WHERE is_active = 1";
                $activeSitInResult = $conn->query($activeSitInQuery);
                $activeSitInCount = 0;
                
                if ($activeSitInResult && $activeSitInRow = $activeSitInResult->fetch_assoc()) {
                    $activeSitInCount = $activeSitInRow['active_count'];
                }
                
                // Query to count total students in current sit-ins
                $studentsQuery = "SELECT COUNT(DISTINCT student_id) as student_count FROM sit_in_requests WHERE is_active = 1";
                $studentsResult = $conn->query($studentsQuery);
                $studentCount = 0;
                
                if ($studentsResult && $studentsRow = $studentsResult->fetch_assoc()) {
                    $studentCount = $studentsRow['student_count'];
                }
                
                // Get purpose statistics for pie chart (programming languages)
                $purposeStatsSql = "SELECT purpose, COUNT(*) as count 
                                   FROM sit_in_requests 
                                   WHERE is_active = 1 
                                   GROUP BY purpose";
                $purposeStatsResult = $conn->query($purposeStatsSql);
                $purposeData = [];
                while ($row = $purposeStatsResult->fetch_assoc()) {
                    $purposeData[$row['purpose']] = (int)$row['count'];
                }
                
                // Get lab statistics for pie chart
                $labStatsSql = "SELECT s.lab_number, COUNT(*) as count 
                               FROM sit_in_requests r
                               JOIN subjects s ON r.subject_id = s.id
                               WHERE r.is_active = 1 
                               GROUP BY s.lab_number";
                $labStatsResult = $conn->query($labStatsSql);
                $labData = [];
                while ($row = $labStatsResult->fetch_assoc()) {
                    $labData[$row['lab_number']] = (int)$row['count'];
                }
                ?>
                
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stats-info">
                        <div class="stats-count"><?php echo $activeSitInCount; ?></div>
                        <h3>Active Sit-ins</h3>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="stats-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-info">
                        <div class="stats-count"><?php echo $studentCount; ?></div>
                        <h3>Students In Labs</h3>
                    </div>
                </div>
            </div>
            <!-- End of Current Sit-in Statistics -->
            
            <!-- Charts for Current Sit-ins -->
            <div class="charts-container">
                <!-- Programming Languages Chart -->
                <div class="chart-container">
                    <div class="chart-title">Programming Languages</div>
                    <canvas id="purposeChart" style="margin:10px"></canvas>
                </div>
                
                <!-- Labs Chart -->
                <div class="chart-container">
                    <div class="chart-title">Laboratory Usage</div>
                    <canvas id="labChart" style="margin:10px"></canvas>
                </div>
            </div>
            <!-- End of Charts for Current Sit-ins -->
            
            <!-- Leaderboards Section -->
            <div style="margin-top: 30px;">
                <h2 style="color: #2c3e50; margin-bottom: 20px; text-align: center;">Sit-in Leaderboards</h2>
                
                <div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">
                    <!-- Most Active Students -->
                    <div style="flex: 1; min-width: 300px; max-width: 600px; background: white; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); padding: 20px;">
                        <h3 style="color: #3498db; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 18px;">
                            <i class="fas fa-trophy" style="color: gold; margin-right: 10px;"></i>Most Active Students
                        </h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: #f8f9fa;">
                                    <th style="padding: 12px 8px; text-align: center; border-bottom: 2px solid #eee;">#</th>
                                    <th style="padding: 12px 8px; text-align: left; border-bottom: 2px solid #eee;">Student</th>
                                    <th style="padding: 12px 8px; text-align: center; border-bottom: 2px solid #eee;">Course</th>
                                    <th style="padding: 12px 8px; text-align: center; border-bottom: 2px solid #eee;">Sessions</th>
                                </tr>
                            </thead>
                            <tbody id="mostActiveTbody">
                                <?php
                                if (!empty($activeParticipants)) {
                                    foreach ($activeParticipants as $index => $participant) {
                                        ?>
                                        <tr>
                                            <td style="padding: 12px 8px; text-align: center; border-bottom: 1px solid #eee; font-weight: bold;"><?php echo $index + 1; ?></td>
                                            <td style="padding: 12px 8px; text-align: left; border-bottom: 1px solid #eee;">
                                                <span style="font-weight: bold;"><?php echo $participant['firstname'] . ' ' . $participant['lastname']; ?></span><br>
                                                <span style="font-size: 12px; color: #7f8c8d;">ID: <?php echo $participant['idno']; ?></span>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: center; border-bottom: 1px solid #eee;"><?php echo $participant['course']; ?></td>
                                            <td style="padding: 12px 8px; text-align: center; border-bottom: 1px solid #eee; font-weight: bold; color: #27ae60;"><?php echo $participant['session_count']; ?></td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="4" style="text-align: center; padding: 20px;">No records found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <div style="text-align: right; margin-top: 15px;">
                            <a href="#" id="viewAllActive" style="color: #3498db; text-decoration: none; font-size: 14px;">View All <i class="fas fa-arrow-right" style="font-size: 12px;"></i></a>
                        </div>
                    </div>
                    
                    <!-- Top Performing Students (by hours spent) -->
                    <div style="flex: 1; min-width: 300px; max-width: 600px; background: white; border-radius: 8px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); padding: 20px;">
                        <h3 style="color: #3498db; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; font-size: 18px;">
                            <i class="fas fa-star" style="color: #f1c40f; margin-right: 10px;"></i>Top Performing Students
                        </h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: #f8f9fa;">
                                    <th style="padding: 12px 8px; text-align: center; border-bottom: 2px solid #eee;">#</th>
                                    <th style="padding: 12px 8px; text-align: left; border-bottom: 2px solid #eee;">Student</th>
                                    <th style="padding: 12px 8px; text-align: center; border-bottom: 2px solid #eee;">Course</th>
                                    <th style="padding: 12px 8px; text-align: center; border-bottom: 2px solid #eee;">Hours</th>
                                </tr>
                            </thead>
                            <tbody id="topPerformersTbody">
                                <?php
                                if (!empty($topPerformers)) {
                                    foreach ($topPerformers as $index => $performer) {
                                        ?>
                                        <tr>
                                            <td style="padding: 12px 8px; text-align: center; border-bottom: 1px solid #eee; font-weight: bold;"><?php echo $index + 1; ?></td>
                                            <td style="padding: 12px 8px; text-align: left; border-bottom: 1px solid #eee;">
                                                <span style="font-weight: bold;"><?php echo $performer['firstname'] . ' ' . $performer['lastname']; ?></span><br>
                                                <span style="font-size: 12px; color: #7f8c8d;">ID: <?php echo $performer['idno']; ?></span>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: center; border-bottom: 1px solid #eee;"><?php echo $performer['course']; ?></td>
                                            <td style="padding: 12px 8px; text-align: center; border-bottom: 1px solid #eee; font-weight: bold; color: #9b59b6;"><?php echo $performer['total_hours']; ?></td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr><td colspan="4" style="text-align: center; padding: 20px;">No records found</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <div style="text-align: right; margin-top: 15px;">
                            <a href="#" id="viewAllPerformers" style="color: #3498db; text-decoration: none; font-size: 14px;">View All <i class="fas fa-arrow-right" style="font-size: 12px;"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End of Leaderboards Section -->
            
            <!-- Your dashboard content here -->
        </div>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
        </footer>
    </div>
        
    <!-- Search Modal -->
    <div id="searchModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Search Student</h2>
            <form id="studentSearchForm">
                <div class="form-group">
                    <label for="studentId">Student ID Number:</label>
                    <input type="text" id="studentId" name="studentId" required>
                </div>
                <button type="submit" class="search-btn">Search</button>
            </form>
            <div id="studentResults"></div>
        </div>
    </div>

    <!-- Direct Sit-in Modal -->
    <div id="sitInModal" class="modal">
        <div class="modal-content">
            <span class="close sitInClose">&times;</span>
            <h2>Direct Sit-in Registration</h2>
            <form id="sitInForm">
                <div class="form-group">
                    <label for="sitInStudentId">Student ID Number:</label>
                    <input type="text" id="sitInStudentId" name="studentId" placeholder="Enter student ID" required>
                    <button type="button" id="fetchStudentBtn" class="fetch-btn">Search</button>
                </div>
                
                <div id="studentInfo" style="display: none;">
                    <div class="student-summary" style="background-color: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                        <div class="student-info-details" style="width: 100%;">
                            <p class="student-name" style="font-size: 22px !important; color: #2c3e50; margin-bottom: 15px !important; font-weight: bold;"><span id="studentName"></span></p>
                            <hr style="border: 0; height: 1px; background-color: #ddd; margin: 10px 0;">
                            <p style="font-size: 16px; font-weight: bold; color: #2c3e50; margin: 10px 0;"><strong>ID Number:</strong> <span id="studentIdDisplay"></span></p>
                            <p style="margin: 10px 0;"><strong>Course:</strong> <span id="studentCourse"></span></p>
                            <p style="margin: 10px 0;"><strong>Year:</strong> <span id="studentYear"></span></p>
                            <p class="sessions-count" style="font-size: 16px !important; margin: 10px 0; font-weight: bold;"><strong>Remaining Sessions:</strong> <span id="remainingSessions"></span></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subjectId">Select Laboratory:</label>
                        <select id="subjectId" name="subjectId" required>
                            <option value="">Select a laboratory</option>
                            <?php
                            // Fetch and list all subjects
                            $subjectSql = "SELECT id, subject_name, lab_number FROM subjects";
                            $subjectResult = $conn->query($subjectSql);
                            
                            if ($subjectResult->num_rows > 0) {
                                while($subject = $subjectResult->fetch_assoc()) {
                                    echo '<option value="' . $subject['id'] . '">' . $subject['lab_number'] . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose of Sit-in:</label>
                        <select id="purpose" name="purpose" required>
                            <option value="">Select a purpose</option>
                            <option value="Java">Java</option>
                            <option value="PHP">PHP</option>
                            <option value="ASP.NET">ASP.NET</option>
                            <option value="C#">C#</option>
                            <option value="Python">Python</option>
                            <option value="C Programming">C Programming</option>
                            <option value="Database">Database</option>
                            <option value="Digital & Logic Design">Digital & Logic Design</option>
                            <option value="Embedded Systems & IoT">Embedded Systems & IoT</option>
                            <option value="System Integration & Architecture">System Integration & Architecture</option>
                            <option value="Computer Application">Computer Application</option>
                            <option value="Project Management">Project Management</option>
                            <option value="IT Trends">IT Trends</option>
                            <option value="Technopreneurship">Technopreneurship</option>
                            <option value="Capstone">Capstone</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="otherPurposeGroup" style="display: none;">
                        <label for="otherPurpose">Specify Purpose:</label>
                        <input type="text" id="otherPurpose" name="otherPurpose" placeholder="Please specify purpose">
                    </div>
                    
                    <!-- PC Selection Section -->
                    <div class="form-group">
                        <label for="pc_selection">Select PC Station (Optional):</label>
                        <div id="pc-selection-container">
                            <p>Please select a laboratory first to view available PCs.</p>
                        </div>
                        <input type="hidden" id="selected_pc_number" name="pc_number" value="">
                    </div>
                    
                    <button type="submit" class="submit-btn">Register Sit-in Session</button>
                </div>
            </form>
            <div id="sitInResults"></div>
        </div>
    </div>

    <!-- Students Modal -->
    <div id="studentsModal" class="modal">
        <div class="modal-content" style="width: 80%; max-height: 80vh; overflow-y: auto;">
            <span class="close studentsClose">&times;</span>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>All Registered Students</h2>
                <button id="resetAllSessionsBtn" style="background-color: #e74c3c; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-weight: bold;">Reset All Sessions</button>
            </div>
            <div id="studentsContainer" style="margin-top: 20px;">
                <div class="table-responsive">
                    <table class="table table-striped" style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #3498db; color: white;">
                            <tr>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">ID Number</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Name</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Course</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Year</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Remaining Sessions</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentsList">
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">Loading students...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle Functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.body.classList.toggle('sidebar-active');
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Get the search modal
            var searchModal = document.getElementById("searchModal");
            
            // Get the search button that opens the modal
            var searchBtn = document.getElementById("searchBtn");
            
            // Get the <span> element that closes the search modal
            var searchClose = document.getElementsByClassName("close")[0];
            
            // When the user clicks the search button, open the search modal 
            searchBtn.onclick = function() {
                searchModal.style.display = "block";
            }
            
            // When the user clicks on <span> (x), close the search modal
            searchClose.onclick = function() {
                searchModal.style.display = "none";
            }
            
            // Get the sit-in modal
            var sitInModal = document.getElementById("sitInModal");
            
            // Get the sit-in button that opens the modal
            var sitInBtn = document.getElementById("sitInBtn");
            
            // Get the <span> element that closes the sit-in modal
            var sitInClose = document.getElementsByClassName("sitInClose")[0];
            
            // When the user clicks the sit-in button, open the sit-in modal 
            sitInBtn.onclick = function() {
                sitInModal.style.display = "block";
            }
            
            // When the user clicks on <span> (x), close the sit-in modal
            sitInClose.onclick = function() {
                sitInModal.style.display = "none";
            }
            
            // When the user clicks anywhere outside of the modals, close them
            window.onclick = function(event) {
                if (event.target == searchModal) {
                    searchModal.style.display = "none";
                }
                if (event.target == sitInModal) {
                    sitInModal.style.display = "none";
                }
            }

            // Handle search form submission
            document.getElementById("studentSearchForm").addEventListener("submit", function(e) {
                e.preventDefault();
                var studentId = document.getElementById("studentId").value;
                
                // AJAX request to search for student
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "search_student.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                        document.getElementById("studentResults").innerHTML = this.responseText;
                    }
                }
                xhr.send("studentId=" + studentId);
            });
            
            // Handle fetch student button click for sit-in form
            document.getElementById("fetchStudentBtn").addEventListener("click", function() {
                var studentId = document.getElementById("sitInStudentId").value;
                
                if (!studentId) {
                    alert("Please enter a student ID");
                    return;
                }
                
                // Show loading indicator
                document.getElementById("fetchStudentBtn").textContent = "Loading...";
                document.getElementById("fetchStudentBtn").disabled = true;
                
                // Clear previous results
                document.getElementById("studentInfo").style.display = "none";
                document.getElementById("sitInResults").innerHTML = "";
                
                // AJAX request to fetch student info
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "register_direct_sitin.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE) {
                        // Reset button
                        document.getElementById("fetchStudentBtn").textContent = "Search";
                        document.getElementById("fetchStudentBtn").disabled = false;
                        
                        if (this.status === 200) {
                            try {
                                console.log("Raw response text:", this.responseText);
                                var response = JSON.parse(this.responseText);
                                console.log("Full parsed response:", response);
                                
                                if (response.success) {
                                    // Display student info
                                    var student = response.student;
                                    console.log("FULL STUDENT OBJECT:", student);
                                    
                                    // Format name with optional middle name
                                    document.getElementById("studentName").textContent = student.firstname + " " + (student.middlename ? student.middlename + " " : "") + student.lastname;
                                    
                                    // Fix ID number display - ensure it's always shown
                                    var inputStudentId = document.getElementById("sitInStudentId").value;
                                    console.log("Student ID from database (idno):", student.idno);
                                    console.log("Student ID from input field:", inputStudentId);
                                    
                                    // Use student ID from database if available, otherwise use the input value
                                    var displayId = student.idno || inputStudentId;
                                    document.getElementById("studentIdDisplay").textContent = displayId;
                                    
                                    document.getElementById("studentCourse").textContent = student.course;
                                    document.getElementById("studentYear").textContent = student.year;
                                    
                                    // Always use 30 as default for remaining sessions if not set
                                    var remainingSessions = (student.remaining_sessions !== null && student.remaining_sessions !== undefined) ? 
                                        parseInt(student.remaining_sessions) : 30;
                                    
                                    console.log("Final remaining sessions value:", remainingSessions);
                                    document.getElementById("remainingSessions").textContent = remainingSessions;
                                    
                                    // Check remaining sessions and style accordingly
                                    var remainingSessionsSpan = document.getElementById("remainingSessions");
                                    if (parseInt(remainingSessions) <= 5) {
                                        remainingSessionsSpan.style.color = "#e74c3c"; // Red for low sessions
                                    } else {
                                        remainingSessionsSpan.style.color = "#27ae60"; // Green for enough sessions
                                    }
                                    
                                    document.getElementById("studentInfo").style.display = "block";
                                    
                                    // Auto-scroll to the form
                                    document.getElementById("studentInfo").scrollIntoView({behavior: "smooth"});
                                } else {
                                    alert(response.message);
                                }
                            } catch (e) {
                                console.error("Error parsing JSON:", e, this.responseText);
                                alert("Error fetching student information. Response is not valid JSON.");
                            }
                        } else {
                            console.error("Server error:", this.status);
                            alert("Server error: " + this.status + ". Please try again later.");
                        }
                    }
                };
                
                xhr.onerror = function() {
                    document.getElementById("fetchStudentBtn").textContent = "Fetch Student";
                    document.getElementById("fetchStudentBtn").disabled = false;
                    console.error("Request failed");
                    alert("Network error. Please check your connection and try again.");
                };
                
                xhr.send("action=fetch&studentId=" + studentId);
            });
            
            // Handle purpose dropdown change
            document.getElementById("purpose").addEventListener("change", function() {
                var otherPurposeGroup = document.getElementById("otherPurposeGroup");
                if (this.value === "Other") {
                    otherPurposeGroup.style.display = "block";
                } else {
                    otherPurposeGroup.style.display = "none";
                }
            });
            
            // Handle subject selection change for PC selection
            document.getElementById("subjectId").addEventListener("change", function() {
                var subjectId = this.value;
                
                if (!subjectId) {
                    document.getElementById("pc-selection-container").innerHTML = "<p>Please select a laboratory first to view available PCs.</p>";
                    return;
                }
                
                // Show loading message
                document.getElementById("pc-selection-container").innerHTML = "<p>Loading available PCs...</p>";
                
                // AJAX request to fetch available PCs
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "register_direct_sitin.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                        try {
                            var response = JSON.parse(this.responseText);
                            
                            if (response.success) {
                                renderPCSelectionUI(response);
                            } else {
                                document.getElementById("pc-selection-container").innerHTML = 
                                    "<div style='color: red;'>" + response.message + "</div>";
                            }
                        } catch (e) {
                            console.error("Error parsing JSON:", e, this.responseText);
                            document.getElementById("pc-selection-container").innerHTML = 
                                "<div style='color: red;'>Error loading PC data. Please try again.</div>";
                        }
                    }
                }
                xhr.send("action=fetch_pcs&subjectId=" + subjectId);
            });
            
            // Function to render PC selection UI
            function renderPCSelectionUI(data) {
                const container = document.getElementById("pc-selection-container");
                
                // Create the header
                let html = `
                    <div class="pc-selection-header">
                        <h3>Select a PC for Lab ${data.lab_number}</h3>
                        <p>${data.available_pcs.length} of ${data.total_pcs} PCs available</p>
                    </div>
                    <div class="pc-grid">
                `;
                
                // Create grid of PCs
                for (let i = 1; i <= data.total_pcs; i++) {
                    const isAvailable = data.available_pcs.includes(i);
                    const pcClass = isAvailable ? 'pc-available' : 'pc-occupied';
                    const disabled = isAvailable ? '' : 'disabled';
                    
                    html += `
                        <div class="pc-item ${pcClass}">
                            <input type="radio" name="pc_number_radio" id="pc-${i}" value="${i}" ${disabled}>
                            <label for="pc-${i}" class="pc-label">PC ${i}</label>
                        </div>
                    `;
                }
                
                html += '</div>';
                
                // Render the HTML
                container.innerHTML = html;
                
                // Add event listeners to the radio buttons
                const radioButtons = container.querySelectorAll('input[type="radio"]');
                radioButtons.forEach(radio => {
                    radio.addEventListener('change', function() {
                        // Update the hidden input value
                        document.getElementById("selected_pc_number").value = this.value;
                        
                        // Remove the selected class from all labels
                        document.querySelectorAll('.pc-label').forEach(label => {
                            label.classList.remove('selected');
                        });
                        
                        // Add the selected class to the chosen PC
                        this.nextElementSibling.classList.add('selected');
                    });
                });
            }
            
            // Handle sit-in form submission
            document.getElementById("sitInForm").addEventListener("submit", function(e) {
                e.preventDefault();
                
                var studentId = document.getElementById("sitInStudentId").value;
                var subjectId = document.getElementById("subjectId").value;
                var purpose = document.getElementById("purpose").value;
                var studentName = document.getElementById("studentName").textContent;
                var studentCourse = document.getElementById("studentCourse").textContent;
                var studentYear = document.getElementById("studentYear").textContent;
                var pcNumber = document.getElementById("selected_pc_number").value;
                
                if (purpose === "Other") {
                    purpose = document.getElementById("otherPurpose").value;
                }
                
                if (!studentId || !subjectId || !purpose) {
                    alert("Please fill all required fields");
                    return;
                }
                
                // Clear previous results
                document.getElementById("sitInResults").innerHTML = "";
                
                // Show loading indicator on the button
                var submitBtn = document.querySelector(".submit-btn");
                var originalText = submitBtn.textContent;
                submitBtn.textContent = "Processing...";
                submitBtn.disabled = true;
                
                // AJAX request to register sit-in
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "register_direct_sitin.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                        // Reset button
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                        
                        document.getElementById("sitInResults").innerHTML = this.responseText;
                        
                        // If success message is displayed, reset the form after 3 seconds
                        if (document.querySelector(".success-message")) {
                            setTimeout(function() {
                                document.getElementById("studentInfo").style.display = "none";
                                document.getElementById("sitInForm").reset();
                                document.getElementById("pc-selection-container").innerHTML = "<p>Please select a laboratory first to view available PCs.</p>";
                                document.getElementById("selected_pc_number").value = "";
                            }, 5000);
                        }
                    }
                }
                
                // Make sure to pass the student ID number that was entered
                console.log("Submitting student ID:", studentId);
                
                xhr.send("studentId=" + studentId + "&subjectId=" + subjectId + "&purpose=" + purpose + 
                        "&studentName=" + encodeURIComponent(studentName) + "&studentCourse=" + 
                        encodeURIComponent(studentCourse) + "&studentYear=" + encodeURIComponent(studentYear) +
                        (pcNumber ? "&pc_number=" + encodeURIComponent(pcNumber) : ""));
            });
            
            // Chart.js setup for Purpose Pie Chart
            const purposeCtx = document.getElementById('purposeChart').getContext('2d');
            const purposeData = <?php echo json_encode(array_values($purposeData)); ?>;
            const purposeLabels = <?php echo json_encode(array_keys($purposeData)); ?>;
            
            // Colors for purpose chart
            const purposeColors = [
                '#1abc9c', // Turquoise
                '#3498db', // Blue
                '#e67e22', // Orange 
                '#f1c40f', // Yellow
                '#9b59b6', // Purple
                '#34495e'  // Dark blue
            ];
            
            new Chart(purposeCtx, {
                type: 'pie',
                data: {
                    labels: purposeLabels,
                    datasets: [{
                        data: purposeData,
                        backgroundColor: purposeColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.formattedValue;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.raw / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Chart.js setup for Lab Pie Chart
            const labCtx = document.getElementById('labChart').getContext('2d');
            const labData = <?php echo json_encode(array_values($labData)); ?>;
            const labLabels = <?php echo json_encode(array_keys($labData)); ?>;
            
            // Colors for lab chart
            const labColors = [
                '#ff9ff3', // Pink
                '#feca57', // Yellow
                '#ff6b6b', // Red
                '#1dd1a1', // Green
                '#54a0ff', // Blue
                '#5f27cd'  // Purple
            ];
            
            new Chart(labCtx, {
                type: 'pie',
                data: {
                    labels: labLabels,
                    datasets: [{
                        data: labData,
                        backgroundColor: labColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.formattedValue;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.raw / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Get the students modal
            var studentsModal = document.getElementById("studentsModal");
            
            // Get the students button that opens the modal
            var studentsBtn = document.getElementById("studentsBtn");
            
            // Get the <span> element that closes the students modal
            var studentsClose = document.getElementsByClassName("studentsClose")[0];
            
            // When the user clicks the students button, open the students modal 
            studentsBtn.onclick = function() {
                studentsModal.style.display = "block";
                loadAllStudents();
            }
            
            // When the user clicks on <span> (x), close the students modal
            studentsClose.onclick = function() {
                studentsModal.style.display = "none";
            }
            
            // Update window onclick to include the students modal
            window.onclick = function(event) {
                if (event.target == searchModal) {
                    searchModal.style.display = "none";
                }
                if (event.target == sitInModal) {
                    sitInModal.style.display = "none";
                }
                if (event.target == studentsModal) {
                    studentsModal.style.display = "none";
                }
            }
            
            // Function to load all students - keep only this one, remove any duplicates
            function loadAllStudents() {
                var studentsList = document.getElementById("studentsList");
                studentsList.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Loading students...</td></tr>';
                
                // AJAX request to fetch all students
                var xhr = new XMLHttpRequest();
                xhr.open("GET", "get_all_students.php", true);
                
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE) {
                        if (this.status === 200) {
                            try {
                                var response = JSON.parse(this.responseText);
                                
                                if (response.success) {
                                    displayStudents(response.students);
                                } else {
                                    studentsList.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: red;">' + response.message + '</td></tr>';
                                }
                            } catch (e) {
                                console.error("Error parsing JSON:", e, this.responseText);
                                studentsList.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: red;">Error loading students data</td></tr>';
                            }
                        } else {
                            studentsList.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: red;">Server error: ' + this.status + '. Please try again later.</td></tr>';
                        }
                    }
                };
                
                xhr.onerror = function() {
                    studentsList.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: red;">Network error. Please check your connection and try again.</td></tr>';
                };
                
                xhr.send();
            }
            
            // Function to display students in the table
            function displayStudents(students) {
                var studentsList = document.getElementById("studentsList");
                
                if (students.length === 0) {
                    studentsList.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No students found</td></tr>';
                    return;
                }
                
                studentsList.innerHTML = '';
                
                students.forEach(function(student) {
                    // Handle any null or undefined values
                    var idno = student.idno || '';
                    var firstname = student.firstname || '';
                    var middlename = student.middlename || '';
                    var lastname = student.lastname || '';
                    var course = student.course || '';
                    var year = student.year || '';
                    var remainingSessions = student.remaining_sessions !== null ? student.remaining_sessions : 30;
                    
                    var sessionsColor = parseInt(remainingSessions) <= 5 ? '#e74c3c' : '#27ae60';
                    
                    var row = document.createElement('tr');
                    row.style.borderBottom = '1px solid #ddd';
                    
                    row.innerHTML = `
                        <td style="padding: 10px;">${idno}</td>
                        <td style="padding: 10px;">${firstname} ${middlename ? middlename + ' ' : ''}${lastname}</td>
                        <td style="padding: 10px;">${course}</td>
                        <td style="padding: 10px;">${year}</td>
                        <td style="padding: 10px; color: ${sessionsColor}; font-weight: bold;">${remainingSessions}</td>
                        <td style="padding: 10px;">
                            <button onclick="viewStudentDetails('${student.id}')" style="background-color: #3498db; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">View</button>
                            <button onclick="editStudent('${student.id}')" style="background-color: #f39c12; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">Edit</button>
                            <button onclick="registerSitIn('${student.idno || student.id}')" style="background-color: #2ecc71; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">Sit-In</button>
                            <button onclick="resetStudentSessions('${student.idno || student.id}')" style="background-color: #9b59b6; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">Reset</button>
                            <button onclick="viewStudentPoints('${student.idno || student.id}')" style="background-color: #f1c40f; color: #2c3e50; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Points</button>
                        </td>
                    `;
                    
                    studentsList.appendChild(row);
                });
            }
            
            // Placeholder functions for student actions
            window.viewStudentDetails = function(studentId) {
                alert("View student details functionality will be implemented here for student ID: " + studentId);
            }
            
            window.editStudent = function(studentId) {
                alert("Edit student functionality will be implemented here for student ID: " + studentId);
            }
            
            window.viewStudentPoints = function(studentId) {
                // Open the student points page in a new tab
                window.open('view_student_points.php?student_id=' + studentId, '_blank');
            }
            
            window.registerSitIn = function(studentId) {
                // Close the students modal
                studentsModal.style.display = "none";
                
                // Open the sit-in modal
                sitInModal.style.display = "block";
                
                // Set the student ID in the sit-in form
                document.getElementById("sitInStudentId").value = studentId;
                
                // Trigger the fetch student button click to load student info
                document.getElementById("fetchStudentBtn").click();
            }

            // Function to reset sessions for an individual student
            window.resetStudentSessions = function(studentId) {
                if (confirm("Are you sure you want to reset the sessions for this student to 30?")) {
                    // Show loading feedback
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = "Resetting...";
                    button.disabled = true;
                    
                    // AJAX request to reset sessions
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "reset_sessions.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    
                    xhr.onreadystatechange = function() {
                        if (this.readyState === XMLHttpRequest.DONE) {
                            // Reset button
                            button.textContent = originalText;
                            button.disabled = false;
                            
                            if (this.status === 200) {
                                try {
                                    var response = JSON.parse(this.responseText);
                                    
                                    if (response.success) {
                                        alert("Sessions reset successfully!");
                                        // Reload student list to show updated sessions
                                        loadAllStudents();
                                    } else {
                                        alert("Error: " + response.message);
                                    }
                                } catch (e) {
                                    console.error("Error parsing JSON:", e, this.responseText);
                                    alert("Error processing server response.");
                                }
                            } else {
                                alert("Server error: " + this.status + ". Please try again later.");
                            }
                        }
                    };
                    
                    xhr.send("action=reset_individual&student_id=" + studentId);
                }
            }

            // Event listener for Reset All Sessions button
            document.getElementById("resetAllSessionsBtn").addEventListener("click", function() {
                if (confirm("WARNING: This will reset sessions for ALL students to 30. This action cannot be undone. Continue?")) {
                    // Show loading feedback
                    const button = this;
                    const originalText = button.textContent;
                    button.textContent = "Resetting All...";
                    button.disabled = true;
                    
                    // AJAX request to reset all sessions
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", "reset_sessions.php", true);
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    
                    xhr.onreadystatechange = function() {
                        if (this.readyState === XMLHttpRequest.DONE) {
                            // Reset button
                            button.textContent = originalText;
                            button.disabled = false;
                            
                            if (this.status === 200) {
                                try {
                                    var response = JSON.parse(this.responseText);
                                    
                                    if (response.success) {
                                        alert("All students' sessions have been reset to 30!");
                                        // Reload student list to show updated sessions
                                        loadAllStudents();
                                    } else {
                                        alert("Error: " + response.message);
                                    }
                                } catch (e) {
                                    console.error("Error parsing JSON:", e, this.responseText);
                                    alert("Error processing server response.");
                                }
                            } else {
                                alert("Server error: " + this.status + ". Please try again later.");
                            }
                        }
                    };
                    
                    xhr.send("action=reset_all");
                }
            });

            // Handle leaderboard "View All" clicks
            document.getElementById("viewAllActive").addEventListener("click", function(e) {
                e.preventDefault();
                alert("This will show the complete list of most active sit-in participants.");
                // This could open a modal or navigate to a dedicated page
            });
            
            document.getElementById("viewAllPerformers").addEventListener("click", function(e) {
                e.preventDefault();
                alert("This will show the complete list of top performing sit-in participants.");
                // This could open a modal or navigate to a dedicated page
            });
        });
    </script>
</body>
</html>
