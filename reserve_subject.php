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
        $subject_id = intval($_POST['subject_id']);
        $student_id = intval($_POST['student_id']);
        $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : "";  // Optional feedback from the student
        $pc_number = isset($_POST['pc_number']) ? intval($_POST['pc_number']) : null;  // Get PC number if provided

        // Debug: Print the data
        error_log("Subject ID: $subject_id, Student ID: $student_id, Feedback: $feedback, PC Number: $pc_number");

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

        // Get lab_number from the subjects table
        $lab_sql = "SELECT lab_number FROM subjects WHERE id = ?";
        $lab_stmt = $conn->prepare($lab_sql);
        $lab_stmt->bind_param("i", $subject_id);
        $lab_stmt->execute();
        $lab_result = $lab_stmt->get_result();
        
        if ($lab_result->num_rows > 0) {
            $row = $lab_result->fetch_assoc();
            $lab_number = $row['lab_number'];
        } else {
            $lab_number = ""; // Default value if lab number is not found
        }
        $lab_stmt->close();

        // Check if the selected PC is already taken
        if ($pc_number !== null) {
            $pc_check_sql = "SELECT id FROM sit_in_requests 
                             WHERE subject_id = ? AND pc_number = ? AND status IN ('pending', 'approved') 
                             AND (is_active = 1 OR end_time IS NULL)";
            $pc_check_stmt = $conn->prepare($pc_check_sql);
            $pc_check_stmt->bind_param("ii", $subject_id, $pc_number);
            $pc_check_stmt->execute();
            $pc_check_result = $pc_check_stmt->get_result();
            
            if ($pc_check_result->num_rows > 0) {
                echo "pc_taken";  // PC is already reserved/in use
                $pc_check_stmt->close();
                $conn->close();
                exit();
            }
            $pc_check_stmt->close();
        }

        // Insert the sit-in request in the sit_in_requests table with the lab_number and pc_number
        $sql_request = "INSERT INTO sit_in_requests (student_id, subject_id, lab_number, pc_number, purpose, status, feedback) 
                        VALUES (?, ?, ?, ?, 'Sit-in Session', 'pending', ?)";
        $stmt_request = $conn->prepare($sql_request);
        $stmt_request->bind_param("iisis", $student_id, $subject_id, $lab_number, $pc_number, $feedback);
        
        if ($stmt_request->execute()) {
            // Update the status of the subject to 'pending'
            $update_sql = "UPDATE subjects SET status = 'pending' WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $subject_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            echo "pending";  // Successfully reserved, returning 'pending' status
        } else {
            error_log("Error inserting sit-in request: " . $stmt_request->error);
            echo "Error inserting sit-in request: " . $stmt_request->error;
        }

        $stmt_request->close();
    } else {
        error_log("Missing subject_id or student_id in POST data.");
        echo "Missing subject_id or student_id";
    }
}

$conn->close();
?>
