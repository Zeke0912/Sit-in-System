<?php
// Simple script to fix PC maintenance status issues

// Database connection
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

$message = "";

// Create the table if it doesn't exist - but first drop it to ensure fresh start
if (isset($_POST['rebuild_table']) && $_POST['rebuild_table'] === 'yes') {
    $dropTable = "DROP TABLE IF EXISTS pc_status";
    
    if ($conn->query($dropTable)) {
        $message .= "Existing table dropped successfully.<br>";
    } else {
        $message .= "Error dropping table: " . $conn->error . "<br>";
    }
    
    $createTableSql = "CREATE TABLE pc_status (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        lab_id INT(11) NOT NULL,
        pc_number INT(11) NOT NULL,
        status VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY lab_pc (lab_id, pc_number)
    )";
    
    if ($conn->query($createTableSql)) {
        $message .= "PC Status table created successfully.<br>";
    } else {
        $message .= "Error creating table: " . $conn->error . "<br>";
    }
    
    // Create index for better performance
    $indexSql = "CREATE INDEX idx_pc_status_lab_status ON pc_status(lab_id, status)";
    if ($conn->query($indexSql)) {
        $message .= "Performance index created successfully.<br>";
    }
} else {
    // Just verify the table exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS pc_status (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        lab_id INT(11) NOT NULL,
        pc_number INT(11) NOT NULL,
        status VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY lab_pc (lab_id, pc_number)
    )";
    
    if ($conn->query($createTableSql)) {
        $message .= "PC Status table verified.<br>";
    } else {
        $message .= "Error with table: " . $conn->error . "<br>";
    }
}

// Handle form submission
if (isset($_POST['lab_id']) && isset($_POST['action'])) {
    $lab_id = intval($_POST['lab_id']);
    $action = $_POST['action'];
    
    // Different actions
    if ($action === 'mark_all_maintenance') {
        // Mark all PCs in a lab as maintenance
        $total_pcs = isset($_POST['pc_count']) ? intval($_POST['pc_count']) : 50;
        
        $conn->begin_transaction();
        
        // First remove any existing status for this lab
        $stmt = $conn->prepare("DELETE FROM pc_status WHERE lab_id = ?");
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        
        // Add maintenance status for all PCs
        $insertSql = "INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, 'maintenance')";
        $stmt = $conn->prepare($insertSql);
        
        $success = true;
        for ($i = 1; $i <= $total_pcs; $i++) {
            $stmt->bind_param("ii", $lab_id, $i);
            if (!$stmt->execute()) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $conn->commit();
            $message .= "Successfully marked all $total_pcs PCs in Lab $lab_id as maintenance.<br>";
        } else {
            $conn->rollback();
            $message .= "Error marking PCs: " . $stmt->error . "<br>";
        }
    } elseif ($action === 'check_status') {
        // Check status of all PCs in lab
        $result = $conn->query("SELECT pc_number, status FROM pc_status WHERE lab_id = $lab_id ORDER BY pc_number");
        
        if ($result && $result->num_rows > 0) {
            $message .= "<strong>Current PC Status for Lab $lab_id:</strong><br>";
            $message .= "<table border='1' cellpadding='5'><tr><th>PC Number</th><th>Status</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                $message .= "<tr><td>{$row['pc_number']}</td><td>{$row['status']}</td></tr>";
            }
            
            $message .= "</table><br>";
        } else {
            $message .= "No status records found for Lab $lab_id (all PCs are vacant by default).<br>";
        }
    } elseif ($action === 'fix_specific_pc') {
        // Fix a specific PC
        $pc_number = isset($_POST['pc_number']) ? intval($_POST['pc_number']) : 0;
        $status = isset($_POST['status']) ? $_POST['status'] : 'maintenance';
        
        if ($pc_number > 0) {
            $conn->begin_transaction();
            
            if ($status === 'vacant') {
                // For vacant, delete the record
                $sql = "DELETE FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
            } else {
                // For other statuses, insert/update
                $sql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                        VALUES (?, ?, ?) 
                        ON DUPLICATE KEY UPDATE status = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $lab_id, $pc_number, $status, $status);
            }
            
            if ($stmt->execute()) {
                $conn->commit();
                $message .= "Successfully set PC $pc_number in Lab $lab_id to " . ucfirst($status) . ".<br>";
            } else {
                $conn->rollback();
                $message .= "Error updating PC status: " . $stmt->error . "<br>";
            }
        }
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
    <title>Fix PC Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .message {
            background: #e8f4fd;
            padding: 15px;
            border-left: 4px solid #2196F3;
            margin-bottom: 20px;
        }
        .card {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.05);
        }
        h2 {
            color: #3498db;
            font-size: 18px;
            margin-top: 0;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background: #2980b9;
        }
        .btn-danger {
            background: #e74c3c;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>PC Status Maintenance Fix</h1>
        
        <?php if ($message): ?>
            <div class="message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Reset Database Table</h2>
            <div class="warning">
                <strong>Warning:</strong> This will delete and recreate the pc_status table. All current PC status data will be lost.
            </div>
            <form method="post">
                <input type="hidden" name="rebuild_table" value="yes">
                <button type="submit" class="btn-danger">Rebuild PC Status Table</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Mark All PCs as Maintenance</h2>
            <form method="post">
                <label for="lab_id">Lab ID:</label>
                <input type="number" id="lab_id" name="lab_id" min="1" required>
                
                <label for="pc_count">Number of PCs in Lab:</label>
                <input type="number" id="pc_count" name="pc_count" min="1" max="200" value="50">
                
                <input type="hidden" name="action" value="mark_all_maintenance">
                <button type="submit">Mark All as Maintenance</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Check Status of Lab PCs</h2>
            <form method="post">
                <label for="check_lab_id">Lab ID:</label>
                <input type="number" id="check_lab_id" name="lab_id" min="1" required>
                
                <input type="hidden" name="action" value="check_status">
                <button type="submit">Check Status</button>
            </form>
        </div>
        
        <div class="card">
            <h2>Set Individual PC Status</h2>
            <form method="post">
                <label for="fix_lab_id">Lab ID:</label>
                <input type="number" id="fix_lab_id" name="lab_id" min="1" required>
                
                <label for="pc_number">PC Number:</label>
                <input type="number" id="pc_number" name="pc_number" min="1" max="200" required>
                
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="maintenance">Maintenance</option>
                    <option value="occupied">Occupied</option>
                    <option value="vacant">Vacant</option>
                </select>
                
                <input type="hidden" name="action" value="fix_specific_pc">
                <button type="submit">Update PC Status</button>
            </form>
        </div>
        
        <p>
            <strong>How to use:</strong><br>
            1. If you're having serious issues, use "Rebuild PC Status Table" first<br>
            2. To fix all PCs in a lab: Enter the lab ID and number of PCs, then click "Mark All as Maintenance"<br>
            3. To check current status: Enter the lab ID and click "Check Status"<br>
            4. To set a specific PC: Enter the lab ID, PC number, select status, and click "Update PC Status"
        </p>
        
        <a href="computer_control.php" class="back-link">? Back to Computer Control</a>
    </div>
</body>
</html>
