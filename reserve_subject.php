<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please log in to reserve a subject.";
    exit();
}

// Check if the subject ID is passed in the request
if (isset($_POST['subject_id'])) {
    $subject_id = $_POST['subject_id'];
    $student_id = $_SESSION['user_id']; // Assuming the user's ID is stored in session

    // Database connection
    $servername = "localhost";
    $username = "root"; // Replace with your database username
    $password = "";     // Replace with your database password
    $dbname = "my_database"; // Replace with your database name

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Insert reservation into the reservations table with 'pending' status
    $sql = "INSERT INTO sit_in_requests (student_id, subject_id, status, feedback) VALUES (?, ?, 'pending', NULL)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $subject_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the database connection
    $stmt->close();
    $conn->close();
} else {
    echo "Subject ID not provided.";
}
?>
