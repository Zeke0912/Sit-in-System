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

// Handle fetch available PCs request
if (isset($_POST['action']) && $_POST['action'] === 'fetch_pcs') {
    // Try to clear any output buffering
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Set content type header if not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    // Check if subject ID is provided
    if (isset($_POST['subjectId']) && !empty($_POST['subjectId'])) {
        $subjectId = $_POST['subjectId'];
        
        try {
            // Get the total PC count for this lab from subjects table
            $stmt = $conn->prepare("SELECT lab_number, pc_count FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Subject not found']);
                exit;
            }
            
            $subject = $result->fetch_assoc();
            $lab_number = $subject['lab_number'];
            $total_pcs = $subject['pc_count'] ?: 50; // Default to 50 if not set
            
            // Get PCs that are already in use or reserved for this lab
            $stmt = $conn->prepare("
                SELECT pc_number 
                FROM sit_in_requests 
                WHERE subject_id = ? 
                AND pc_number IS NOT NULL 
                AND status IN ('approved', 'pending')
                AND (end_time IS NULL OR is_active = 1)
            ");
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reserved_pcs = [];
            while ($row = $result->fetch_assoc()) {
                $reserved_pcs[] = (int)$row['pc_number'];
            }
            
            // Specifically get PCs that are marked as maintenance
            $stmt = $conn->prepare("
                SELECT pc_number 
                FROM pc_status 
                WHERE lab_id = ? 
                AND status = 'maintenance'
            ");
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $maintenance_pcs = [];
            while ($row = $result->fetch_assoc()) {
                $maintenance_pcs[] = (int)$row['pc_number'];
            }
            
            // Get PCs that are marked as occupied (but not maintenance)
            $stmt = $conn->prepare("
                SELECT pc_number 
                FROM pc_status 
                WHERE lab_id = ? 
                AND status = 'occupied'
            ");
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $occupied_pcs = [];
            while ($row = $result->fetch_assoc()) {
                $occupied_pcs[] = (int)$row['pc_number'];
            }
            
            // Combine reserved, occupied, and maintenance PCs
            $unavailable_pcs = array_unique(array_merge($reserved_pcs, $occupied_pcs, $maintenance_pcs));
            
            // Create an array of available PCs
            $available_pcs = [];
            for ($i = 1; $i <= $total_pcs; $i++) {
                if (!in_array($i, $unavailable_pcs)) {
                    $available_pcs[] = $i;
                }
            }
            
            // Return the list of PCs with their status
            echo json_encode([
                'success' => true,
                'lab_number' => $lab_number,
                'available_pcs' => $available_pcs,
                'total_pcs' => $total_pcs,
                'occupied_pcs' => $occupied_pcs,
                'reserved_pcs' => $reserved_pcs,
                'maintenance_pcs' => $maintenance_pcs
            ]);
            
            $stmt->close();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'No subject ID provided']);
        exit();
    }
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

// Process sit-in direct registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $studentId = isset($_POST['studentId']) ? $_POST['studentId'] : null;
    $studentName = isset($_POST['studentName']) ? $_POST['studentName'] : null;
    $pc_number = isset($_POST['pc_number']) ? (int)$_POST['pc_number'] : 0;
    $subjectId = isset($_POST['subjectId']) ? (int)$_POST['subjectId'] : 0;
    $purpose = isset($_POST['purpose']) ? $_POST['purpose'] : '';
    
    if (!$studentId || !$pc_number || !$subjectId || !$purpose) {
        echo '<div class="error-message">All fields are required.</div>';
    } else {
        // Check if PC is under maintenance before proceeding
        $maintenance_check = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM pc_status 
            WHERE lab_id = ? AND pc_number = ? AND status = 'maintenance'
        ");
        $maintenance_check->bind_param("ii", $subjectId, $pc_number);
        $maintenance_check->execute();
        $maintenance_result = $maintenance_check->get_result();
        $maintenance_data = $maintenance_result->fetch_assoc();
        
        if ($maintenance_data['count'] > 0) {
            echo '<div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                Error: PC #' . $pc_number . ' is currently under maintenance and cannot be used.
            </div>';
            exit();
        }
        
        // Check if PC is already occupied by someone else
        $occupied_check = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM pc_status 
            WHERE lab_id = ? AND pc_number = ? AND status = 'occupied'
        ");
        $occupied_check->bind_param("ii", $subjectId, $pc_number);
        $occupied_check->execute();
        $occupied_result = $occupied_check->get_result();
        $occupied_data = $occupied_result->fetch_assoc();
        
        if ($occupied_data['count'] > 0) {
            echo '<div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                Error: PC #' . $pc_number . ' is currently occupied by another student.
            </div>';
            exit();
        }
        
        // Proceed with registration if PC is available
        try {
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
            
            // Get lab_number from the subjects table
            $lab_sql = "SELECT lab_number FROM subjects WHERE id = ?";
            $lab_stmt = $conn->prepare($lab_sql);
            $lab_stmt->bind_param("i", $subjectId);
            $lab_stmt->execute();
            $lab_result = $lab_stmt->get_result();
            
            if ($lab_result->num_rows > 0) {
                $row = $lab_result->fetch_assoc();
                $lab_number = $row['lab_number'];
            } else {
                $lab_number = ""; // Default value if lab number is not found
            }
            $lab_stmt->close();
            
            // Insert the new sit-in request with automatically approved status and set is_active to 1
            $insertRequest = $conn->prepare("INSERT INTO sit_in_requests (student_id, subject_id, purpose, status, is_active, start_time, lab_number, pc_number) VALUES (?, ?, ?, 'approved', 1, NOW(), ?, ?)");
            if (!$insertRequest) {
                echo '<div class="error-message">Database error: ' . $conn->error . '</div>';
                $conn->close();
                exit();
            }
            
            $insertRequest->bind_param("iissi", $studentId, $subjectId, $purpose, $lab_number, $pc_number);
            
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
                    ' . ($pc_number ? '<p><strong>PC Number:</strong> ' . $pc_number . '</p>' : '') . '
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
            
            // If request was successfully inserted, also update the pc_status table
            if ($pc_number !== null) {
                $tableCheckSql = "SHOW TABLES LIKE 'pc_status'";
                $tableCheckResult = $conn->query($tableCheckSql);
                
                if ($tableCheckResult && $tableCheckResult->num_rows > 0) {
                    // Check if the PC is currently occupied or under maintenance
                    $status_check_sql = "SELECT status FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                    $status_check_stmt = $conn->prepare($status_check_sql);
                    $status_check_stmt->bind_param("ii", $subjectId, $pc_number);
                    $status_check_stmt->execute();
                    $status_result = $status_check_stmt->get_result();
                    
                    $pc_status = 'vacant'; // Default status if not found in pc_status table
                    if ($status_result->num_rows > 0) {
                        $status_row = $status_result->fetch_assoc();
                        $pc_status = $status_row['status'];
                    }
                    $status_check_stmt->close();
                    
                    // Final maintenance check before updating status - redundant but provides extra security
                    if ($pc_status === 'maintenance') {
                        echo '<div class="error-message" style="background-color: #f8d7da; border-left: 5px solid #9b59b6; padding: 15px; margin: 20px 0; border-radius: 4px;">
                            <h3 style="margin-top:0;color:#721c24;font-size:18px;"><i class="fas fa-tools"></i> PC Under Maintenance</h3>
                            <p>PC #' . $pc_number . ' is under maintenance. The student has been registered but <strong>cannot use this PC</strong>.</p>
                            <p>Please select a different PC or address the maintenance issue first.</p>
                        </div>';
                    } else {
                        // Only update status if PC is not under maintenance
                        $update_pc_status_sql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                                                VALUES (?, ?, 'occupied') 
                                                ON DUPLICATE KEY UPDATE status = 'occupied'";
                        $update_pc_status_stmt = $conn->prepare($update_pc_status_sql);
                        $update_pc_status_stmt->bind_param("ii", $subjectId, $pc_number);
                        $update_pc_status_stmt->execute();
                        $update_pc_status_stmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
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
