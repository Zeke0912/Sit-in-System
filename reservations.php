<?php
// Step 1: Connect to the database
$servername = "localhost";
$username = "root";  // Replace with your username
$password = "";      // Replace with your password
$dbname = "my_database";  // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the student ID from the session (assuming it's stored there)
session_start();

// Check if the user is logged in as a student
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Redirect to login if not logged in
    exit();
}

$student_id = $_SESSION['user_id'];
$message = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reserve'])) {
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $lab_number = $conn->real_escape_string($_POST['lab_number']);
    $date = $conn->real_escape_string($_POST['date']);
    $time_in = $conn->real_escape_string($_POST['time_in']);
    $pc_number = isset($_POST['selected_pc_number']) ? intval($_POST['selected_pc_number']) : null;
    
    // Get the student's details
    $studentSql = "SELECT firstname, lastname, course, year, remaining_sessions FROM users WHERE idno = ?";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->bind_param("s", $student_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $student = $studentResult->fetch_assoc();
    $studentStmt->close();
    
    // Check if student has remaining sessions
    if (!isset($student['remaining_sessions'])) {
        // If remaining_sessions doesn't exist, calculate based on course
        $usedSql = "SELECT COUNT(*) as used FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
        $usedStmt = $conn->prepare($usedSql);
        $usedStmt->bind_param("s", $student_id);
        $usedStmt->execute();
        $usedResult = $usedStmt->get_result();
        $usedRow = $usedResult->fetch_assoc();
        $usedStmt->close();
        
        $max_sessions = (in_array($student['course'], ['BSIT', 'BSCS'])) ? 30 : 15;
        $remaining_sessions = $max_sessions - $usedRow['used'];
    } else {
        $remaining_sessions = $student['remaining_sessions'];
    }
    
    if ($remaining_sessions <= 0) {
        $message = "<div class='error-message'>You have no remaining sessions. Please contact an administrator.</div>";
    } else {
        // Format datetime for start_time
        $datetime = $date . ' ' . $time_in . ':00';
        
        // Find the subject ID for the selected lab
        $subjectSql = "SELECT id FROM subjects WHERE lab_number = ? LIMIT 1";
        $subjectStmt = $conn->prepare($subjectSql);
        $subjectStmt->bind_param("s", $lab_number);
        $subjectStmt->execute();
        $subjectResult = $subjectStmt->get_result();
        $subject = $subjectResult->fetch_assoc();
        $subject_id = $subject ? $subject['id'] : null;
        $subjectStmt->close();
        
        if (!$subject_id) {
            $message = "<div class='error-message'>Error: Could not find subject for the selected lab.</div>";
        } else {
            // Create a direct sit-in request with PC number
            $insertSql = "INSERT INTO sit_in_requests (student_id, subject_id, purpose, start_time, status, lab_number, pc_number) 
                        VALUES (?, ?, ?, ?, 'pending', ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("sisssi", $student_id, $subject_id, $purpose, $datetime, $lab_number, $pc_number);
            
            if ($insertStmt->execute()) {
                $message = "<div class='success-message'>Your sit-in request has been submitted and is pending approval.</div>";
            } else {
                $message = "<div class='error-message'>Error: " . $insertStmt->error . "</div>";
            }
            $insertStmt->close();
        }
    }
}

// Get user information
$userSql = "SELECT idno, firstname, lastname, course, year FROM users WHERE idno = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("s", $student_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

// Get remaining sessions count
$max_sessions = (in_array($user['course'], ['BSIT', 'BSCS'])) ? 30 : 15;
$usedSql = "SELECT COUNT(*) as used FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
$usedStmt = $conn->prepare($usedSql);
$usedStmt->bind_param("s", $student_id);
$usedStmt->execute();
$usedResult = $usedStmt->get_result();
$usedRow = $usedResult->fetch_assoc();
$remaining_sessions = $max_sessions - $usedRow['used'];
$usedStmt->close();

// Get available subjects instead of just labs
$subjectsSql = "SELECT id, lab_number FROM subjects ORDER BY lab_number ASC";
$subjectsResult = $conn->query($subjectsSql);
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row;
}

// Get pending and recent requests - Updated to include PC number
$requestsSql = "SELECT r.id, r.purpose, DATE(r.start_time) AS date_requested, r.start_time, r.status, 
                r.lab_number, r.pc_number 
                FROM sit_in_requests r
                WHERE r.student_id = ?
                ORDER BY r.start_time DESC
                LIMIT 10";
$requestsStmt = $conn->prepare($requestsSql);
$requestsStmt->bind_param("s", $student_id);
$requestsStmt->execute();
$requestsResult = $requestsStmt->get_result();
$requests = [];
while ($row = $requestsResult->fetch_assoc()) {
    $requests[] = $row;
}
$requestsStmt->close();

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in Reservation</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Add flatpickr for better date/time picking -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Add PC selection styles -->
    <link rel="stylesheet" href="css/pc_selection.css">
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
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            margin: 80px auto 20px;
            padding: 0 20px;
            flex: 1;
        }

        /* Card design for forms and containers */
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
            transform: translateY(-3px);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 25px;
            position: relative;
        }

        .card-header h2 {
            margin: 0;
            font-size: 22px;
            display: flex;
            align-items: center;
        }

        .card-header h2 i {
            margin-right: 10px;
            font-size: 24px;
        }

        .card-body {
            padding: 25px;
        }

        /* Student info card */
        .student-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            border: 1px solid #e9ecef;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            box-shadow: var(--shadow);
        }

        .student-details {
            flex: 1;
            min-width: 200px;
        }

        .student-details h3 {
            margin: 0 0 10px 0;
            color: var(--secondary-color);
            font-size: 22px;
        }

        .student-details p {
            margin: 5px 0;
            font-size: 16px;
            color: #555;
            display: flex;
            align-items: center;
        }

        .student-details p i {
            width: 20px;
            margin-right: 10px;
            color: var(--primary-color);
        }

        .session-counter {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            min-width: 120px;
        }

        .session-count {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
            color: <?php echo ($remaining_sessions <= 5) ? 'var(--danger-color)' : 'var(--success-color)'; ?>;
        }

        .session-label {
            font-size: 14px;
            color: #777;
            text-align: center;
        }

        /* Form styling */
        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: var(--secondary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--secondary-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }

        /* Make date/time pickers larger and more prominent */
        input[type="date"], 
        input[type="time"],
        .flatpickr-input {
            height: 50px;
            font-size: 16px;
            cursor: pointer;
            background-color: #f8f9fa;
        }

        select.form-control {
            height: 50px;
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23333" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px;
            padding-right: 30px;
        }

        /* Button styling */
        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 12px 20px;
            font-size: 16px;
            line-height: 1.5;
            border-radius: 8px;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-primary {
            color: white;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        /* Status badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }

        .badge-pending {
            background-color: var(--warning-color);
        }

        .badge-approved {
            background-color: var(--success-color);
        }

        .badge-rejected {
            background-color: var(--danger-color);
        }

        /* Table styling */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        .table th, 
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            font-weight: 600;
            color: var(--secondary-color);
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }

        .table tr:hover {
            background-color: #f8f9fa;
        }

        /* Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-weight: 500;
            position: relative;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .container {
                padding: 0 15px;
                margin-top: 60px;
            }
            
            .student-info {
                flex-direction: column;
                text-align: center;
            }
            
            .student-details p {
                justify-content: center;
            }
            
            .session-counter {
                width: 100%;
            }
            
            .card-body {
                padding: 15px;
            }
        }

        /* Calendar icon styling for the date picker */
        .date-icon {
            position: relative;
        }

        .date-icon i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            pointer-events: none;
        }

        /* Animation for the success checkmark */
        @keyframes checkmark {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-checkmark {
            display: inline-block;
            animation: checkmark 0.5s ease-in-out forwards;
        }
        
        /* Success Message Animation */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Error Message Animation */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Navigation Bar -->
<nav>
    <ul>
        <li><a href="home.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="reservations.php" class="active"><i class="fas fa-calendar-alt"></i> Reservations</a></li>
        <li><a href="student_sit_in_records.php"><i class="fas fa-history"></i> My Records</a></li>
        <li><a href="redeem_points.php"><i class="fas fa-star"></i> Redeem Points</a></li>
        <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li><a href="edit_profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
    </ul>
    <div class="logout-container">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- Page Content -->
<div class="container">
    <?php echo $message; ?>
    
    <!-- Reservation Form Card -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-calendar-plus"></i> Request Sit-in Session</h2>
        </div>
        <div class="card-body">
            <!-- Student Information -->
            <div class="student-info">
                <div class="student-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="student-details">
                    <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
                    <p><i class="fas fa-id-card"></i> <strong>ID:</strong> <?php echo htmlspecialchars($user['idno']); ?></p>
                    <p><i class="fas fa-graduation-cap"></i> <strong>Course:</strong> <?php echo htmlspecialchars($user['course']); ?></p>
                    <p><i class="fas fa-user-graduate"></i> <strong>Year:</strong> <?php echo htmlspecialchars($user['year']); ?></p>
                </div>
                <div class="session-counter">
                    <div class="session-count"><?php echo $remaining_sessions; ?></div>
                    <div class="session-label">Remaining Sessions</div>
                </div>
            </div>
            
            <!-- Reservation Form -->
            <form method="POST" action="" id="reservationForm">
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Sit-in Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="purpose">Purpose of Visit:</label>
                            <select id="purpose" name="purpose" class="form-control" required>
                                <option value="">Select Purpose</option>
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
                            <label for="subject_id">Laboratory Room:</label>
                            <select id="subject_id" name="subject_id" class="form-control" required>
                                <option value="">Select Laboratory</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo htmlspecialchars($subject['id']); ?>"><?php echo htmlspecialchars($subject['lab_number']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="lab_number" id="selected_lab_number" value="">
                        </div>
                    </div>
                </div>
                
                <!-- PC Selection Section -->
                <div class="form-section">
                    <h3><i class="fas fa-desktop"></i> Select PC</h3>
                    <div id="pc-selection-container">
                        <p>Please select a laboratory first to view available PCs.</p>
                    </div>
                    <input type="hidden" name="selected_pc_number" id="selected_pc_number" value="">
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-clock"></i> Scheduling</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date">Date of Visit:</label>
                            <div class="date-icon">
                                <input type="text" id="date" name="date" class="form-control datepicker" required placeholder="Select Date">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="time_in">Preferred Time:</label>
                            <div class="date-icon">
                                <input type="text" id="time_in" name="time_in" class="form-control timepicker" required placeholder="Select Time">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="reserve" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> Submit Reservation Request
                </button>
            </form>
        </div>
    </div>
    
    <!-- Recent Requests Card -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-history"></i> Recent Requests</h2>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div style="text-align: center; padding: 30px;">
                    <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                    <p style="color: #777; font-size: 18px;">You haven't made any sit-in requests yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Purpose</th>
                                <th>Laboratory</th>
                                <th>PC#</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['date_requested']); ?></td>
                                    <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($request['lab_number']); ?></td>
                                    <td><?php echo $request['pc_number'] ? htmlspecialchars($request['pc_number']) : 'N/A'; ?></td>
                                    <td><?php echo date('h:i A', strtotime($request['start_time'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($request['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
</footer>

<script src="js/pc_selection.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize date picker with better UI
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            minDate: "today",
            disableMobile: "true",
            altInput: true,
            altFormat: "F j, Y",
            animate: true
        });
        
        // Initialize time picker
        flatpickr(".timepicker", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            minuteIncrement: 15,
            disableMobile: "true"
        });
        
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            var messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(function(message) {
                message.style.display = 'none';
            });
        }, 5000);
        
        // Update hidden lab_number field when subject is selected
        document.getElementById('subject_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const labNumber = selectedOption.text;
                document.getElementById('selected_lab_number').value = labNumber;
            }
        });
    });
</script>

</body>
</html>