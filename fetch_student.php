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
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if student ID is provided
if (isset($_POST['studentId']) && !empty($_POST['studentId'])) {
    $studentId = $_POST['studentId'];
    
    // Prepare SQL statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT idno, lastname, firstname, middlename, course, year, email, remaining_hours FROM users WHERE idno = ? AND role = 'student'");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Student found
        $student = $result->fetch_assoc();
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        // Student not found
        echo json_encode(['success' => false, 'message' => 'No student found with ID: ' . $studentId]);
    }
    
    $stmt->close();
} else {
    // No student ID provided
    echo json_encode(['success' => false, 'message' => 'Please enter a student ID number']);
}

$conn->close();
?> 