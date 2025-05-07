<?php
// This script handles fetching available PCs for a selected laboratory

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to browser for JSON responses

// Start session for authentication
session_start();

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Check if the action parameter is set
if (isset($_POST['action']) && $_POST['action'] === 'fetch_pcs') {
    // Check if subject ID is provided
    if (isset($_POST['subjectId']) && !empty($_POST['subjectId'])) {
        $subjectId = intval($_POST['subjectId']);
        
        try {
            // Get the total PC count for this lab from subjects table
            $stmt = $conn->prepare("SELECT lab_number, pc_count FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Subject not found']);
                exit;
            }
            
            $subject = $result->fetch_assoc();
            $lab_number = $subject['lab_number'];
            $total_pcs = $subject['pc_count'] ?: 50; // Default to 50 if not set
            
            // Get PCs that are already in use from sit_in_requests table
            // Only consider approved requests, not pending ones
            $stmt = $conn->prepare("
                SELECT pc_number 
                FROM sit_in_requests 
                WHERE subject_id = ? 
                AND pc_number IS NOT NULL 
                AND status = 'approved'
                AND (end_time IS NULL OR is_active = 1)
            ");
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $occupied_pcs = [];
            $reserved_pcs = [];
            $maintenance_pcs = [];
            
            while ($row = $result->fetch_assoc()) {
                $occupied_pcs[] = (int)$row['pc_number'];
            }
            
            // Also check for PCs with status in the pc_status table
            $checkPcStatusTable = $conn->query("SHOW TABLES LIKE 'pc_status'");
            if ($checkPcStatusTable && $checkPcStatusTable->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT pc_number, status
                    FROM pc_status 
                    WHERE lab_id = ?
                ");
                $stmt->bind_param("i", $subjectId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $pcNum = (int)$row['pc_number'];
                    switch ($row['status']) {
                        case 'occupied':
                            if (!in_array($pcNum, $occupied_pcs)) {
                                $occupied_pcs[] = $pcNum;
                            }
                            break;
                        case 'maintenance':
                            $maintenance_pcs[] = $pcNum;
                            break;
                        case 'reserved':
                            $reserved_pcs[] = $pcNum;
                            break;
                    }
                }
            }
            
            // Combine all unavailable PCs
            $unavailable_pcs = array_unique(array_merge($occupied_pcs, $maintenance_pcs, $reserved_pcs));
            
            // Create an array of available PCs
            $available_pcs = [];
            for ($i = 1; $i <= $total_pcs; $i++) {
                if (!in_array($i, $unavailable_pcs)) {
                    $available_pcs[] = $i;
                }
            }
            
            // Return the list of available and unavailable PCs with statuses
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'lab_number' => $lab_number,
                'available_pcs' => $available_pcs,
                'total_pcs' => $total_pcs,
                'occupied_pcs' => $occupied_pcs,
                'maintenance_pcs' => $maintenance_pcs,
                'reserved_pcs' => $reserved_pcs
            ]);
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No subject ID provided']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

// Close the connection
$conn->close();
?> 
