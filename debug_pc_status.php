<?php
// Debug script for PC Status table
header('Content-Type: text/html; charset=utf-8');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("<div style='color:red;'>Connection failed: " . $conn->connect_error . "</div>");
    }
    
    echo "<h1>PC Status Debugging Tool</h1>";
    
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'pc_status'");
    if ($tableCheck->num_rows == 0) {
        echo "<div style='color:red;'><strong>ERROR:</strong> The 'pc_status' table doesn't exist!</div>";
        
        // Create the table if it doesn't exist
        echo "<h2>Creating PC Status Table</h2>";
        
        $createTable = "CREATE TABLE IF NOT EXISTS pc_status (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            lab_id INT(11) NOT NULL,
            pc_number INT(11) NOT NULL,
            status VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY lab_pc (lab_id, pc_number)
        )";
        
        if ($conn->query($createTable) === TRUE) {
            echo "<div style='color:green;'>Table created successfully</div>";
        } else {
            echo "<div style='color:red;'>Error creating table: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color:green;'>The 'pc_status' table exists.</div>";
    }
    
    // Describe table structure
    echo "<h2>PC Status Table Structure</h2>";
    $result = $conn->query("DESCRIBE pc_status");
    
    if ($result) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] === NULL ? 'NULL' : $row['Default']) . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<div style='color:red;'>Error describing table: " . $conn->error . "</div>";
    }
    
    // Get all entries in pc_status
    echo "<h2>Current PC Status Data</h2>";
    $result = $conn->query("SELECT * FROM pc_status ORDER BY lab_id, pc_number");
    
    if ($result) {
        if ($result->num_rows > 0) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>ID</th><th>Lab ID</th><th>PC Number</th><th>Status</th><th>Created At</th><th>Updated At</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                $statusColor = '';
                if ($row['status'] == 'occupied') {
                    $statusColor = 'red';
                } elseif ($row['status'] == 'maintenance') {
                    $statusColor = 'purple';
                } elseif ($row['status'] == 'vacant') {
                    $statusColor = 'green';
                }
                
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['lab_id'] . "</td>";
                echo "<td>" . $row['pc_number'] . "</td>";
                echo "<td style='color: " . $statusColor . "; font-weight: bold;'>" . $row['status'] . "</td>";
                echo "<td>" . $row['created_at'] . "</td>";
                echo "<td>" . $row['updated_at'] . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<div style='color:blue;'>No data found in the pc_status table.</div>";
        }
    } else {
        echo "<div style='color:red;'>Error retrieving data: " . $conn->error . "</div>";
    }
    
    // Test maintenance functionality
    echo "<h2>Test Maintenance Functionality</h2>";
    echo "<form method='post'>";
    echo "<label>Lab ID: <input type='number' name='lab_id' value='1' required></label><br><br>";
    echo "<label>PC Number: <input type='number' name='pc_number' value='1' required></label><br><br>";
    echo "<label>Status: <select name='status' required>";
    echo "<option value='vacant'>Vacant</option>";
    echo "<option value='occupied'>Occupied</option>";
    echo "<option value='maintenance'>Maintenance</option>";
    echo "</select></label><br><br>";
    echo "<button type='submit' name='update_status'>Update Status</button>";
    echo "</form>";
    
    // Handle status update
    if (isset($_POST['update_status'])) {
        $lab_id = intval($_POST['lab_id']);
        $pc_number = intval($_POST['pc_number']);
        $status = $_POST['status'];
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Update status in pc_status table
            if ($status === 'vacant') {
                // For vacant, delete the record
                $updateSql = "DELETE FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("ii", $lab_id, $pc_number);
            } else {
                // For occupied or maintenance, insert/update
                $updateSql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE status = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("iiss", $lab_id, $pc_number, $status, $status);
            }
            
            if ($stmt->execute()) {
                // Commit the transaction
                $conn->commit();
                echo "<div style='color:green;'>PC $pc_number in Lab $lab_id status successfully updated to " . ucfirst($status) . ".</div>";
                
                // Check if the record is really set
                echo "<h3>Database Verification</h3>";
                if ($status === 'vacant') {
                    $checkSql = "SELECT COUNT(*) as count FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("ii", $lab_id, $pc_number);
                } else {
                    $checkSql = "SELECT status FROM pc_status WHERE lab_id = ? AND pc_number = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("ii", $lab_id, $pc_number);
                }
                
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $checkRow = $checkResult->fetch_assoc();
                
                if ($status === 'vacant') {
                    if ($checkRow['count'] == 0) {
                        echo "<div style='color:green;'>Record was successfully deleted for vacant status.</div>";
                    } else {
                        echo "<div style='color:red;'>Error: Record still exists after setting to vacant!</div>";
                    }
                } else {
                    if ($checkRow['status'] === $status) {
                        echo "<div style='color:green;'>Record was successfully set to $status.</div>";
                    } else {
                        echo "<div style='color:red;'>Error: Record status is " . $checkRow['status'] . " instead of $status!</div>";
                    }
                }
                $checkStmt->close();
            } else {
                // Roll back the transaction if the statement failed
                $conn->rollback();
                echo "<div style='color:red;'>Error updating PC status: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } catch (Exception $e) {
            // Roll back the transaction if there was an exception
            $conn->rollback();
            echo "<div style='color:red;'>Error: " . $e->getMessage() . "</div>";
        }
        
        // Refresh the page after 2 seconds to show updated data
        echo "<script>setTimeout(function(){ window.location.reload(); }, 2000);</script>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<div style='color:red;'>Error: " . $e->getMessage() . "</div>";
}
?> 
