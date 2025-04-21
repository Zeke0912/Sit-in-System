<?php
// Step 1: Check if the user is logged in and is an admin
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");  // Redirect to login page if not logged in
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";  // Replace with your database name

// Step 2: Create connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get existing lab numbers
$labQuery = "SELECT DISTINCT lab_number FROM subjects ORDER BY lab_number";
$labResult = $conn->query($labQuery);
$existingLabs = [];
if ($labResult && $labResult->num_rows > 0) {
    while ($row = $labResult->fetch_assoc()) {
        $existingLabs[] = $row['lab_number'];
    }
}
// Add the new lab 517 to the list if it doesn't exist
if (!in_array('517', $existingLabs)) {
    $existingLabs[] = '517';
}
// Sort the labs numerically
sort($existingLabs);

// Step 3: Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $purpose = $_POST['purpose'];
    $lab_number = $_POST['lab_number'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $sessions = $_POST['sessions'];

    // Step 4: Insert data into the subjects table, with default status as 'available'
    $sql = "INSERT INTO subjects (subject_name, lab_number, date, start_time, end_time, sessions, status)
            VALUES (?, ?, ?, ?, ?, ?, 'available')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $purpose, $lab_number, $date, $start_time, $end_time, $sessions);
    
    if ($stmt->execute()) {
        $success_message = "New subject added successfully with status 'available'!";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

// Step 5: Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subject</title>
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

        /* Form Styles */
        .form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }

        .form-title {
            color: #2980b9;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .btn-submit {
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: #3498db;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
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
            .form-container {
                width: 100%;
                padding: 20px;
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
            <a href="active_sitin.php"><i class="fas fa-user-clock"></i> Active Sit-ins</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Sit-in Reports</a>
            <a href="add_subject.php" class="active"><i class="fas fa-book"></i> Add Subject</a>
            <a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
        </div>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content">
            <h1>Add New Subject</h1>

            <div class="form-container">
                <div class="form-title">Create a new subject for sit-in sessions</div>
                
                <?php if(isset($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="add_subject.php">
                    <div class="form-group">
                        <label for="purpose">Purpose</label>
                        <select id="purpose" name="purpose" class="form-control" required>
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

                    <div class="form-group">
                        <label for="lab_number">Lab Number</label>
                        <select id="lab_number" name="lab_number" class="form-control" required>
                            <?php foreach ($existingLabs as $lab): ?>
                                <option value="<?php echo htmlspecialchars($lab); ?>"><?php echo htmlspecialchars($lab); ?></option>
                            <?php endforeach; ?>
                            <!-- Custom option for new lab entry -->
                            <option value="custom">Add Custom Lab</option>
                        </select>
                        <div id="custom-lab-container" style="display: none; margin-top: 10px;">
                            <input type="text" id="custom_lab" placeholder="Enter custom lab number" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="sessions">Sessions</label>
                        <input type="number" id="sessions" name="sessions" class="form-control" required>
                    </div>

                    <button type="submit" class="btn-submit">Add Subject</button>
                </form>
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
        
        // Custom lab input logic
        document.getElementById('lab_number').addEventListener('change', function() {
            const customLabContainer = document.getElementById('custom-lab-container');
            const customLabInput = document.getElementById('custom_lab');
            
            if (this.value === 'custom') {
                customLabContainer.style.display = 'block';
                customLabInput.setAttribute('required', 'required');
                
                // Add an event listener to update the hidden input when custom value changes
                customLabInput.addEventListener('input', function() {
                    // Create or update a hidden input with the custom value
                    let hiddenInput = document.querySelector('input[name="lab_number"]');
                    if (!hiddenInput) {
                        hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'lab_number';
                        document.querySelector('form').appendChild(hiddenInput);
                    }
                    hiddenInput.value = this.value;
                });
            } else {
                customLabContainer.style.display = 'none';
                customLabInput.removeAttribute('required');
            }
        });
    </script>
</body>
</html>
