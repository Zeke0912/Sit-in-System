<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle feedback submission
$feedbackMessage = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    $sit_in_id = $_POST['sit_in_id'];
    $rating = $_POST['rating'];
    $feedback = $_POST['feedback'] . " (Rating: " . $rating . "/5)";
    $student_id = $_SESSION['user_id'];
    
    // Update feedback in the sit_in_requests table - using only the feedback column
    $updateSql = "UPDATE sit_in_requests SET feedback = ? WHERE id = ? AND student_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sis", $feedback, $sit_in_id, $student_id);
    
    if ($updateStmt->execute()) {
        $feedbackMessage = "Thank you for your feedback!";
    } else {
        $feedbackMessage = "Error submitting feedback: " . $conn->error;
    }
    $updateStmt->close();
}

// Get sit-in records for current user
$student_id = $_SESSION['user_id'];
$sql = "SELECT r.id, r.subject_id, r.purpose, r.start_time, r.end_time, r.status, 
        r.feedback, s.subject_name, s.lab_number
        FROM sit_in_requests r
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.student_id = ? AND r.is_active = 0
        ORDER BY r.end_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in Records</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }

        h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 25px;
            text-align: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .records-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-top: 20px;
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .records-table th, 
        .records-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .records-table th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        .records-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .records-table tr:hover {
            background-color: #f1f5f9;
        }
        
        .feedback-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 12px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        
        .feedback-btn:hover {
            background-color: #45a049;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .feedback-given {
            color: #4CAF50;
            font-weight: bold;
            margin-right: 10px;
            display: block;
            margin-bottom: 5px;
        }
        
        .feedback-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border: 1px solid #e0e0e0;
            width: 60%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideDown 0.3s;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        
        .close:hover,
        .close:focus {
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }
        
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            margin: 25px 0;
        }
        
        .rating > input {
            display: none;
        }
        
        .rating > label {
            position: relative;
            width: 1.1em;
            font-size: 3em;
            color: #FFD700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rating > label::before {
            content: "\2605";
            position: absolute;
            opacity: 0;
        }
        
        .rating > label:hover:before,
        .rating > label:hover ~ label:before {
            opacity: 1 !important;
        }
        
        .rating > input:checked ~ label:before {
            opacity: 1;
        }
        
        .rating > label:hover {
            transform: scale(1.1);
        }
        
        .feedback-form textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            margin-top: 10px;
            margin-bottom: 20px;
            resize: vertical;
            min-height: 120px;
            font-family: Arial, sans-serif;
            font-size: 15px;
            transition: border 0.3s ease;
        }
        
        .feedback-form textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }
        
        .feedback-form input[type=submit] {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        
        .feedback-form input[type=submit]:hover {
            background-color: #2980b9;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .feedback-message {
            margin: 20px auto;
            padding: 15px;
            background-color: #dff0d8;
            border-radius: 4px;
            color: #3c763d;
            text-align: center;
            border-left: 5px solid #4CAF50;
            display: <?php echo (!empty($feedbackMessage)) ? 'block' : 'none'; ?>;
            max-width: 800px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 16px;
            border: 1px dashed #ddd;
        }
        
        #feedbackContent {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            border-left: 4px solid #3498db;
        }
        
        /* Responsive styling */
        @media screen and (max-width: 768px) {
            .container {
                width: 95%;
                padding: 10px;
            }
            
            .modal-content {
                width: 90%;
                margin: 20% auto;
                padding: 15px;
            }
            
            .records-table {
                font-size: 14px;
            }
            
            .records-table th, 
            .records-table td {
                padding: 8px;
            }
            
            .feedback-btn {
                padding: 6px 10px;
                font-size: 13px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav>
    <ul>
        <li><a href="home.php">Dashboard</a></li>
        <li><a href="reservations.php">Reservations</a></li>
        <li><a href="student_sit_in_records.php">Sit-in Records</a></li>
        <li><a href="redeem_points.php">Redeem Points</a></li>
        <li><a href="announcements.php">Announcements</a></li>
    </ul>
    <div class="logout-container">
        <a href="logout.php">Logout</a>
    </div>
</nav>

<!-- Page Content Layout -->
<div class="container">
    <?php
    // Get user information
    $userSql = "SELECT idno, firstname, lastname, username, email, course, photo, year 
                FROM users 
                WHERE idno = ?";
    $userStmt = $conn->prepare($userSql);
    $userStmt->bind_param("s", $student_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    
    // Get remaining sessions count
    $max_sessions = (in_array($user['course'], ['BSIT', 'BSCS'])) ? 30 : 15;
    $usedSql = "SELECT COUNT(*) as used FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
    $usedStmt = $conn->prepare($usedSql);
    $usedStmt->bind_param("s", $student_id);
    $usedStmt->execute();
    $usedResult = $usedStmt->get_result();
    $usedRow = $usedResult->fetch_assoc();
    $remaining_sessions = $max_sessions - $usedRow['used'];
    ?>
    
    <div class="feedback-message"><?php echo $feedbackMessage; ?></div>
    
    <div class="records-container">
        <?php if ($result->num_rows > 0): ?>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Purpose</th>
                        <th>Lab</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    while($row = $result->fetch_assoc()): 
                        $durationStr = '';
                        
                        // Add null checks to prevent errors
                        if (!empty($row['start_time']) && !empty($row['end_time'])) {
                            $start = new DateTime($row['start_time']);
                            $end = new DateTime($row['end_time']);
                            $duration = $start->diff($end);
                            
                            if ($duration->h > 0) {
                                $durationStr .= $duration->h . 'h ';
                            }
                            $durationStr .= $duration->i . 'm';
                        }
                    ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['lab_number']); ?></td>
                        <td><?php echo !empty($row['end_time']) ? date('Y-m-d', strtotime($row['end_time'])) : 'N/A'; ?></td>
                        <td><?php 
                            if (!empty($row['start_time']) && !empty($row['end_time'])) {
                                echo date('H:i:s', strtotime($row['start_time'])) . ' - ' . date('H:i:s', strtotime($row['end_time']));
                            } else {
                                echo 'N/A';
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td>
                            <?php if (!empty($row['feedback']) && $row['feedback'] != "Looking forward to the session!"): ?>
                                <?php 
                                // Extract and display just the feedback content (without extra styling)
                                $feedback = $row['feedback'];
                                echo htmlspecialchars($feedback);
                                ?>
                            <?php else: ?>
                                <button class="feedback-btn" onclick="openFeedbackModal(<?php echo $row['id']; ?>)">Provide Feedback</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-records">
                <p>You don't have any completed sit-in sessions yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="feedback-modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Share Your Experience</h2>
        <p>Please rate your sit-in experience and provide any feedback that could help us improve.</p>
        
        <form class="feedback-form" method="POST" action="">
            <input type="hidden" id="sit_in_id" name="sit_in_id" value="">
            
            <div class="rating">
                <input type="radio" name="rating" value="5" id="rating-5" required>
                <label for="rating-5"></label>
                <input type="radio" name="rating" value="4" id="rating-4">
                <label for="rating-4"></label>
                <input type="radio" name="rating" value="3" id="rating-3">
                <label for="rating-3"></label>
                <input type="radio" name="rating" value="2" id="rating-2">
                <label for="rating-2"></label>
                <input type="radio" name="rating" value="1" id="rating-1">
                <label for="rating-1"></label>
            </div>
            
            <label for="feedback">Your Comments:</label>
            <textarea id="feedback" name="feedback" placeholder="Tell us about your experience..." required></textarea>
            
            <input type="submit" name="submit_feedback" value="Submit Feedback">
        </form>
    </div>
</div>

<!-- View Feedback Modal -->
<div id="viewFeedbackModal" class="feedback-modal">
    <div class="modal-content">
        <span class="close" onclick="closeViewFeedbackModal()">&times;</span>
        <h2>Your Feedback</h2>
        <div id="feedbackContent"></div>
    </div>
</div>

<script>
    // Modal functionality
    const modal = document.getElementById("feedbackModal");
    const viewModal = document.getElementById("viewFeedbackModal");
    const span = document.getElementsByClassName("close")[0];
    
    function openFeedbackModal(sit_in_id) {
        document.getElementById("sit_in_id").value = sit_in_id;
        modal.style.display = "block";
    }
    
    function viewFeedback(feedback) {
        document.getElementById("feedbackContent").innerHTML = feedback;
        viewModal.style.display = "block";
    }
    
    function closeViewFeedbackModal() {
        viewModal.style.display = "none";
    }
    
    span.onclick = function() {
        modal.style.display = "none";
    }
    
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
        if (event.target == viewModal) {
            viewModal.style.display = "none";
        }
    }
    
    // Auto-hide feedback message after 5 seconds
    setTimeout(function() {
        const feedbackMessage = document.querySelector('.feedback-message');
        if (feedbackMessage.style.display !== 'none') {
            feedbackMessage.style.display = 'none';
        }
    }, 5000);
</script>

</body>
</html>
<?php
// Close statements
$stmt->close();
$userStmt->close();
$usedStmt->close();
$conn->close();
?> 