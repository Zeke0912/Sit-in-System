<?php
session_start();
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die(json_encode([
        'success' => false, 
        'message' => "Connection failed: " . $conn->connect_error
    ]));
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Query to get only student users (exclude admin users)
// Based on the registration.php file, the role field is used to identify user types
$query = "SELECT * FROM users WHERE role = 'student' ORDER BY lastname";
$result = $conn->query($query);

if ($result) {
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'students' => $users
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching users: ' . $conn->error
    ]);
}

$conn->close();
?> 