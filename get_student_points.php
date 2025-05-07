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

// Get today's date for filtering
$today = date('Y-m-d');

// Get points for today's sessions
$sql = "SELECT sp.student_id, sp.session_id, sp.points
        FROM session_points sp
        JOIN sit_in_requests r ON sp.session_id = r.id
        WHERE DATE(r.end_time) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

$points = [];
while ($row = $result->fetch_assoc()) {
    $points[] = $row;
}

echo json_encode(['success' => true, 'points' => $points]);
$conn->close();
?> 
