<?php
include 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get all lab rooms
$lab_query = "SELECT DISTINCT lab_number FROM subjects ORDER BY lab_number";
$lab_result = $conn->query($lab_query);
$labs = [];

if ($lab_result && $lab_result->num_rows > 0) {
    while ($row = $lab_result->fetch_assoc()) {
        $labs[] = $row['lab_number'];
    }
}

// If no labs found, create a message
if (empty($labs)) {
    $no_labs_message = "No laboratory rooms are currently available in the system.";
}

// Get selected lab or default to first one
$selected_lab = isset($_GET['lab']) ? $_GET['lab'] : (empty($labs) ? '' : $labs[0]);

// Define days
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Get scheduled classes for selected lab - exclude placeholder records
$schedule = [];
if (!empty($selected_lab)) {
    $schedule_query = "SELECT s.subject_name, s.lab_number, s.date, s.start_time, s.end_time, 
                      i.firstname, i.lastname
                      FROM subjects s 
                      LEFT JOIN instructor_names i ON s.instructor_id = i.id 
                      WHERE s.lab_number = ? 
                      AND NOT (s.subject_name = 'Available' AND s.start_time = '00:00:00')
                      ORDER BY DAYOFWEEK(s.date), s.start_time";
    $stmt = $conn->prepare($schedule_query);
    $stmt->bind_param("s", $selected_lab);
    $stmt->execute();
    $schedule_result = $stmt->get_result();
    
    // Create schedule array indexed by day of week
    while ($row = $schedule_result->fetch_assoc()) {
        $day_of_week = date('l', strtotime($row['date']));
        if (!isset($schedule[$day_of_week])) {
            $schedule[$day_of_week] = [];
        }
        $schedule[$day_of_week][] = [
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'subject' => $row['subject_name'],
            'instructor' => $row['firstname'] . ' ' . $row['lastname']
        ];
    }
}

// Get purposes for dropdown from hardcoded list and database
$purposes = ['Java', 'PHP', 'ASP.NET', 'C#', 'Python', 'C Programming',
    'Database', 'Digital & Logic Design', 'Embedded Systems & IoT',
    'System Integration & Architecture', 'Computer Application',
    'Project Management', 'IT Trends', 'Technopreneurship', 'Capstone', 'Other'];

// Also try to get purposes from database as backup
$db_purposes = [];
$purpose_query = "SELECT DISTINCT purpose FROM sit_in_requests WHERE purpose IS NOT NULL AND purpose != '' ORDER BY purpose";
$purpose_result = $conn->query($purpose_query);

if ($purpose_result && $purpose_result->num_rows > 0) {
    while ($row = $purpose_result->fetch_assoc()) {
        if (!empty($row['purpose'])) {
            $db_purposes[] = $row['purpose'];
        }
    }
    
    // Only use database purposes if we found a significant number
    if (count($db_purposes) > 5) {
        $purposes = $db_purposes;
    }
}

// Process sit-in request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_sitin'])) {
    $user_id = $_SESSION['user_id'];
    $lab_number = $_POST['lab_number'];
    $purpose = $_POST['purpose'];
    $day = $_POST['day'];
    $time_slot = $_POST['time_slot'];
    
    $insert_query = "INSERT INTO sit_in_requests (student_id, lab_number, purpose, status) 
                     VALUES (?, ?, ?, 'pending')";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("iss", $user_id, $lab_number, $purpose);
    
    if ($insert_stmt->execute()) {
        $success_message = "Your sit-in request has been submitted successfully!";
    } else {
        $error_message = "Error submitting request: " . $conn->error;
    }
}

// Format time helper function
function format_time($time) {
    return date('h:i A', strtotime($time));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Schedule</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #4895ef;
            --success: #4cc9f0;
            --info: #f72585;
            --warning: #ffd166;
            --danger: #ef476f;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .page-title {
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .page-title:after {
            content: '';
            position: absolute;
            width: 50px;
            height: 4px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            bottom: 0;
            left: 0;
            border-radius: 2px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.06);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 18px 20px;
        }
        
        .card-title {
            margin-bottom: 0;
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .lab-selector {
            padding: 15px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 10px 15px;
            height: auto;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--secondary);
            border-color: var(--secondary);
        }
        
        .btn-secondary:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0;
        }
        
        .schedule-table th, 
        .schedule-table td {
            border: 1px solid rgba(0,0,0,0.05);
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        .schedule-table th {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border: none;
            text-align: center;
        }
        
        .schedule-cell {
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .schedule-cell.has-class {
            border-left: 3px solid var(--info);
            background-color: rgba(76, 201, 240, 0.05);
        }
        
        .subject-name {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }
        
        .instructor-name {
            color: var(--info);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .schedule-time {
            font-size: 0.8rem;
            color: var(--dark);
            opacity: 0.7;
        }
        
        .no-schedule {
            text-align: center;
            color: #999;
            font-style: italic;
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            border: none;
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.15);
            color: var(--success);
        }
        
        .alert-danger {
            background-color: rgba(239, 71, 111, 0.15);
            color: var(--danger);
        }
        
        @media (max-width: 768px) {
            .schedule-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="page-title">Laboratory Schedule</h1>
            </div>
            <div class="col-md-4 text-right">
                <a href="home.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Home
                </a>
            </div>
        </div>
        
        <?php if (isset($no_labs_message)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $no_labs_message; ?>
            </div>
        <?php else: ?>
            <!-- Lab Selector Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-flask mr-2"></i>Select Laboratory</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="form-inline justify-content-center">
                        <div class="form-group mr-3">
                            <label for="lab" class="mr-2">Laboratory Room:</label>
                            <select name="lab" id="lab" class="form-control" onchange="this.form.submit()">
                                <?php foreach ($labs as $lab): ?>
                                    <option value="<?php echo htmlspecialchars($lab); ?>" 
                                        <?php echo ($lab == $selected_lab) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lab); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Schedule Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        Schedule for Laboratory <?php echo htmlspecialchars($selected_lab); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Check if this lab has only placeholder data
                    $placeholder_check = "SELECT COUNT(*) as count FROM subjects WHERE lab_number = ? AND subject_name = 'Available' AND start_time = '00:00:00'";
                    $placeholder_stmt = $conn->prepare($placeholder_check);
                    $placeholder_stmt->bind_param("s", $selected_lab);
                    $placeholder_stmt->execute();
                    $placeholder_result = $placeholder_stmt->get_result();
                    $placeholder_data = $placeholder_result->fetch_assoc();
                    
                    $total_check = "SELECT COUNT(*) as count FROM subjects WHERE lab_number = ?";
                    $total_stmt = $conn->prepare($total_check);
                    $total_stmt->bind_param("s", $selected_lab);
                    $total_stmt->execute();
                    $total_result = $total_stmt->get_result();
                    $total_data = $total_result->fetch_assoc();
                    
                    if ($placeholder_data['count'] > 0 && $placeholder_data['count'] == $total_data['count']) {
                        echo '<div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> 
                                This laboratory currently has no scheduled classes. All time slots are available for sit-in requests.
                              </div>';
                    }
                    ?>
                    
                    <div class="table-responsive">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th style="width: 16%">Day</th>
                                    <th style="width: 84%">Scheduled Classes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $day): ?>
                                    <tr>
                                        <td class="font-weight-bold text-center">
                                            <?php echo $day; ?>
                                        </td>
                                        <td class="schedule-cell <?php echo (isset($schedule[$day]) && !empty($schedule[$day])) ? 'has-class' : ''; ?>">
                                            <?php if (isset($schedule[$day]) && !empty($schedule[$day])): ?>
                                                <div class="row">
                                                    <?php foreach ($schedule[$day] as $class): ?>
                                                        <div class="col-md-6 mb-3">
                                                            <div class="p-3 border rounded">
                                                                <div class="subject-name"><?php echo htmlspecialchars($class['subject']); ?></div>
                                                                <div class="instructor-name">
                                                                    <i class="fas fa-user-tie mr-1"></i>
                                                                    <?php echo htmlspecialchars($class['instructor']); ?>
                                                                </div>
                                                                <div class="schedule-time">
                                                                    <i class="far fa-clock mr-1"></i>
                                                                    <?php echo format_time($class['start_time']) . ' - ' . format_time($class['end_time']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="no-schedule">
                                                    <i class="fas fa-calendar-times mr-2"></i>No scheduled classes for this day
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Information card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle mr-2"></i>Sit-In Information
                    </h5>
                </div>
                <div class="card-body">
                    <p>To request a sit-in session for this laboratory, please contact the laboratory administrator or your instructor directly. Provide them with the following information:</p>
                    <ul>
                        <li>Your name and student ID</li>
                        <li>The laboratory room number</li>
                        <li>Your preferred day and time</li>
                        <li>The purpose of your sit-in request</li>
                    </ul>
                    <p>Sit-in requests are subject to approval based on laboratory availability and class schedules.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 