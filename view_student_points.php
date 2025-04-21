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

// Check authentication
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

// Get student ID from request
$studentId = isset($_GET['student_id']) ? $_GET['student_id'] : '';

if (empty($studentId)) {
    die("Student ID is required");
}

// Fetch student information
$studentSql = "SELECT idno, firstname, lastname, middlename, course, year, points FROM users WHERE idno = ?";
$studentStmt = $conn->prepare($studentSql);
$studentStmt->bind_param("s", $studentId);
$studentStmt->execute();
$studentResult = $studentStmt->get_result();

if ($studentResult->num_rows == 0) {
    die("Student not found");
}

$student = $studentResult->fetch_assoc();

// Fetch points history
$pointsSql = "SELECT sp.*, r.purpose, r.start_time, r.end_time, s.lab_number 
              FROM session_points sp
              JOIN sit_in_requests r ON sp.session_id = r.id
              JOIN subjects s ON r.subject_id = s.id
              WHERE sp.student_id = ?
              ORDER BY sp.awarded_at DESC";
$pointsStmt = $conn->prepare($pointsSql);
$pointsStmt->bind_param("s", $studentId);
$pointsStmt->execute();
$pointsResult = $pointsStmt->get_result();

// Calculate total points
$totalPoints = 0;
$pointsHistory = [];
while ($row = $pointsResult->fetch_assoc()) {
    $pointsHistory[] = $row;
    $totalPoints += $row['points'];
}

// Calculate bonus sessions
$bonusSql = "SELECT * FROM bonus_logs WHERE student_id = ? ORDER BY awarded_at DESC";
$bonusStmt = $conn->prepare($bonusSql);
$bonusStmt->bind_param("s", $studentId);
$bonusStmt->execute();
$bonusResult = $bonusStmt->get_result();

$bonusHistory = [];
while ($row = $bonusResult->fetch_assoc()) {
    $bonusHistory[] = $row;
}

// Close database connection
$studentStmt->close();
$pointsStmt->close();
$bonusStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Points - <?php echo $student['firstname'] . ' ' . $student['lastname']; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/favicon.png" type="image/png">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .student-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-right: 20px;
        }
        
        .student-info {
            flex-grow: 1;
        }
        
        .student-info h1 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .student-info p {
            margin: 5px 0;
            color: #7f8c8d;
        }
        
        .points-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .summary-card {
            flex: 1;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            margin: 0 10px;
            border-left: 4px solid #3498db;
        }
        
        .summary-card h2 {
            margin: 0;
            font-size: 36px;
            color: #3498db;
        }
        
        .summary-card p {
            margin: 5px 0 0;
            color: #7f8c8d;
        }
        
        .section-title {
            margin: 30px 0 15px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .points-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .points-table th, .points-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .points-table th {
            background-color: #3498db;
            color: white;
            font-weight: normal;
        }
        
        .points-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .points-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .points-badge {
            background-color: #f1c40f;
            color: #2c3e50;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        
        .empty-message {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        
        .action-buttons button {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
            transition: background-color 0.3s;
        }
        
        .action-buttons button:hover {
            background-color: #2980b9;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .points-summary {
                flex-direction: column;
            }
            
            .summary-card {
                margin: 10px 0;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .student-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="student-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="student-info">
                <h1><?php echo $student['firstname'] . ' ' . (!empty($student['middlename']) ? $student['middlename'] . ' ' : '') . $student['lastname']; ?></h1>
                <p><strong>ID Number:</strong> <?php echo $student['idno']; ?></p>
                <p><strong>Course:</strong> <?php echo $student['course']; ?></p>
                <p><strong>Year:</strong> <?php echo $student['year']; ?></p>
                <div class="points-badge">
                    <i class="fas fa-star"></i> Total Points: <?php echo $totalPoints; ?>
                </div>
            </div>
        </div>
        
        <div class="points-summary">
            <div class="summary-card">
                <h2><?php echo $totalPoints; ?></h2>
                <p>Total Points Earned</p>
            </div>
            <div class="summary-card">
                <h2><?php echo count($pointsHistory); ?></h2>
                <p>Sessions with Points</p>
            </div>
            <div class="summary-card">
                <h2><?php echo count($bonusHistory); ?></h2>
                <p>Bonus Sessions Earned</p>
            </div>
        </div>
        
        <h2 class="section-title">Points History</h2>
        <?php if (count($pointsHistory) > 0): ?>
            <table class="points-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Lab</th>
                        <th>Purpose</th>
                        <th>Session Time</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pointsHistory as $point): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($point['awarded_at'])); ?></td>
                            <td><?php echo $point['lab_number']; ?></td>
                            <td><?php echo $point['purpose']; ?></td>
                            <td>
                                <?php 
                                if (!empty($point['start_time']) && !empty($point['end_time'])) {
                                    echo date('h:i A', strtotime($point['start_time'])) . ' - ' . 
                                         date('h:i A', strtotime($point['end_time']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><strong><?php echo $point['points']; ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">No points history found for this student.</div>
        <?php endif; ?>
        
        <h2 class="section-title">Bonus Sessions History</h2>
        <?php if (count($bonusHistory) > 0): ?>
            <table class="points-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Points Used</th>
                        <th>Sessions Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bonusHistory as $bonus): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($bonus['awarded_at'])); ?></td>
                            <td><?php echo $bonus['points_used']; ?></td>
                            <td><?php echo $bonus['sessions_added']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-message">No bonus sessions history found for this student.</div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <button onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
            <button onclick="window.history.back()"><i class="fas fa-arrow-left"></i> Go Back</button>
        </div>
    </div>
</body>
</html> 