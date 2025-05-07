<?php
/**
 * Maintenance Status Fix Script
 * 
 * This script is designed to fix issues with PC maintenance status not being properly maintained.
 * It provides:
 * 1. Database validation and repair
 * 2. Status consistency check
 * 3. Manual status repair functionality
 */

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

$message = "";
$success = false;

// Process form submission
if (isset($_POST['fix_maintenance'])) {
    $lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
    
    if ($lab_id > 0) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get all PCs marked for maintenance
            $maintenanceSql = "SELECT * FROM pc_status WHERE lab_id = ? AND status = 'maintenance'";
            $stmt = $conn->prepare($maintenanceSql);
            $stmt->bind_param("i", $lab_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $maintenancePCs = [];
            while ($row = $result->fetch_assoc()) {
                $maintenancePCs[] = $row['pc_number'];
            }
            
            // Re-apply maintenance status to ensure it's properly set
            if (!empty($maintenancePCs)) {
                // First reset existing maintenance entries
                $resetSql = "DELETE FROM pc_status WHERE lab_id = ? AND status = 'maintenance'";
                $stmt = $conn->prepare($resetSql);
                $stmt->bind_param("i", $lab_id);
                $stmt->execute();
                
                // Then add them back
                $insertSql = "INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, 'maintenance')";
                $stmt = $conn->prepare($insertSql);
                
                foreach ($maintenancePCs as $pcNumber) {
                    $stmt->bind_param("ii", $lab_id, $pcNumber);
                    $stmt->execute();
                }
                
                $conn->commit();
                $message = "Successfully fixed maintenance status for " . count($maintenancePCs) . " PCs in Lab " . $lab_id;
                $success = true;
            } else {
                $message = "No PCs are currently marked for maintenance in Lab " . $lab_id;
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Please select a valid laboratory";
    }
}

// Process mark all as maintenance
if (isset($_POST['mark_all_maintenance'])) {
    $lab_id = isset($_POST['lab_id']) ? intval($_POST['lab_id']) : 0;
    
    if ($lab_id > 0) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get PC count for the lab
            $pcCountSql = "SELECT pc_count FROM subjects WHERE id = ?";
            $stmt = $conn->prepare($pcCountSql);
            $stmt->bind_param("i", $lab_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $lab = $result->fetch_assoc();
            $total_pcs = $lab['pc_count'] ?: 50; // Default to 50 if not set
            
            // Clear existing status records for this lab
            $clearSql = "DELETE FROM pc_status WHERE lab_id = ?";
            $stmt = $conn->prepare($clearSql);
            $stmt->bind_param("i", $lab_id);
            $stmt->execute();
            
            // Insert maintenance records
            $insertSql = "INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, 'maintenance')";
            $stmt = $conn->prepare($insertSql);
            
            for ($i = 1; $i <= $total_pcs; $i++) {
                $stmt->bind_param("ii", $lab_id, $i);
                $stmt->execute();
            }
            
            // Verify records were inserted
            $verifySql = "SELECT COUNT(*) as count FROM pc_status WHERE lab_id = ? AND status = 'maintenance'";
            $stmt = $conn->prepare($verifySql);
            $stmt->bind_param("i", $lab_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] == $total_pcs) {
                $conn->commit();
                $message = "Successfully marked all " . $total_pcs . " PCs in Lab " . $lab_id . " as under maintenance";
                $success = true;
            } else {
                $conn->rollback();
                $message = "Error: Only " . $row['count'] . " out of " . $total_pcs . " PCs were updated";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Please select a valid laboratory";
    }
}

// Check database structure
$tableCheck = $conn->query("SHOW TABLES LIKE 'pc_status'");
$tableExists = $tableCheck->num_rows > 0;

// Create table if it doesn't exist
if (!$tableExists) {
    $createTableSql = "CREATE TABLE IF NOT EXISTS pc_status (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        lab_id INT(11) NOT NULL,
        pc_number INT(11) NOT NULL,
        status VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY lab_pc (lab_id, pc_number)
    )";
    
    if ($conn->query($createTableSql) === TRUE) {
        $tableExists = true;
        $message = "pc_status table created successfully";
        $success = true;
    } else {
        $message = "Error creating pc_status table: " . $conn->error;
    }
}

// Get all laboratories
$labsSql = "SELECT id, lab_number, pc_count FROM subjects ORDER BY lab_number ASC";
$labsResult = $conn->query($labsSql);
$labs = [];

if ($labsResult && $labsResult->num_rows > 0) {
    while ($row = $labsResult->fetch_assoc()) {
        $labs[] = $row;
    }
}

// Get PC status statistics
$statistics = [];
if ($tableExists && !empty($labs)) {
    foreach ($labs as $lab) {
        $lab_id = $lab['id'];
        $statsSql = "SELECT status, COUNT(*) as count FROM pc_status WHERE lab_id = ? GROUP BY status";
        $stmt = $conn->prepare($statsSql);
        $stmt->bind_param("i", $lab_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stats = [
            'lab_id' => $lab_id,
            'lab_number' => $lab['lab_number'],
            'total_pcs' => $lab['pc_count'] ?: 50,
            'vacant' => 0,
            'occupied' => 0,
            'maintenance' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = $row['count'];
        }
        
        // Calculate vacant (not in database)
        $stats['vacant'] = $stats['total_pcs'] - $stats['occupied'] - $stats['maintenance'];
        
        $statistics[] = $stats;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Status Fix</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        h1 {
            color: #2980b9;
            margin-bottom: 20px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, button {
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        button {
            background-color: #4a6cf7;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background-color: #3a56d8;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-warning {
            background-color: #f39c12;
        }
        .btn-warning:hover {
            background-color: #e67e22;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
        }
        .status-vacant {
            background-color: #2ecc71;
        }
        .status-occupied {
            background-color: #e74c3c;
        }
        .status-maintenance {
            background-color: #9b59b6;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .card-header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #4a6cf7;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-tools"></i> PC Maintenance Status Fix</h1>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Database Status</div>
            <?php if ($tableExists): ?>
                <p><i class="fas fa-check-circle" style="color: green;"></i> The pc_status table exists in the database.</p>
            <?php else: ?>
                <p><i class="fas fa-exclamation-circle" style="color: red;"></i> The pc_status table does not exist in the database.</p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">Fix Maintenance Status</div>
            <form method="post">
                <div class="form-group">
                    <label for="lab_id">Select Laboratory:</label>
                    <select id="lab_id" name="lab_id" required>
                        <option value="">-- Select Lab --</option>
                        <?php foreach ($labs as $lab): ?>
                            <option value="<?php echo $lab['id']; ?>">
                                Lab <?php echo htmlspecialchars($lab['lab_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" name="fix_maintenance">
                        <i class="fas fa-wrench"></i> Fix Maintenance Status
                    </button>
                    <button type="submit" name="mark_all_maintenance" class="btn-warning">
                        <i class="fas fa-tools"></i> Mark All PCs as Maintenance
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($statistics)): ?>
            <h2>PC Status Statistics</h2>
            <table>
                <thead>
                    <tr>
                        <th>Lab</th>
                        <th>Total PCs</th>
                        <th>Vacant</th>
                        <th>Occupied</th>
                        <th>Maintenance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statistics as $stat): ?>
                        <tr>
                            <td>Lab <?php echo htmlspecialchars($stat['lab_number']); ?></td>
                            <td><?php echo $stat['total_pcs']; ?></td>
                            <td>
                                <span class="status-badge status-vacant">
                                    <?php echo $stat['vacant']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-occupied">
                                    <?php echo $stat['occupied']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-maintenance">
                                    <?php echo $stat['maintenance']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <a href="computer_control.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Computer Control
        </a>
    </div>
    
    <script>
        // Auto-hide alert after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html> 
