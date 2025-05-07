<?php
// Connect to the database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Create connection with error handling
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("<div style='background-color: #f8d7da; border-left: 5px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px;'>
    <h3 style='margin-top:0;color:#721c24;'>Connection failed:</h3> 
    <p>" . $conn->connect_error . "</p>
    </div>");
}

// Define CSS for the output page
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Maintenance Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .card {
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            border-left: 5px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .error {
            background-color: #f8d7da;
            border-left: 5px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info {
            background-color: #e8f4fd;
            border-left: 5px solid #17a2b8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 15px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .maintenance {
            color: #9b59b6;
            font-weight: bold;
            background-color: rgba(155, 89, 182, 0.1);
        }
    </style>
</head>
<body>
    <h1>PC Maintenance Status Fix</h1>
    <div class='card'>";

// Backup the current pc_status table if it exists
$backupData = array();
$hasTable = false;

$result = $conn->query("SHOW TABLES LIKE 'pc_status'");
if ($result->num_rows > 0) {
    $hasTable = true;
    
    // Get existing maintenance records
    $query = "SELECT lab_id, pc_number, status FROM pc_status WHERE status = 'maintenance'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='info'><h3>Backing up existing maintenance records...</h3>";
        echo "<p>Found " . $result->num_rows . " PCs marked as maintenance.</p>";
        
        while ($row = $result->fetch_assoc()) {
            $backupData[] = $row;
        }
        
        echo "<p>Successfully backed up maintenance status for " . count($backupData) . " PCs.</p></div>";
    } else {
        echo "<div class='info'><p>No PCs currently marked as maintenance.</p></div>";
    }
}

// Start a transaction to ensure all operations are completed together
$conn->begin_transaction();

try {
    // Drop the existing table and recreate with correct structure
    $conn->query("DROP TABLE IF EXISTS pc_status");
    
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
        echo "<div class='success'><h3>Table Structure Fixed</h3><p>The pc_status table has been rebuilt with the correct structure.</p></div>";
        
        // Create index for better performance
        $conn->query("CREATE INDEX idx_pc_status_lab_status ON pc_status(lab_id, status)");
        
        // Restore the maintenance records
        if (!empty($backupData)) {
            echo "<div class='info'><h3>Restoring maintenance status...</h3>";
            
            // Prepare the insert statement
            $stmt = $conn->prepare("INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, 'maintenance')");
            $stmt->bind_param("ii", $lab_id, $pc_number);
            
            $restoredCount = 0;
            
            echo "<table>
                <tr>
                    <th>Lab ID</th>
                    <th>PC Number</th>
                    <th>Status</th>
                </tr>";
            
            foreach ($backupData as $record) {
                $lab_id = $record['lab_id'];
                $pc_number = $record['pc_number'];
                
                if ($stmt->execute()) {
                    $restoredCount++;
                    echo "<tr class='maintenance'>
                        <td>" . $lab_id . "</td>
                        <td>" . $pc_number . "</td>
                        <td>maintenance</td>
                    </tr>";
                }
            }
            
            echo "</table>";
            
            if ($restoredCount == count($backupData)) {
                echo "<p>Successfully restored all " . $restoredCount . " maintenance records.</p></div>";
            } else {
                echo "<p>Restored " . $restoredCount . " of " . count($backupData) . " maintenance records.</p></div>";
            }
            
            $stmt->close();
        }
        
        // Verify the table structure
        $result = $conn->query("DESCRIBE pc_status");
        if ($result && $result->num_rows > 0) {
            echo "<div class='success'><h3>Table Structure Verification</h3>";
            echo "<table>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>" . $row['Field'] . "</td>
                    <td>" . $row['Type'] . "</td>
                    <td>" . $row['Null'] . "</td>
                    <td>" . $row['Key'] . "</td>
                    <td>" . $row['Default'] . "</td>
                    <td>" . $row['Extra'] . "</td>
                </tr>";
            }
            
            echo "</table></div>";
        }
        
        // Commit the transaction if everything was successful
        $conn->commit();
        
        echo "<div class='success'>
            <h3>✅ Fix Complete!</h3>
            <p>The database has been fixed successfully. Maintenance status has been preserved.</p>
            <p>You can now return to the Computer Control panel and use the maintenance feature.</p>
            <a href='computer_control.php' class='btn'>Go to Computer Control</a>
        </div>";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }
} catch (Exception $e) {
    // Roll back the transaction if anything failed
    $conn->rollback();
    
    echo "<div class='error'>
        <h3>Error:</h3>
        <p>" . $e->getMessage() . "</p>
        <p>No changes were made to the database.</p>
    </div>";
}

echo "</div>
</body>
</html>";

// Close the connection
$conn->close();
?> 
