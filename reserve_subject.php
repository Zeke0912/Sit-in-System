<?php
// reserve_subject.php

// Debug: Print the POST data
error_log('Received POST data: ' . print_r($_POST, true));  // This will log the incoming data for debugging purposes

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Handle the POST request to reserve the subject
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if 'subject_id' and 'student_id' are set
    if (isset($_POST['subject_id']) && isset($_POST['student_id'])) {
        $subject_id = $_POST['subject_id'];
        $student_id = $_POST['student_id'];
        $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : "";  // Optional feedback from the student

        // Debug: Print the data
        error_log("Subject ID: $subject_id, Student ID: $student_id, Feedback: $feedback");

        // First, check if a request already exists for this student and subject
        $check_sql = "SELECT status FROM sit_in_requests WHERE student_id = ? AND subject_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $student_id, $subject_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Request already exists, return its status
            $row = $check_result->fetch_assoc();
            echo $row['status'];  // Return the current status (approved, rejected, or pending)
            $check_stmt->close();
            $conn->close();
            exit();
        }
        $check_stmt->close();

        // Set the SQL query to update the status
        $sql = "UPDATE subjects SET status='pending' WHERE id=?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Error preparing statement: " . $conn->error);  // Log if the statement preparation fails
            echo "Error preparing query.";
            exit();
        }

        // Bind the parameter and execute
        $stmt->bind_param("i", $subject_id); // Bind the 'subject_id' parameter (integer type)
        if ($stmt->execute()) {
            // Insert the sit-in request in the sit_in_requests table
            $sql_request = "INSERT INTO sit_in_requests (student_id, subject_id, status, feedback) VALUES (?, ?, 'pending', ?)";
            $stmt_request = $conn->prepare($sql_request);
            $stmt_request->bind_param("iis", $student_id, $subject_id, $feedback);
            $stmt_request->execute();

            if ($stmt_request->affected_rows > 0) {
                echo "pending";  // Successfully reserved, returning 'pending' status
            } else {
                echo "Error inserting sit-in request.";
            }

            $stmt_request->close();
        } else {
            error_log("Error executing SQL: " . $stmt->error);  // Log if query execution fails
            echo "Error executing query.";
        }

        $stmt->close();
    } else {
        error_log("Missing subject_id or student_id in POST data.");
        echo "Missing subject_id or student_id";
    }
}

$conn->close();
?>
