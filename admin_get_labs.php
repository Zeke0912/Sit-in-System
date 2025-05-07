<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser for JSON responses

// Start session
session_start();

// Set content type header
header('Content-Type: application/json');

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

try {
    // Get laboratories from the subjects table
    $sql = "SELECT id, lab_number FROM subjects ORDER BY lab_number ASC";
    $result = $conn->query($sql);
    
    if ($result) {
        $laboratories = [];
        
        while ($row = $result->fetch_assoc()) {
            $laboratories[] = [
                'id' => $row['id'],
                'lab_number' => $row['lab_number']
            ];
        }
        
        echo json_encode(['success' => true, 'laboratories' => $laboratories]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error fetching laboratories: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}

// Close the database connection
$conn->close(); 
