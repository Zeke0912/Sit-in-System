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

// Step 2: Retrieve subjects from the database
$sql = "SELECT s.*, 
               COALESCE(
                   (SELECT sir.status 
                    FROM sit_in_requests sir 
                    WHERE sir.subject_id = s.id AND sir.student_id = ?
                    LIMIT 1), 
                   'available'
               ) as request_status 
        FROM subjects s
        WHERE s.status != 'full'
        ORDER BY s.date, s.start_time";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Initialize $subjects array
$subjects = [];

if ($result->num_rows > 0) {
    // Fetch all subjects into an array
    while($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Get user information
$userSql = "SELECT firstname, lastname, course, year FROM users WHERE idno = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $student_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

// Close connection
$stmt->close();
$userStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations</title>
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
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navigation */
        nav {
            background: #2C3E50;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        nav ul {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            height: 60px;
        }

        nav li {
            display: flex;
        }

        nav a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 0 20px;
            display: flex;
            align-items: center;
            height: 100%;
            transition: background-color 0.3s;
        }

        nav a:hover, nav a.active {
            background-color: rgba(26, 188, 156, 0.2);
        }

        nav a i {
            margin-right: 8px;
        }

        .logout-container {
            margin-left: auto;
            display: flex;
            align-items: center;
            padding-right: 20px;
        }

        .logout-container a {
            display: flex;
            align-items: center;
            color: #e74c3c;
            padding: 8px 15px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .logout-container a:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 80px auto 20px;
            padding: 0 20px;
            flex: 1;
        }

        /* User Info Section */
        .user-info {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-right: 20px;
        }

        .user-details h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 22px;
        }

        .user-details p {
            margin: 5px 0 0;
            color: #7f8c8d;
        }

        /* Reservations Section */
        .reservations-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .reservations-section h2 {
            color: #2980B9;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .reservations-section h2 i {
            margin-right: 10px;
        }

        .reservations-section table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-section th, .reservations-section td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            color: #333;
        }

        .reservations-section th {
            background-color: #2980B9;
            color: white;
        }

        .reservations-section tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .reservations-section tr:hover {
            background-color: #f1f1f1;
        }

        .reserve-btn {
            background-color: #2980B9;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            font-weight: bold;
        }

        .reserve-btn:hover {
            background-color: #3498db;
        }

        .reserve-btn:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }

        .pending-btn {
            background-color: #f39c12;
        }

        .approved-btn {
            background-color: #27ae60;
        }

        .rejected-btn {
            background-color: #e74c3c;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #bdc3c7;
        }

        .empty-state p {
            font-size: 18px;
        }

        /* Loading indicator */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0s, opacity 0.3s;
        }

        .loading-overlay.visible {
            visibility: visible;
            opacity: 1;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer */
        footer {
            background: #2C3E50;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: auto;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            
            .reservations-section {
                padding: 15px;
                overflow-x: auto;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }
            
            .user-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            nav ul {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            nav a {
                padding: 0 15px;
                white-space: nowrap;
            }
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
        <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        <li><a href="edit_profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
    </ul>
    <div class="logout-container">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- Page Content -->
<div class="container">


    <div class="reservations-section">
        <h2><i class="fas fa-calendar-check"></i> Available Subjects for Reservation</h2>
        
        <?php if (empty($subjects)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <p>No subjects are currently available for reservation</p>
        </div>
        <?php else: ?>    
        <table>
            <tr>
                <th>Subject Name</th>
                <th>Lab Number</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Sessions</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($subjects as $subject) { ?>
                <tr id="subject-<?php echo $subject['id']; ?>">
                    <td><?php echo htmlspecialchars($subject['subject_name']) ?: 'Unnamed Subject'; ?></td>
                    <td><?php echo htmlspecialchars($subject['lab_number']); ?></td>
                    <td><?php echo htmlspecialchars($subject['date']); ?></td>
                    <td><?php echo htmlspecialchars($subject['start_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['end_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['sessions']); ?></td>
                    <td class="status-cell"><?php echo htmlspecialchars($subject['request_status']); ?></td>
                    <td>
                        <?php 
                            $btnClass = 'reserve-btn';
                            $btnText = 'Reserve';
                            $disabled = '';
                            
                            if ($subject['request_status'] !== 'available') {
                                $btnClass .= ' ' . strtolower($subject['request_status']) . '-btn';
                                $btnText = ucfirst($subject['request_status']);
                                $disabled = 'disabled';
                            }
                        ?>
                        <button class="<?php echo $btnClass; ?>" 
                                data-subject-id="<?php echo $subject['id']; ?>" 
                                data-action="reserve" 
                                <?php echo $disabled; ?>>
                            <?php echo $btnText; ?>
                        </button>
                    </td>
                </tr>
            <?php } ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<footer>
    &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Show loading overlay
    function showLoading() {
        $("#loadingOverlay").addClass("visible");
    }
    
    // Hide loading overlay
    function hideLoading() {
        $("#loadingOverlay").removeClass("visible");
    }

    $(".reserve-btn").click(function() {
        if ($(this).attr('disabled')) {
            return; // Don't proceed if button is disabled
        }
        
        var subjectId = $(this).data("subject-id");
        var button = $(this);
        var statusCell = button.closest('tr').find('.status-cell');
        
        // Show loading
        showLoading();
        
        // Get the student ID
        var studentId = <?php echo $student_id; ?>;

        $.ajax({
            url: "reserve_subject.php",
            type: "POST",
            data: {
                subject_id: subjectId,
                student_id: studentId
            },
            success: function(response) {
                hideLoading();
                console.log("Server Response:", response);
                
                // Update button and status based on response
                if (response === "pending") {
                    statusCell.text("pending");
                    button.text("Pending")
                          .addClass("pending-btn")
                          .attr("disabled", true);
                } else if (response === "approved") {
                    statusCell.text("approved");
                    button.text("Approved")
                          .addClass("approved-btn")
                          .attr("disabled", true);
                } else if (response === "rejected") {
                    statusCell.text("rejected");
                    button.text("Rejected")
                          .addClass("rejected-btn")
                          .attr("disabled", true);
                } else {
                    alert("Error: " + response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                hideLoading();
                console.error("AJAX Error:", textStatus, errorThrown);
                alert("There was an error processing your request. Please try again.");
            }
        });
    });
});
</script>

</body>
</html>