<?php
session_start();
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Get request data
$studentId = isset($_POST['student_id']) ? $conn->real_escape_string($_POST['student_id']) : '';
$sessionId = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
$points = isset($_POST['points']) ? intval($_POST['points']) : 0;

// Validate data
if (empty($studentId) || $sessionId === 0 || $points < 1) {
    die(json_encode(['success' => false, 'message' => 'Invalid input data']));
}

// Begin transaction
$conn->begin_transaction();

try {
    // Check if points already awarded for this session
    $checkSql = "SELECT id FROM session_points WHERE student_id = ? AND session_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("si", $studentId, $sessionId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing points
        $updateSql = "UPDATE session_points SET points = ? WHERE student_id = ? AND session_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("isi", $points, $studentId, $sessionId);
        $updateStmt->execute();
    } else {
        // Insert new points record
        $insertSql = "INSERT INTO session_points (student_id, session_id, points, awarded_at) VALUES (?, ?, ?, NOW())";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sii", $studentId, $sessionId, $points);
        $insertStmt->execute();
    }
    
    // Get student's name
    $nameSql = "SELECT firstname, lastname FROM users WHERE idno = ?";
    $nameStmt = $conn->prepare($nameSql);
    $nameStmt->bind_param("s", $studentId);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $nameRow = $nameResult->fetch_assoc();
    $studentName = $nameRow ? $nameRow['firstname'] . ' ' . $nameRow['lastname'] : 'Unknown';
    
    // Get unprocessed points for this student
    $unprocessedPointsSql = "SELECT 
                              SUM(points) as total_points,
                              COALESCE(
                                (SELECT SUM(points_used) FROM bonus_logs WHERE student_id = ?), 
                                0
                              ) as points_used
                            FROM session_points 
                            WHERE student_id = ?";
    $unprocessedPointsStmt = $conn->prepare($unprocessedPointsSql);
    $unprocessedPointsStmt->bind_param("ss", $studentId, $studentId);
    $unprocessedPointsStmt->execute();
    $unprocessedPointsResult = $unprocessedPointsStmt->get_result();
    $unprocessedPointsRow = $unprocessedPointsResult->fetch_assoc();
    
    $totalPoints = $unprocessedPointsRow['total_points'];
    $pointsUsed = $unprocessedPointsRow['points_used'];
    $availablePoints = $totalPoints - $pointsUsed;
    
    // Get current number of sessions
    $sessionsSql = "SELECT remaining_sessions FROM users WHERE idno = ?";
    $sessionsStmt = $conn->prepare($sessionsSql);
    $sessionsStmt->bind_param("s", $studentId);
    $sessionsStmt->execute();
    $sessionsResult = $sessionsStmt->get_result();
    $currentSessions = $sessionsResult->fetch_assoc()['remaining_sessions'];
    
    // Calculate how many bonus sessions to award (every 3 points = 1 session)
    $bonusSessions = floor($availablePoints / 3);
    $bonusAwarded = false;
    
    if ($bonusSessions > 0) {
        // Points to use for bonus
        $pointsToUse = $bonusSessions * 3;
        
        // Make sure we don't exceed 30 sessions total
        $maxPossibleToAdd = 30 - $currentSessions;
        if ($maxPossibleToAdd <= 0) {
            // Already at or above the maximum, cannot add more
            $bonusSessions = 0;
            $pointsToUse = 0;
        } else if ($bonusSessions > $maxPossibleToAdd) {
            // Limit to what we can add without exceeding 30
            $bonusSessions = $maxPossibleToAdd;
            $pointsToUse = $bonusSessions * 3;
        }
        
        if ($bonusSessions > 0) {
            // Award bonus sessions
            $bonusSql = "UPDATE users SET remaining_sessions = ? WHERE idno = ?";
            $newTotalSessions = $currentSessions + $bonusSessions;
            $bonusStmt = $conn->prepare($bonusSql);
            $bonusStmt->bind_param("is", $newTotalSessions, $studentId);
            $bonusStmt->execute();
            
            // Log the bonus award
            $logSql = "INSERT INTO bonus_logs (student_id, points_used, sessions_awarded, awarded_at) 
                      VALUES (?, ?, ?, NOW())";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("sii", $studentId, $pointsToUse, $bonusSessions);
            $logStmt->execute();
            
            $bonusAwarded = true;
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Points awarded successfully', 
        'student_name' => $studentName,
        'total_points' => $totalPoints,
        'available_points' => $availablePoints,
        'points_used' => $pointsUsed,
        'bonus_sessions' => $bonusSessions,
        'bonus_awarded' => $bonusAwarded
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?> 