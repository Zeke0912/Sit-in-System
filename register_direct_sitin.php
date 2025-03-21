<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser for JSON responses

// Make sure no output has been sent before session_start
if (headers_sent($filename, $linenum)) {
    // If headers already sent, log for debugging
    error_log("Headers already sent in $filename on line $linenum");
} else {
    // Start session
    session_start();
}

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    if (isset($_POST['action']) && $_POST['action'] === 'fetch') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    } else {
        echo '<div class="error-message">Connection failed: ' . $conn->connect_error . '</div>';
    }
    exit();
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    if (isset($_POST['action']) && $_POST['action'] === 'fetch') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    } else {
        echo '<div class="error-message">Unauthorized access</div>';
    }
    exit();
}

// Handle fetch student request
if (isset($_POST['action']) && $_POST['action'] === 'fetch') {
    // Try to clear any output buffering
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Set content type header if not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    // Check if student ID is provided
    if (isset($_POST['studentId']) && !empty($_POST['studentId'])) {
        $studentId = $_POST['studentId'];
        
        try {
            // Prepare SQL statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT idno, lastname, firstname, middlename, course, year, email, remaining_sessions FROM users WHERE idno = ? AND role = 'student'");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $studentId);
            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Student found
                $student = $result->fetch_assoc();
                
                // Ensure idno and remaining_sessions are set properly
                if (!isset($student['idno']) || empty($student['idno'])) {
                    error_log('WARNING: Student ID (idno) not set in database result, but student was found');
                    $student['idno'] = $studentId; // Fallback to the input ID if database didn't return one
                }
                
                if (!isset($student['remaining_sessions']) || $student['remaining_sessions'] === null) {
                    error_log('WARNING: remaining_sessions not set in database, defaulting to 30');
                    $student['remaining_sessions'] = 30; // Default value
                }
                
                // Log critical values for debugging
                error_log('CRITICAL VALUES:');
                error_log('- Student ID (idno): ' . $student['idno']);
                error_log('- Remaining sessions: ' . $student['remaining_sessions']);
                error_log('- Complete student record: ' . print_r($student, true));
                
                // Ensure clean output
                ob_clean();
                header('Content-Type: application/json');
                
                // Return student data with JSON_PRETTY_PRINT for debugging
                echo json_encode(['success' => true, 'student' => $student], JSON_PRETTY_PRINT);
            } else {
                // Student not found
                echo json_encode(['success' => false, 'message' => 'No student found with ID: ' . $studentId]);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        // No student ID provided
        echo json_encode(['success' => false, 'message' => 'Please enter a student ID number']);
    }
    
    $conn->close();
    exit();
}

// Handle register sit-in request
if (isset($_POST['studentId']) && !empty($_POST['studentId']) &&
    isset($_POST['subjectId']) && !empty($_POST['subjectId']) &&
    isset($_POST['purpose']) && !empty($_POST['purpose'])) {
    
    $studentId = $_POST['studentId'];
    $subjectId = $_POST['subjectId'];
    $purpose = $_POST['purpose'];
    
    // First verify that student exists and has remaining sessions  
    $checkStudent = $conn->prepare("SELECT idno, remaining_sessions FROM users WHERE idno = ? AND role = 'student'");
    if (!$checkStudent) {
        echo '<div class="error-message">Database error: ' . $conn->error . '</div>';
        $conn->close();
        exit();
    }
    
    $checkStudent->bind_param("i", $studentId);
    $checkStudent->execute();
    $studentResult = $checkStudent->get_result();
    
    if ($studentResult->num_rows === 0) {
        echo '<div class="error-message">No student found with ID: ' . $studentId . '</div>';
        $checkStudent->close();
        $conn->close();
        exit();
    }
    
    $student = $studentResult->fetch_assoc();
    
    // Set default remaining sessions if not set or zero
    if (!isset($student['remaining_sessions']) || $student['remaining_sessions'] === null || $student['remaining_sessions'] <= 0) {
        error_log('Setting default remaining sessions to 30 for student ID: ' . $studentId);
        $student['remaining_sessions'] = 30;
        
        // Update the database with the default value
        $updateSessions = $conn->prepare("UPDATE users SET remaining_sessions = 30 WHERE idno = ? AND role = 'student'");
        if ($updateSessions) {
            $updateSessions->bind_param("i", $studentId);
            $updateSessions->execute();
            $updateSessions->close();
            error_log('Updated database with default remaining sessions for student ID: ' . $studentId);
        }
    }
    
    // Verify that subject exists
    $checkSubject = $conn->prepare("SELECT id FROM subjects WHERE id = ?");
    if (!$checkSubject) {
        echo '<div class="error-message">Database error: ' . $conn->error . '</div>';
        $conn->close();
        exit();
    }
    
    $checkSubject->bind_param("i", $subjectId);
    $checkSubject->execute();
    $subjectResult = $checkSubject->get_result();
    
    if ($subjectResult->num_rows === 0) {
        echo '<div class="error-message">Invalid subject selected</div>';
        $checkSubject->close();
        $conn->close();
        exit();
    }
    $checkSubject->close();
    
    // Check if a request already exists for this student and subject
    $checkRequest = $conn->prepare("SELECT id, status FROM sit_in_requests WHERE student_id = ? AND subject_id = ? AND status IN ('pending', 'approved') AND is_active = 1");
    if (!$checkRequest) {
        echo '<div class="error-message">Database error: ' . $conn->error . '</div>';
        $conn->close();
        exit();
    }
    
    $checkRequest->bind_param("ii", $studentId, $subjectId);
    $checkRequest->execute();
    $requestResult = $checkRequest->get_result();
    
    if ($requestResult->num_rows > 0) {
        $request = $requestResult->fetch_assoc();
        // If a request exists, return the current status
        echo '<div class="error-message">An active session already exists for this student and subject.</div>';
        $checkRequest->close();
        $conn->close();
        exit();
    }
    $checkRequest->close();
    
    // Insert the new sit-in request with automatically approved status and set is_active to 1
    $insertRequest = $conn->prepare("INSERT INTO sit_in_requests (student_id, subject_id, purpose, status, is_active, start_time) VALUES (?, ?, ?, 'approved', 1, NOW())");
    if (!$insertRequest) {
        echo '<div class="error-message">Database error: ' . $conn->error . '</div>';
        $conn->close();
        exit();
    }
    
    $insertRequest->bind_param("iis", $studentId, $subjectId, $purpose);
    
    if ($insertRequest->execute()) {
        // Get subject details for the confirmation message
        $getSubject = $conn->prepare("SELECT subject_name, lab_number FROM subjects WHERE id = ?");
        if (!$getSubject) {
            echo '<div class="error-message">Database error: ' . $conn->error . '</div>';
            $insertRequest->close();
            $conn->close();
            exit();
        }
        
        $getSubject->bind_param("i", $subjectId);
        $getSubject->execute();
        $subjectDetails = $getSubject->get_result()->fetch_assoc();
        
        echo '<div class="success-message" style="background-color: #d4edda; border-left: 5px solid #28a745; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 3px 8px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;color:#155724;font-size:20px;">✅ Student Successfully Registered for Sit-in</h3>
            <p><strong>Student:</strong> ' . $_POST['studentName'] . '</p>
            <p><strong>ID Number:</strong> ' . $studentId . '</p>
            <p><strong>Course:</strong> ' . $_POST['studentCourse'] . '</p>
            <p><strong>Year:</strong> ' . $_POST['studentYear'] . '</p>
            <p><strong>Laboratory:</strong> ' . $subjectDetails['lab_number'] . '</p>
            <p><strong>Purpose:</strong> ' . $purpose . '</p>
            <p><strong>Status:</strong> <span style="color:#28a745;font-weight:bold;">Approved and Active</span></p>
            <p><strong>Remaining Sessions:</strong> ' . $student['remaining_sessions'] . '</p>
            <p><strong>Time:</strong> ' . date('F j, Y g:i A') . '</p>
        </div>';
        
        $getSubject->close();
    } else {
        echo '<div class="error-message">Error creating sit-in request: ' . $conn->error . '</div>';
    }
    
    $insertRequest->close();
} else {
    // If no POST data is received, check if it's a direct page access
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo '<div class="error-message">Direct page access is not allowed.</div>';
    } else if (!isset($_POST['action'])) { // Only show this error if it's not a fetch action
        echo '<div class="error-message">Please fill all required fields</div>';
    }
}

$conn->close();
// No closing PHP tag to prevent accidental whitespace 