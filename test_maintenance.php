<?php
// Simple maintenance status test script

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$message = '';
$success = false;
$lab_id = isset($_GET['lab']) ? intval($_GET['lab']) : 1;
$pc_number = isset($_GET['pc']) ? intval($_GET['pc']) : 1;

// Handle test actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'set_maintenance':
                // Set PC to maintenance
                $conn->begin_transaction();
                $sql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                       VALUES (?, ?, 'maintenance')
                       ON DUPLICATE KEY UPDATE status = 'maintenance'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $message = "PC $pc_number in Lab $lab_id set to MAINTENANCE successfully.";
                    $success = true;
                } else {
                    $conn->rollback();
                    $message = "Error setting maintenance: " . $stmt->error;
                }
                break;
                
            case 'check_status':
                // Check current status
                $sql = "SELECT status FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $status = $result->fetch_assoc()['status'];
                    $message = "PC $pc_number in Lab $lab_id has status: $status";
                    $success = true;
                } else {
                    $message = "PC $pc_number in Lab $lab_id is VACANT (default state - no record in database)";
                    $success = true;
                }
                break;
                
            case 'set_occupied':
                // Set PC to occupied
                $conn->begin_transaction();
                $sql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                       VALUES (?, ?, 'occupied')
                       ON DUPLICATE KEY UPDATE status = 'occupied'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $message = "PC $pc_number in Lab $lab_id set to OCCUPIED successfully.";
                    $success = true;
                } else {
                    $conn->rollback();
                    $message = "Error setting occupied: " . $stmt->error;
                }
                break;
                
            case 'set_vacant':
                // Set PC to vacant (delete record)
                $conn->begin_transaction();
                $sql = "DELETE FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $message = "PC $pc_number in Lab $lab_id set to VACANT successfully.";
                    $success = true;
                } else {
                    $conn->rollback();
                    $message = "Error setting vacant: " . $stmt->error;
                }
                break;
                
            case 'fix_all_maintenance':
                // Get PC count for the lab
                $pcCountSql = "SELECT pc_count FROM subjects WHERE id = ?";
                $stmt = $conn->prepare($pcCountSql);
                $stmt->bind_param("i", $lab_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $lab = $result->fetch_assoc();
                $total_pcs = $lab['pc_count'] ?: 50; // Default to 50 if not set
                
                // Fix maintenance status for all PCs in the lab
                $conn->begin_transaction();
                
                // First get all PCs currently marked as maintenance
                $maintenanceSql = "SELECT pc_number FROM pc_status WHERE lab_id = ? AND status = 'maintenance'";
                $stmt = $conn->prepare($maintenanceSql);
                $stmt->bind_param("i", $lab_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $maintenancePCs = [];
                while ($row = $result->fetch_assoc()) {
                    $maintenancePCs[] = $row['pc_number'];
                }
                
                if (empty($maintenancePCs)) {
                    $message = "No PCs currently marked as maintenance in Lab $lab_id.";
                } else {
                    // Delete and reinsert maintenance records to ensure they're correct
                    $deleteSql = "DELETE FROM pc_status WHERE lab_id = ? AND status = 'maintenance'";
                    $stmt = $conn->prepare($deleteSql);
                    $stmt->bind_param("i", $lab_id);
                    $stmt->execute();
                    
                    // Reinsert maintenance records
                    $insertSql = "INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, 'maintenance')";
                    $stmt = $conn->prepare($insertSql);
                    
                    foreach ($maintenancePCs as $pc) {
                        $stmt->bind_param("ii", $lab_id, $pc);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    $message = "Fixed maintenance status for " . count($maintenancePCs) . " PCs in Lab $lab_id.";
                    $success = true;
                }
                break;
                
            default:
                $message = "Unknown action: $action";
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_error === null) {
            $conn->rollback();
        }
        $message = "Error: " . $e->getMessage();
    }
}

// Get all labs
$labsSql = "SELECT id, lab_number FROM subjects ORDER BY lab_number ASC";
$labsResult = $conn->query($labsSql);
$labs = [];

if ($labsResult && $labsResult->num_rows > 0) {
    while ($row = $labsResult->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Maintenance Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .message {
            padding: 10px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        form {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        select, input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        .btn-red {
            background-color: #dc3545;
        }
        .btn-red:hover {
            background-color: #c82333;
        }
        .btn-blue {
            background-color: #007bff;
        }
        .btn-blue:hover {
            background-color: #0069d9;
        }
        .btn-purple {
            background-color: #6f42c1;
        }
        .btn-purple:hover {
            background-color: #5e37a6;
        }
        .btn-orange {
            background-color: #fd7e14;
        }
        .btn-orange:hover {
            background-color: #e96b02;
        }
        .buttons {
            margin-top: 15px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>PC Maintenance Status Test</h1>
    
    <?php if ($message): ?>
        <div class="message <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="get">
        <div>
            <label for="lab">Select Laboratory:</label>
            <select id="lab" name="lab">
                <?php foreach ($labs as $lab): ?>
                    <option value="<?php echo $lab['id']; ?>" <?php echo $lab_id == $lab['id'] ? 'selected' : ''; ?>>
                        Lab <?php echo htmlspecialchars($lab['lab_number']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label for="pc">PC Number:</label>
            <input type="number" id="pc" name="pc" min="1" max="50" value="<?php echo $pc_number; ?>">
        </div>
        
        <div class="buttons">
            <button type="submit" name="action" value="check_status" class="btn-blue">Check Status</button>
            <button type="submit" name="action" value="set_maintenance" class="btn-purple">Set Maintenance</button>
            <button type="submit" name="action" value="set_occupied" class="btn-red">Set Occupied</button>
            <button type="submit" name="action" value="set_vacant">Set Vacant</button>
            <button type="submit" name="action" value="fix_all_maintenance" class="btn-orange">Fix All Maintenance in Lab</button>
        </div>
    </form>
    
    <div>
        <p>
            <strong>How to use:</strong><br>
            1. Select a lab and PC number<br>
            2. Use the buttons to check or change the status<br>
            3. "Fix All Maintenance" will correct all maintenance statuses in the selected lab
        </p>
        
        <p>
            <strong>Troubleshooting:</strong><br>
            - If maintenance PCs don't show properly, first check their status with "Check Status"<br>
            - If status is incorrect, try "Set Maintenance" again<br>
            - For lab-wide issues, use "Fix All Maintenance"
        </p>
    </div>
    
    <a href="computer_control.php" class="back-link">Back to Computer Control</a>
</body>
</html>
