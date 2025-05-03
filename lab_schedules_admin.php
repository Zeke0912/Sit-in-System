<?php
include 'connection.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get all lab rooms - only select distinct lab numbers
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
    $schedule_query = "SELECT s.id, s.subject_name, s.lab_number, s.date, s.start_time, s.end_time, 
                      i.firstname, i.lastname, i.id AS instructor_id 
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
            'id' => $row['id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'subject' => $row['subject_name'],
            'instructor' => $row['firstname'] . ' ' . $row['lastname'],
            'instructor_id' => $row['instructor_id']
        ];
    }
}

// Get all instructors for dropdown from instructor_names table
$instructor_query = "SELECT id, firstname, lastname FROM instructor_names ORDER BY firstname, lastname";
$instructor_result = $conn->query($instructor_query);
$instructors = [];

if ($instructor_result && $instructor_result->num_rows > 0) {
    while ($row = $instructor_result->fetch_assoc()) {
        $instructors[] = $row;
    }
}

// FIXED: Force set the purposes to match the screenshot
$purposes = [
    'Java', 'PHP', 'ASP.NET', 'C#', 'Python', 'C Programming',
    'Database', 'Digital & Logic Design', 'Embedded Systems & IoT',
    'System Integration & Architecture', 'Computer Application',
    'Project Management', 'IT Trends', 'Technopreneurship', 'Capstone', 'Other'
];

// Also try to get from database as backup
$db_purposes = [];
$purpose_query = "SELECT DISTINCT purpose FROM sit_in_requests WHERE purpose IS NOT NULL AND purpose != '' ORDER BY purpose";
$purpose_result = $conn->query($purpose_query);

if ($purpose_result && $purpose_result->num_rows > 0) {
    while ($row = $purpose_result->fetch_assoc()) {
        if (!empty($row['purpose'])) {
            $db_purposes[] = $row['purpose'];
        }
    }
    
    // Only use database purposes if we actually found some
    if (count($db_purposes) > 5) {
        $purposes = $db_purposes;
    }
}

// Handle adding new schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $subject_name = $_POST['purpose']; // Changed from subject_name to purpose
    $lab_number = $_POST['lab_number'];
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $instructor_id = $_POST['instructor_id'];
    
    // Convert day to date (next occurrence of the day)
    $today = date('Y-m-d');
    $this_week_day = date('Y-m-d', strtotime("next {$day}", strtotime($today)));
    
    // Insert new schedule
    $insert_query = "INSERT INTO subjects (subject_name, lab_number, date, start_time, end_time, instructor_id, sessions, status) 
                     VALUES (?, ?, ?, ?, ?, ?, 1, 'available')";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("sssssi", $subject_name, $lab_number, $this_week_day, $start_time, $end_time, $instructor_id);
    
    if ($insert_stmt->execute()) {
        $success_message = "Schedule added successfully!";
        // Refresh the page to show updated schedule
        header("Location: lab_schedules_admin.php?lab=" . urlencode($lab_number));
        exit();
    } else {
        $error_message = "Error adding schedule: " . $conn->error;
    }
}

// Handle delete schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $schedule_id = $_POST['schedule_id'];
    
    // First, get the lab number for this schedule
    $check_query = "SELECT lab_number FROM subjects WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $schedule_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($row = $check_result->fetch_assoc()) {
        $lab_number = $row['lab_number'];
        
        // Delete this specific schedule
        $delete_query = "DELETE FROM subjects WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $schedule_id);
        
        if ($delete_stmt->execute()) {
            // Check if we still have schedules for this lab
            $count_query = "SELECT COUNT(*) as count FROM subjects 
                           WHERE lab_number = ? 
                           AND NOT (subject_name = 'Available' AND start_time = '00:00:00')";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param("s", $lab_number);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row = $count_result->fetch_assoc();
            
            // If we have no real schedules
            if ($count_row['count'] == 0) {
                // Check if we already have a placeholder
                $placeholder_query = "SELECT COUNT(*) as count FROM subjects 
                                    WHERE lab_number = ? 
                                    AND subject_name = 'Available' 
                                    AND start_time = '00:00:00'";
                $placeholder_stmt = $conn->prepare($placeholder_query);
                $placeholder_stmt->bind_param("s", $lab_number);
                $placeholder_stmt->execute();
                $placeholder_result = $placeholder_stmt->get_result();
                $placeholder_row = $placeholder_result->fetch_assoc();
                
                // Only add placeholder if none exists
                if ($placeholder_row['count'] == 0) {
                    // Add a placeholder record to keep the lab in the system
                    $placeholder_insert = "INSERT INTO subjects 
                                        (subject_name, lab_number, date, start_time, end_time, instructor_id, sessions, status) 
                                        VALUES ('Available', ?, CURDATE(), '00:00:00', '00:00:00', 0, 0, 'available')";
                    $placeholder_stmt = $conn->prepare($placeholder_insert);
                    $placeholder_stmt->bind_param("s", $lab_number);
                    $placeholder_stmt->execute();
                }
            }
            
            $success_message = "Schedule deleted successfully!";
            
            // Redirect to the lab's page
            header("Location: lab_schedules_admin.php?lab=" . urlencode($lab_number));
            exit();
        } else {
            $error_message = "Error deleting schedule: " . $conn->error;
        }
    } else {
        $error_message = "Schedule not found!";
    }
}

// Format time helper function
function format_time($time) {
    return date('h:i A', strtotime($time));
}

// Check if time slot is occupied
function is_time_slot_occupied($time_slot, $day_schedule) {
    if (empty($day_schedule)) return ['occupied' => false];
    
    $current = strtotime($time_slot);
    foreach ($day_schedule as $class) {
        $start = strtotime($class['start_time']);
        $end = strtotime($class['end_time']);
        if ($current >= $start && $current < $end) {
            return [
                'occupied' => true,
                'class' => $class
            ];
        }
    }
    return ['occupied' => false];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Schedule Management</title>
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
        
        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .schedule-table th, 
        .schedule-table td {
            border: 1px solid rgba(0,0,0,0.05);
            padding: 12px 15px;
        }
        
        .schedule-table th {
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .time-column {
            background: var(--primary);
            color: white;
            font-weight: 500;
            text-align: center;
            width: 120px;
        }
        
        .schedule-cell {
            height: 100px;
            position: relative;
            transition: all 0.3s ease;
            vertical-align: middle;
            text-align: center;
        }
        
        .vacant-cell {
            background-color: rgba(76, 201, 240, 0.1);
        }
        
        .occupied-cell {
            background-color: white;
            border-left: 3px solid var(--info);
            border-radius: 8px;
        }
        
        .occupied-cell .cell-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: left;
            padding: 10px;
            position: relative;
        }
        
        .delete-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .occupied-cell:hover .delete-btn {
            opacity: 1;
        }
        
        .subject-name {
            font-weight: 600;
            color: var(--dark);
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
            
            .schedule-cell {
                height: 80px;
                font-size: 0.9rem;
            }
            
            .time-column {
                width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="page-title">Laboratory Schedule Management</h1>
            </div>
            <div class="col-md-4 text-right">
                <a href="admin_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
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
                                This lab currently has no scheduled classes. Use the form below to add a schedule.
                              </div>';
                    }
                    ?>
                    
                    <div class="table-responsive">
                        <?php if (empty($schedule)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> 
                                This lab currently has no scheduled classes. Use the form below to add a schedule.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($days as $day): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="card-title mb-0"><i class="fas fa-calendar-day mr-2"></i><?php echo $day; ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <?php if (isset($schedule[$day]) && !empty($schedule[$day])): ?>
                                                    <ul class="list-group">
                                                        <?php foreach ($schedule[$day] as $class): ?>
                                                            <li class="list-group-item">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <div class="font-weight-bold"><?php echo htmlspecialchars($class['subject']); ?></div>
                                                                        <div><i class="far fa-clock mr-1"></i><?php echo format_time($class['start_time']); ?> - <?php echo format_time($class['end_time']); ?></div>
                                                                        <div><i class="fas fa-user-tie mr-1"></i><?php echo htmlspecialchars($class['instructor']); ?></div>
                                                                    </div>
                                                                    <div>
                                                                        <form method="post" class="delete-schedule-form">
                                                                            <input type="hidden" name="schedule_id" value="<?php echo $class['id']; ?>">
                                                                            <button type="submit" name="delete_schedule" class="btn btn-sm btn-danger" 
                                                                                    onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                                                <i class="fas fa-trash-alt"></i>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <div class="text-center text-muted py-4">
                                                        <i class="fas fa-calendar-times mb-3" style="font-size: 2rem;"></i>
                                                        <p>No schedules for <?php echo $day; ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Add Schedule Form Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="fas fa-plus-circle mr-2"></i>Add New Schedule</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="purpose">
                                        <i class="fas fa-book mr-1"></i> Purpose:
                                    </label>
                                    <select name="purpose" id="purpose" class="form-control" required>
                                        <?php foreach ($purposes as $purpose): ?>
                                            <option value="<?php echo htmlspecialchars($purpose); ?>">
                                                <?php echo htmlspecialchars($purpose); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="lab_number">
                                        <i class="fas fa-flask mr-1"></i> Laboratory:
                                    </label>
                                    <select name="lab_number" id="lab_number" class="form-control" required>
                                        <?php foreach ($labs as $lab): ?>
                                            <option value="<?php echo htmlspecialchars($lab); ?>" 
                                                <?php echo ($lab == $selected_lab) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lab); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="day">
                                        <i class="fas fa-calendar-day mr-1"></i> Day:
                                    </label>
                                    <select name="day" id="day" class="form-control" required>
                                        <?php foreach ($days as $day): ?>
                                            <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_time">
                                        <i class="fas fa-hourglass-start mr-1"></i> Start Time:
                                    </label>
                                    <input type="time" name="start_time" id="start_time" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_time">
                                        <i class="fas fa-hourglass-end mr-1"></i> End Time:
                                    </label>
                                    <input type="time" name="end_time" id="end_time" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="instructor_id">
                                        <i class="fas fa-user-tie mr-1"></i> Instructor:
                                    </label>
                                    <select name="instructor_id" id="instructor_id" class="form-control" required>
                                        <?php foreach ($instructors as $instructor): ?>
                                            <option value="<?php echo $instructor['id']; ?>">
                                                <?php echo htmlspecialchars($instructor['firstname'] . ' ' . $instructor['lastname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <button type="submit" name="add_schedule" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Add Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const scheduleForm = document.querySelector('form[name="add_schedule"]');
            
            if (scheduleForm) {
                scheduleForm.addEventListener('submit', function(e) {
                    const startTime = document.getElementById('start_time').value;
                    const endTime = document.getElementById('end_time').value;
                    
                    if (endTime <= startTime) {
                        e.preventDefault();
                        alert('End time must be after start time.');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html> 