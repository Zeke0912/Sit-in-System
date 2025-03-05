<?php
session_start();
$conn = new mysqli("localhost", "root", "", "my_database");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
$subject_id = $_GET['subject_id'];

// Get the student ID and course
$sql = "SELECT idno, course FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($student_id, $course);
$stmt->fetch();
$stmt->close();

// Check the total approved sessions for the student
$sql = "SELECT COUNT(*) FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->bind_result($approved_sessions);
$stmt->fetch();
$stmt->close();

// Determine the maximum sessions based on the course
$max_sessions = ($course == 'BSIT' || $course == 'BSCS') ? 30 : 15;

if ($approved_sessions >= $max_sessions) {
    echo "<script>alert('You have reached the maximum number of sit-in sessions.'); window.location.href = 'home.php';</script>";
} else {
    // Insert the sit-in request
    $sql = "INSERT INTO sit_in_requests (student_id, subject_id, status) VALUES (?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $student_id, $subject_id);

    if ($stmt->execute()) {
        echo "<script>alert('Sit-in request submitted successfully.'); window.location.href = 'home.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>