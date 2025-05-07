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

$studentId = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Handle redemption request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['redeem_points'])) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get current points and sessions
        $userSql = "SELECT points, remaining_sessions, course FROM users WHERE idno = ?";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param("s", $studentId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        
        $currentPoints = $userData['points'];
        $currentSessions = $userData['remaining_sessions'];
        $course = $userData['course'];
        
        // Determine max sessions based on course
        $maxSessions = (in_array($course, ['BSIT', 'BSCS'])) ? 30 : 15;
        
        // Check if already at max sessions
        if ($currentSessions >= $maxSessions) {
            throw new Exception("You already have the maximum number of sessions allowed ($maxSessions).");
        }
        
        // Check if user has enough points (3 points = 1 session)
        if ($currentPoints < 3) {
            throw new Exception("You need at least 3 points to redeem for a session. You currently have $currentPoints points.");
        }
        
        // Calculate how many sessions to add
        $pointsToUse = min(floor($currentPoints / 3) * 3, ($maxSessions - $currentSessions) * 3);
        $sessionsToAdd = $pointsToUse / 3;
        
        if ($sessionsToAdd <= 0) {
            throw new Exception("Not enough points to redeem or you're at maximum sessions.");
        }
        
        // Update user's remaining sessions and points
        $newSessions = $currentSessions + $sessionsToAdd;
        $newPoints = $currentPoints - $pointsToUse;
        
        $updateSql = "UPDATE users SET remaining_sessions = ?, points = ? WHERE idno = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("iis", $newSessions, $newPoints, $studentId);
        $updateStmt->execute();
        
        // Log the redemption
        $logSql = "INSERT INTO bonus_logs (student_id, points_used, sessions_added, awarded_at) VALUES (?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("sii", $studentId, $pointsToUse, $sessionsToAdd);
        $logStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $message = "Successfully redeemed $pointsToUse points for $sessionsToAdd additional sessions.";
        $messageType = "success";
        
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollback();
        $message = $e->getMessage();
        $messageType = "error";
    }
}

// Get user data
$userSql = "SELECT idno, firstname, lastname, middlename, username, email, course, photo, year, points, remaining_sessions 
            FROM users 
            WHERE idno = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("s", $studentId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

// Determine max sessions
$maxSessions = (in_array($user['course'], ['BSIT', 'BSCS'])) ? 30 : 15;

// Calculate conversion potential
$pointsAvailable = $user['points'];
$maxRedeemableSessions = min(floor($pointsAvailable / 3), $maxSessions - $user['remaining_sessions']);
$pointsNeededForSession = 3;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redeem Points</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .points-container {
            display: flex;
            flex-direction: column;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .points-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .points-badge {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border-radius: 50px;
            margin-left: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }
        
        .points-badge i {
            margin-right: 8px;
        }
        
        .points-card {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-box {
            background-color: #f9f9f9;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-bottom: 15px;
            width: calc(50% - 10px);
            box-sizing: border-box;
            border-radius: 4px;
        }
        
        .info-box h3 {
            margin-top: 0;
            color: #333;
        }
        
        .info-box p {
            margin-bottom: 0;
            font-size: 24px;
            font-weight: bold;
            color: #3498db;
        }
        
        .redeem-form {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .redeem-form h2 {
            margin-top: 0;
            color: #333;
        }
        
        .redeem-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 10px 0;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .redeem-btn:hover {
            background-color: #45a049;
        }
        
        .redeem-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .message {
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .conversion-rate {
            font-style: italic;
            color: #666;
            margin-top: 10px;
        }
        
        @media (max-width: 600px) {
            .info-box {
                width: 100%;
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

<!-- Page Content -->
<div class="container">
    <h1>Redeem Points for Extra Sessions</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="points-container">
        <div class="points-header">
            <h2>Welcome, <?php echo $user['firstname'] . ' ' . $user['lastname']; ?></h2>
            <div class="points-badge">
                <i class="fas fa-star"></i> <?php echo $user['points']; ?> Points
            </div>
        </div>
        
        <div class="points-card">
            <div class="info-box">
                <h3>Available Points</h3>
                <p><?php echo $user['points']; ?></p>
            </div>
            <div class="info-box">
                <h3>Current Sessions</h3>
                <p><?php echo $user['remaining_sessions']; ?> / <?php echo $maxSessions; ?></p>
            </div>
            <div class="info-box">
                <h3>Redeemable Sessions</h3>
                <p><?php echo $maxRedeemableSessions; ?></p>
            </div>
            <div class="info-box">
                <h3>Points per Session</h3>
                <p><?php echo $pointsNeededForSession; ?></p>
            </div>
        </div>
        
        <div class="redeem-form">
            <h2>Convert Points to Sessions</h2>
            <p>You can convert your accumulated points into additional sit-in sessions. Each session costs 3 points.</p>
            <p class="conversion-rate">Conversion rate: 3 points = 1 session</p>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <button type="submit" name="redeem_points" class="redeem-btn" <?php echo ($maxRedeemableSessions <= 0) ? 'disabled' : ''; ?>>
                    Redeem Points for Sessions
                </button>
            </form>
            
            <?php if ($maxRedeemableSessions <= 0): ?>
                <?php if ($user['points'] < 3): ?>
                    <p>You need at least 3 points to redeem for a session.</p>
                <?php elseif ($user['remaining_sessions'] >= $maxSessions): ?>
                    <p>You already have the maximum number of sessions (<?php echo $maxSessions; ?>).</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html> 
