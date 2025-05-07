<?php
// Connect to the database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure pc_status table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'pc_status'");
if ($tableCheck->num_rows == 0) {
    // Create the table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS pc_status (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        lab_id INT(11) NOT NULL,
        pc_number INT(11) NOT NULL,
        status VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY lab_pc (lab_id, pc_number)
    )";
    
    $conn->query($createTable);
}

// Start session to check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Process PC status update via dropdown
if (isset($_POST['update_pc_status'])) {
    $lab_id = intval($_POST['lab_id']);
    $pc_number = intval($_POST['pc_number']);
    $new_status = $_POST['new_status'];
    
    // Validate status
    if (!in_array($new_status, ['vacant', 'occupied', 'maintenance'])) {
        $message = "<div class='error-message'>Invalid status selected.</div>";
    } else {
        try {
            // Start a transaction to ensure data consistency
            $conn->begin_transaction();

            // Update status in pc_status table
            if ($new_status === 'vacant') {
                // For vacant, delete the record since our default state is vacant
                $updateSql = "DELETE FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
            } else {
                // For occupied or maintenance, insert/update
                $updateSql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE status = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("iiss", $lab_id, $pc_number, $new_status, $new_status);
            }
            
            if ($stmt->execute()) {
                // Commit the transaction
                $conn->commit();
                
                // Verify the status was properly set
                if ($new_status !== 'vacant') {
                    $verifySql = "SELECT status FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                    $verifyStmt = $conn->prepare($verifySql);
                    $verifyStmt->bind_param("ii", $lab_id, $pc_number);
                    $verifyStmt->execute();
                    $verifyResult = $verifyStmt->get_result();
                    
                    if ($verifyResult->num_rows == 0 || $verifyResult->fetch_assoc()['status'] !== $new_status) {
                        // Status wasn't set properly, try again
                        $conn->begin_transaction();
                        $stmt->execute();
                        $conn->commit();
                    }
                    $verifyStmt->close();
                }
                
                $message = "<div class='success-message'>PC $pc_number status updated to " . ucfirst($new_status) . ".</div>";
            } else {
                // Roll back the transaction if the statement failed
                $conn->rollback();
                $message = "<div class='error-message'>Error updating PC status: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } catch (Exception $e) {
            // Roll back the transaction if there was an exception
            $conn->rollback();
            $message = "<div class='error-message'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Process mark all PCs
if (isset($_POST['mark_all'])) {
    $lab_id = intval($_POST['lab_id']);
    $status = $_POST['mark_status'];
    
    // Get the total PC count for this lab
    $stmt = $conn->prepare("SELECT pc_count FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lab = $result->fetch_assoc();
    $total_pcs = $lab['pc_count'] ?: 50; // Default to 50 if not set
    $stmt->close();
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Delete all existing status records for this lab
        $stmt = $conn->prepare("DELETE FROM pc_status WHERE lab_id = ?");
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        $stmt->close();
        
        // If marking all as occupied or maintenance, insert new records
        if ($status === 'occupied' || $status === 'maintenance') {
            $insertSql = "INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertSql);
            
            for ($i = 1; $i <= $total_pcs; $i++) {
                $stmt->bind_param("iis", $lab_id, $i, $status);
                $stmt->execute();
            }
            $stmt->close();
            
            // Verify records were inserted correctly
            $verifySql = "SELECT COUNT(*) as count FROM pc_status WHERE lab_id = ? AND status = ?";
            $verifyStmt = $conn->prepare($verifySql);
            $verifyStmt->bind_param("is", $lab_id, $status);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            $verifyCount = $verifyResult->fetch_assoc()['count'];
            $verifyStmt->close();
            
            if ($verifyCount != $total_pcs) {
                // Not all records were inserted, try once more
                $stmt = $conn->prepare($insertSql);
                for ($i = 1; $i <= $total_pcs; $i++) {
                    // Skip already inserted records
                    $checkSql = "SELECT id FROM pc_status WHERE lab_id = ? AND pc_number = ? AND status = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("iis", $lab_id, $i, $status);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    
                    if ($checkResult->num_rows == 0) {
                        $stmt->bind_param("iis", $lab_id, $i, $status);
                        $stmt->execute();
                    }
                    $checkStmt->close();
                }
                $stmt->close();
            }
        }
        
        // Commit the transaction
        $conn->commit();
        $message = "<div class='success-message'>All PCs in the lab have been marked as " . ucfirst($status) . ".</div>";
    } catch (Exception $e) {
        // Roll back the transaction if something failed
        $conn->rollback();
        $message = "<div class='error-message'>Error updating PC status: " . $e->getMessage() . "</div>";
    }
}

// Create a function to clean lab number display
function cleanLabNumber($labNumber) {
    // Convert to string if it's not already
    $labNumber = (string)$labNumber;
    
    // Remove any non-numeric characters
    $numericOnly = preg_replace('/[^0-9]/', '', $labNumber);
    
    // Check for consecutive repeated numbers (like 517517)
    // This looks for patterns where the same number sequence is repeated
    for ($i = 1; $i <= strlen($numericOnly) / 2; $i++) {
        $pattern = substr($numericOnly, 0, $i);
        if (preg_match('/^(' . $pattern . ')+$/', $numericOnly)) {
            return $pattern;
        }
    }
    
    // If we reach here, there's no repetition, just return the numeric part
    return $numericOnly;
}

// Get all laboratories
$labsSql = "SELECT id, lab_number, pc_count FROM subjects ORDER BY lab_number ASC";
$labsResult = $conn->query($labsSql);
$labs = [];
$seen_labs = []; // Track already seen lab numbers

// For debugging
$debug_lab_info = [];

while ($row = $labsResult->fetch_assoc()) {
    // Store original lab number for debugging
    $originalLabNumber = $row['lab_number'];
    
    // Clean the lab number
    $cleanedLabNumber = cleanLabNumber($row['lab_number']);
    
    // Add to debug info
    $debug_lab_info[] = [
        'id' => $row['id'],
        'original' => $originalLabNumber,
        'cleaned' => $cleanedLabNumber,
        'is_duplicate' => in_array($cleanedLabNumber, $seen_labs, true)
    ];
    
    // Check if we've already seen this lab number
    if (!in_array($cleanedLabNumber, $seen_labs, true)) {
        $seen_labs[] = $cleanedLabNumber;
        $row['lab_number'] = $cleanedLabNumber; // Replace with cleaned version
        $labs[] = $row;
    }
}

// If no labs are found, add hardcoded labs
if (empty($labs)) {
    $hardcoded_labs = [517, 524, 526, 528, 530, 542, 544];
    foreach ($hardcoded_labs as $index => $lab) {
        $labs[] = [
            'id' => $index + 1,
            'lab_number' => $lab,
            'pc_count' => 50 // Default to 50 PCs
        ];
    }
}

// Get the currently selected lab
$selected_lab_id = isset($_GET['lab_id']) ? intval($_GET['lab_id']) : (count($labs) > 0 ? $labs[0]['id'] : 0);

// Get PC status for selected lab
$pc_status = [];
if ($selected_lab_id > 0) {
    $statusSql = "SELECT pc_number, status FROM pc_status WHERE lab_id = ?";
    $stmt = $conn->prepare($statusSql);
    $stmt->bind_param("i", $selected_lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $pc_status[$row['pc_number']] = $row['status'];
    }
    $stmt->close();
}

// Get the selected lab's details
$selected_lab = null;
foreach ($labs as $lab) {
    if ($lab['id'] == $selected_lab_id) {
        $selected_lab = $lab;
        break;
    }
}

// Get reserved PCs for the selected lab
$reserved_pcs = [];
if ($selected_lab_id > 0) {
    $reservedSql = "SELECT pc_number FROM sit_in_requests 
                   WHERE subject_id = ? AND pc_number IS NOT NULL 
                   AND status IN ('approved', 'pending')
                   AND (end_time IS NULL OR is_active = 1)";
    $stmt = $conn->prepare($reservedSql);
    $stmt->bind_param("i", $selected_lab_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $reserved_pcs[] = $row['pc_number'];
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Computer Control Panel</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --primary-dark: #3a56d8;
            --secondary-color: #2b3a67;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-color: #333;
            --border-color: #e0e0e0;
            --occupied-color: #e74c3c;
            --vacant-color: #2ecc71;
            --reserved-color: #f39c12;
            --maintenance-color: #9b59b6;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }
        
        /* New global layout styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: var(--text-color);
            position: relative;
            display: flex;
            margin: 0;
            padding: 0;
        }
        
        /* Left Sidebar Navigation */
        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: #2c3e50;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: #ecf0f1;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .sidebar-header h3 {
            color: #ecf0f1;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            color: #bdc3c7;
            font-size: 12px;
        }
        
        .nav-links {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .nav-links a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 12px 20px;
            transition: background-color 0.3s, border-left 0.3s;
            border-left: 3px solid transparent;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(26, 188, 156, 0.2);
            border-left: 3px solid #1abc9c;
        }
        
        .nav-links a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .logout-container {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .logout-container a {
            display: block;
            padding: 10px;
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            transition: background-color 0.3s;
        }

        .logout-container a:hover {
            background-color: #c0392b;
        }
        
        /* Toggle button for mobile */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: #2c3e50;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            z-index: 1001;
            cursor: pointer;
            font-size: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            transition: margin-left 0.3s, width 0.3s;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .content {
            width: 100%;
            flex: 1;
            padding-bottom: 20px;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 20px;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            margin-top: 30px;
            width: 100%;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-250px);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            body.sidebar-active .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
            
            body.sidebar-active .sidebar-toggle {
                left: 265px;
            }
        }
        
        @media (max-width: 768px) {
            body.sidebar-active .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        /* Remove old nav styles - they'll be replaced by sidebar styles */
        nav {
            display: none;
        }
        
        /* Card styling */
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
        }
        
        .card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--secondary-color);
            color: white;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card-header h2 i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Lab selector */
        .lab-selector {
            margin-bottom: 25px;
            background-color: var(--light-color);
            padding: 20px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
            padding: 0 10px;
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background-color: white;
            transition: var(--transition);
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.2);
        }
        
        /* Status Legend */
        .status-legend {
            display: flex;
            gap: 20px;
            margin: 25px 0;
            padding: 15px;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            padding: 8px 15px;
            background-color: white;
            border-radius: 50px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .status-badge {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-badge.vacant {
            background-color: var(--vacant-color);
        }
        
        .status-badge.occupied {
            background-color: var(--occupied-color);
        }
        
        .status-badge.reserved {
            background-color: var(--reserved-color);
        }
        
        .status-badge.maintenance {
            background-color: var(--maintenance-color);
        }
        
        /* PC Actions */
        .pc-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            color: white;
            background-color: var(--primary-color);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .btn-occupied {
            background-color: var(--danger-color);
        }
        
        .btn-occupied:hover {
            background-color: #c0392b;
        }
        
        .btn-vacant {
            background-color: var(--success-color);
        }
        
        .btn-vacant:hover {
            background-color: #27ae60;
        }
        
        .btn-maintenance {
            background-color: var(--maintenance-color);
        }
        
        .btn-maintenance:hover {
            background-color: #8e44ad;
        }
        
        .lab-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding: 10px 15px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .lab-info p {
            margin: 5px 0;
            font-size: 16px;
            color: var(--text-color);
        }
        
        .lab-info .lab-number {
            font-size: 20px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* PC Grid Container */
        .pc-grid-container {
            position: relative;
            padding: 20px;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .grid-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .grid-controls .form-group {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
        
        .grid-controls .form-group label {
            margin-right: 10px;
            margin-bottom: 0;
            white-space: nowrap;
        }
        
        .grid-controls select {
            width: auto;
            padding: 8px 12px;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 300px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .search-box input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.2);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        /* PC Grid */
        .pc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .pc-grid.size-small {
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        }
        
        .pc-grid.size-medium {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        }
        
        .pc-grid.size-large {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        }
        
        .pc-grid.size-xl {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
        
        .pc-item {
            position: relative;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            transition: var(--transition);
            transform-origin: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .pc-item:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            z-index: 1;
        }
        
        .pc-header {
            padding: 12px 10px;
            text-align: center;
            font-weight: 600;
            background-color: var(--secondary-color);
            color: white;
            font-size: 15px;
        }
        
        .pc-status {
            width: 100%;
            padding: 20px 10px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: none;
            transition: background-color 0.3s, transform 0.2s;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            outline: none;
            font-family: inherit;
        }
        
        .pc-status:focus {
            outline: none;
        }
        
        .pc-status.vacant {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--vacant-color);
        }
        
        .pc-status.vacant:hover {
            background-color: rgba(46, 204, 113, 0.2);
        }
        
        .pc-status.occupied {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--occupied-color);
        }
        
        .pc-status.occupied:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }
        
        .pc-status.reserved {
            background-color: rgba(243, 156, 18, 0.1);
            color: var(--reserved-color);
            cursor: not-allowed;
        }
        
        .pc-status.maintenance {
            background-color: rgba(155, 89, 182, 0.2);
            color: var(--maintenance-color);
            background-image: repeating-linear-gradient(
                45deg,
                rgba(155, 89, 182, 0.2),
                rgba(155, 89, 182, 0.2) 10px,
                rgba(155, 89, 182, 0.3) 10px,
                rgba(155, 89, 182, 0.3) 20px
            );
            position: relative;
            overflow: hidden;
            font-weight: bold;
            border-bottom: 3px solid var(--maintenance-color);
        }
        
        .pc-status.maintenance:after {
            content: "⚠";
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 10px;
            color: var(--maintenance-color);
        }
        
        .pc-status i {
            display: block;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .pc-item.hidden {
            display: none;
        }
        
        /* Alert */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .alert-info {
            background-color: #e8f4fd;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .alert i {
            margin-right: 10px;
        }
        
        /* Messages */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            animation: fadeInUp 0.4s ease;
            display: flex;
            align-items: center;
        }
        
        .success-message:before {
            content: "\f058";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 10px;
            font-size: 20px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            animation: shake 0.5s ease;
            display: flex;
            align-items: center;
        }
        
        .error-message:before {
            content: "\f06a";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 10px;
            font-size: 20px;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 5px solid var(--primary-color);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 90%;
            text-align: center;
            position: relative;
            animation: modalIn 0.3s ease;
        }
        
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-title {
            color: var(--secondary-color);
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .modal-message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
            color: #555;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Left Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h3>Sit-in Monitoring</h3>
            <p>Admin Panel</p>
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="computer_control.php" class="active">
                <i class="fas fa-desktop"></i> Computer Control
            </a>
            <a href="manage_sit_in_requests.php">
                <i class="fas fa-tasks"></i> Manage Requests
            </a>
            <a href="todays_sit_in_records.php"><i class="fas fa-clipboard-list"></i> Today's Records</a>
            <a href="active_sitin.php"><i class="fas fa-user-clock"></i> Active Sit-ins</a>
            <a href="add_subject.php"><i class="fas fa-book"></i> Add Subject</a>
            <a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="lab_schedules_admin.php"><i class="fas fa-calendar-alt"></i> Lab Schedules</a>
        </div>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content">
            <?php echo $message; ?>
            
            <h1>Computer Control Panel</h1>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-desktop"></i> Computer Control Panel</h2>
                </div>
                <div class="card-body">
                    <p>Manage PC availability status for laboratory rooms. This will affect which PCs students can reserve.</p>
                    
                    <!-- Lab Selector -->
                    <div class="lab-selector">
                        <form method="GET" action="">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="lab_id">Select Laboratory:</label>
                                    <select id="lab_id" name="lab_id" class="form-control" onchange="this.form.submit()">
                                        <?php foreach ($labs as $lab): ?>
                                        <option value="<?php echo $lab['id']; ?>" <?php echo $selected_lab_id == $lab['id'] ? 'selected' : ''; ?>>
                                            Lab <?php echo htmlspecialchars($lab['lab_number']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <?php 
                    // Debug information for admin
                    if (isset($_GET['debug']) && $_GET['debug'] == 1): 
                    ?>
                    <div style="margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-left: 5px solid #dc3545; border-radius: 5px;">
                        <h3>Debug Information</h3>
                        <p><strong>Lab Cleaning Results:</strong></p>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                            <tr style="background-color: #e9ecef;">
                                <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">ID</th>
                                <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Original</th>
                                <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Cleaned</th>
                                <th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Is Duplicate</th>
                            </tr>
                            <?php foreach($debug_lab_info as $info): ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo $info['id']; ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($info['original']); ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($info['cleaned']); ?></td>
                                <td style="padding: 8px; border: 1px solid #dee2e6;"><?php echo $info['is_duplicate'] ? 'Yes' : 'No'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($selected_lab): ?>
                        <!-- Status Legend -->
                        <div class="status-legend">
                            <div class="status-item">
                                <span class="status-badge vacant"></span> Vacant
                            </div>
                            <div class="status-item">
                                <span class="status-badge occupied"></span> Occupied
                            </div>
                            <div class="status-item">
                                <span class="status-badge reserved"></span> Reserved
                            </div>
                            <div class="status-item">
                                <span class="status-badge maintenance"></span> Maintenance
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="pc-actions">
                            <div class="action-buttons">
                                <button type="button" class="btn btn-vacant" id="markAllVacantBtn">
                                    <i class="fas fa-unlock"></i> Mark All as Vacant
                                </button>
                                <button type="button" class="btn btn-occupied" id="markAllOccupiedBtn">
                                    <i class="fas fa-lock"></i> Mark All as Occupied
                                </button>
                                <button type="button" class="btn btn-maintenance" id="markAllMaintenanceBtn">
                                    <i class="fas fa-tools"></i> Mark All as Maintenance
                                </button>
                            </div>
                            
                            <div class="lab-info">
                                <p class="lab-number">Lab <?php echo htmlspecialchars($selected_lab['lab_number']); ?></p>
                                <p>Total PCs: <?php echo $selected_lab['pc_count'] ?: 50; ?></p>
                            </div>
                        </div>
                        
                        <!-- PC Grid Container -->
                        <div class="pc-grid-container">
                            <!-- Grid Controls -->
                            <div class="grid-controls">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="pc-search" class="form-control" placeholder="Search PC number...">
                                </div>
                            </div>
                            
                            <!-- PC Grid -->
                            <div class="pc-grid size-xl">
                                <?php
                                $total_pcs = $selected_lab['pc_count'] ?: 50;
                                for ($i = 1; $i <= $total_pcs; $i++):
                                    // Determine PC status
                                    $status = 'vacant'; // Default status
                                    if (in_array($i, $reserved_pcs)) {
                                        $status = 'reserved';
                                    } elseif (isset($pc_status[$i])) {
                                        $status = $pc_status[$i]; // Use exact status from database
                                    }
                                    
                                    $status_text = ucfirst($status);
                                    
                                    // Determine icon based on status
                                    if ($status === 'vacant') {
                                        $icon = 'unlock';
                                    } elseif ($status === 'occupied') {
                                        $icon = 'lock';
                                    } elseif ($status === 'reserved') {
                                        $icon = 'user-clock';
                                    } elseif ($status === 'maintenance') {
                                        $icon = 'tools';
                                    } else {
                                        $icon = 'unlock'; // Default fallback
                                    }
                                ?>
                                <div class="pc-item" data-pc-number="<?php echo $i; ?>">
                                    <div class="pc-header">PC <?php echo $i; ?></div>
                                    <div class="pc-status <?php echo $status; ?>">
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                        <?php echo $status_text; ?>
                                    </div>
                                    
                                    <?php if ($status != 'reserved'): ?>
                                    <form method="POST" class="status-form" style="margin-top: 10px;">
                                        <input type="hidden" name="lab_id" value="<?php echo $selected_lab_id; ?>">
                                        <input type="hidden" name="pc_number" value="<?php echo $i; ?>">
                                        <select name="new_status" class="form-control status-select" style="width: 100%; margin-bottom: 5px;">
                                            <option value="vacant" <?php echo $status === 'vacant' ? 'selected' : ''; ?>>Vacant</option>
                                            <option value="occupied" <?php echo $status === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                            <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        </select>
                                        <button type="submit" name="update_pc_status" class="btn btn-sm btn-primary" style="width: 100%;">Update</button>
                                    </form>
                                    <?php else: ?>
                                    <div class="alert alert-warning" style="margin-top: 10px; padding: 5px; font-size: 12px;">
                                        <i class="fas fa-info-circle"></i> Reserved
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Please add laboratory rooms first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
        </footer>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal" style="display: none;">
        <div class="modal-content">
            <h3 class="modal-title">Confirmation</h3>
            <p class="modal-message">Are you sure you want to perform this action?</p>
            <div class="modal-buttons">
                <button type="button" class="btn btn-primary">Confirm</button>
                <button type="button" class="btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
    
    <script>
        // Sidebar Toggle Functionality
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.body.classList.toggle('sidebar-active');
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                var messages = document.querySelectorAll('.success-message, .error-message');
                messages.forEach(function(message) {
                    message.style.display = 'none';
                });
            }, 5000);
            
            // Set up PC search functionality
            const searchInput = document.getElementById('pc-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.trim().toLowerCase();
                    filterPcItems(searchTerm);
                });
            }
            
            // Set up Mark All buttons
            const markAllOccupiedBtn = document.getElementById('markAllOccupiedBtn');
            if (markAllOccupiedBtn) {
                markAllOccupiedBtn.addEventListener('click', function() {
                    showConfirmationModal(
                        'Mark All PCs as Occupied', 
                        'Are you sure you want to mark all PCs as occupied? This will make all PCs unavailable for reservation.',
                        function() {
                            submitMarkAllForm('occupied');
                        }
                    );
                });
            }
            
            const markAllVacantBtn = document.getElementById('markAllVacantBtn');
            if (markAllVacantBtn) {
                markAllVacantBtn.addEventListener('click', function() {
                    showConfirmationModal(
                        'Mark All PCs as Vacant', 
                        'Are you sure you want to mark all PCs as vacant? This will make all PCs available for reservation.',
                        function() {
                            submitMarkAllForm('vacant');
                        }
                    );
                });
            }
            
            const markAllMaintenanceBtn = document.getElementById('markAllMaintenanceBtn');
            if (markAllMaintenanceBtn) {
                markAllMaintenanceBtn.addEventListener('click', function() {
                    showConfirmationModal(
                        'Mark All PCs as Maintenance', 
                        'Are you sure you want to mark all PCs as under maintenance? This will make all PCs unavailable for reservation.',
                        function() {
                            submitMarkAllForm('maintenance');
                        }
                    );
                });
            }
            
            // Get all maintenance status buttons and enhance their appearance
            document.querySelectorAll('.status-select').forEach(function(select) {
                select.addEventListener('change', function() {
                    const pcItem = this.closest('.pc-item');
                    const statusValue = this.value;
                    
                    // Update visual indicator immediately
                    const statusDiv = pcItem.querySelector('.pc-status');
                    statusDiv.className = 'pc-status ' + statusValue;
                    
                    // Update icon
                    const icon = statusDiv.querySelector('i');
                    if (icon) {
                        if (statusValue === 'vacant') {
                            icon.className = 'fas fa-unlock';
                        } else if (statusValue === 'occupied') {
                            icon.className = 'fas fa-lock';
                        } else if (statusValue === 'maintenance') {
                            icon.className = 'fas fa-tools';
                        }
                    }
                    
                    // Update text
                    const textNode = Array.from(statusDiv.childNodes).find(node => node.nodeType === 3);
                    if (textNode) {
                        textNode.nodeValue = statusValue.charAt(0).toUpperCase() + statusValue.slice(1);
                    }
                });
            });
        });
        
        // Function to filter PC items based on search term
        function filterPcItems(searchTerm) {
            const pcItems = document.querySelectorAll('.pc-item');
            
            pcItems.forEach(item => {
                const pcNumber = item.getAttribute('data-pc-number');
                
                if (searchTerm === '' || pcNumber.includes(searchTerm)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }
        
        // Function to submit the mark all form
        function submitMarkAllForm(status) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const labIdInput = document.createElement('input');
            labIdInput.type = 'hidden';
            labIdInput.name = 'lab_id';
            labIdInput.value = <?php echo $selected_lab_id; ?>;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'mark_status';
            statusInput.value = status;
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'mark_all';
            submitInput.value = '1';
            
            form.appendChild(labIdInput);
            form.appendChild(statusInput);
            form.appendChild(submitInput);
            
            document.body.appendChild(form);
            
            showLoading();
            form.submit();
        }
        
        // Show loading overlay
        function showLoading() {
            let loadingOverlay = document.querySelector('.loading-overlay');
            
            if (!loadingOverlay) {
                loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'loading-overlay';
                
                const spinner = document.createElement('div');
                spinner.className = 'loading-spinner';
                
                loadingOverlay.appendChild(spinner);
                document.body.appendChild(loadingOverlay);
            }
            
            loadingOverlay.style.display = 'flex';
        }
        
        // Hide loading overlay
        function hideLoading() {
            const loadingOverlay = document.querySelector('.loading-overlay');
            
            if (loadingOverlay) {
                loadingOverlay.style.display = 'none';
            }
        }
        
        // Show confirmation modal
        function showConfirmationModal(title, message, onConfirm) {
            let modal = document.querySelector('.modal');
            
            if (!modal) {
                modal = document.createElement('div');
                modal.className = 'modal';
                
                const modalContent = document.createElement('div');
                modalContent.className = 'modal-content';
                
                const modalTitle = document.createElement('h3');
                modalTitle.className = 'modal-title';
                
                const modalMessage = document.createElement('p');
                modalMessage.className = 'modal-message';
                
                const modalButtons = document.createElement('div');
                modalButtons.className = 'modal-buttons';
                
                const confirmButton = document.createElement('button');
                confirmButton.type = 'button';
                confirmButton.className = 'btn btn-primary';
                confirmButton.textContent = 'Confirm';
                
                const cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'btn btn-secondary';
                cancelButton.textContent = 'Cancel';
                
                modalButtons.appendChild(confirmButton);
                modalButtons.appendChild(cancelButton);
                
                modalContent.appendChild(modalTitle);
                modalContent.appendChild(modalMessage);
                modalContent.appendChild(modalButtons);
                
                modal.appendChild(modalContent);
                document.body.appendChild(modal);
                
                // Set up event listeners
                cancelButton.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
                
                modal.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // Update modal content
            const modalTitle = modal.querySelector('.modal-title');
            const modalMessage = modal.querySelector('.modal-message');
            const confirmButton = modal.querySelector('.btn-primary');
            
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            
            // Update confirm button click handler
            const newConfirmButton = confirmButton.cloneNode(true);
            confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
            
            newConfirmButton.addEventListener('click', function() {
                modal.style.display = 'none';
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
            
            // Show the modal
            modal.style.display = 'flex';
        }
    </script>
</body>
</html> 
