<?php
session_start();
header('Content-Type: application/json');

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please log in as an administrator.'
    ]);
    exit();
}

// Database connection details
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Check if action is specified
if (!isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No action specified'
    ]);
    exit();
}

// Action: Reset individual student's sessions
if ($_POST['action'] === 'reset_individual') {
    // Validate student ID
    if (!isset($_POST['student_id']) || empty($_POST['student_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Student ID is required'
        ]);
        exit();
    }
    
    $studentId = $conn->real_escape_string($_POST['student_id']);
    
    // Start transaction for safety
    $conn->begin_transaction();
    
    try {
        // Update student's remaining sessions to 30
        $stmt = $conn->prepare("UPDATE users SET remaining_sessions = 30 WHERE idno = ?");
        $stmt->bind_param("s", $studentId);
        $stmt->execute();
        
        // Check if the update was successful
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Sessions reset successfully for student ' . $studentId
            ]);
        } else {
            // No rows were updated, student ID might not exist
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Student with ID ' . $studentId . ' not found or sessions already at 30'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error resetting sessions: ' . $e->getMessage()
        ]);
    }
}
// Action: Reset all students' sessions
elseif ($_POST['action'] === 'reset_all') {
    // Start transaction for safety
    $conn->begin_transaction();
    
    try {
        // Update all students' remaining sessions to 30
        $stmt = $conn->prepare("UPDATE users SET remaining_sessions = 30");
        $stmt->execute();
        
        // Get the number of affected rows
        $affectedRows = $stmt->affected_rows;
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Sessions reset successfully for all students',
            'affected_rows' => $affectedRows
        ]);
        
        $stmt->close();
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error resetting sessions for all students: ' . $e->getMessage()
        ]);
    }
}
// Invalid action
else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action specified'
    ]);
}

// Close connection
$conn->close();
?> 
