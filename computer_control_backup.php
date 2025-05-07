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

// Start session to check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Process individual PC status change
if (isset($_POST['toggle_pc_status'])) {
    $lab_id = intval($_POST['lab_id']);
    $pc_number = intval($_POST['pc_number']);
    $status = $_POST['status'] === 'vacant' ? 'occupied' : 'vacant';
    
    // Update status in pc_status table
    $updateSql = "INSERT INTO pc_status (lab_id, pc_number, status) 
                 VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE status = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("iiss", $lab_id, $pc_number, $status, $status);
    
    if ($stmt->execute()) {
        $message = "<div class='success-message'>PC $pc_number status updated successfully.</div>";
    } else {
        $message = "<div class='error-message'>Error updating PC status: " . $stmt->error . "</div>";
    }
    $stmt->close();
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
        
        // If marking all as occupied, insert new records
        if ($status === 'occupied') {
            $insertSql = "INSERT INTO pc_status (lab_id, pc_number, status) VALUES (?, ?, 'occupied')";
            $stmt = $conn->prepare($insertSql);
            
            for ($i = 1; $i <= $total_pcs; $i++) {
                $stmt->bind_param("ii", $lab_id, $i);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        // Commit the transaction
        $conn->commit();
        $message = "<div class='success-message'>All PCs in the lab have been marked as $status.</div>";
    } catch (Exception $e) {
        // Roll back the transaction if something failed
        $conn->rollback();
        $message = "<div class='error-message'>Error updating PC status: " . $e->getMessage() . "</div>";
    }
}

// Get all laboratories
$labsSql = "SELECT id, lab_number, pc_count FROM subjects ORDER BY lab_number ASC";
$labsResult = $conn->query($labsSql);
$labs = [];
while ($row = $labsResult->fetch_assoc()) {
    $labs[] = $row;
}

// Create a function to clean lab number display
function cleanLabNumber($labNumber) {
    // Remove any duplicate numbers (e.g., 517517 to 517)
    $labNumber = preg_replace('/(\d+)\1+/', '$1', $labNumber);
    
    // Remove any "Lab " prefix if it exists in the database value
    $labNumber = preg_replace('/^Lab\s+/i', '', $labNumber);
    
    return $labNumber;
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
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
            background-color: #f5f7fb;
            margin: 0;
            padding: 0;
        }
        
        nav {
            background-color: var(--secondary-color);
            padding: 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        
        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
        }
        
        nav li {
            margin: 0;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            padding: 18px 20px;
            display: block;
            font-weight: 500;
            transition: var(--transition);
            font-size: 15px;
        }
        
        nav a:hover, nav a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        nav a i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        .logout-container {
            margin-left: auto;
        }
        
        .logout-container a {
            background-color: var(--danger-color);
        }
        
        .logout-container a:hover {
            background-color: #c0392b;
        }
        
        .container {
            max-width: 1320px;
            margin: 80px auto 30px;
            padding: 20px;
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
        
        /* Footer */
        footer {
            text-align: center;
            padding: 20px;
            background-color: var(--secondary-color);
            color: white;
            margin-top: 30px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 991px) {
            .container {
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .pc-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
            
            .pc-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
            }
            
            .lab-info {
                align-items: center;
                width: 100%;
                margin-top: 10px;
            }
            
            .grid-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .grid-controls .search-box {
                max-width: none;
                margin-top: 10px;
            }
            
            nav ul {
                flex-direction: column;
            }
            
            nav .logout-container {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="computer_control.php" class="active"><i class="fas fa-desktop"></i> Computer Control</a></li>
            <li><a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a></li>
            <li><a href="todays_sit_in_records.php"><i class="fas fa-clipboard-list"></i> Today's Records</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
        </ul>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="container">
        <?php echo $message; ?>
        
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
                                        Lab <?php echo htmlspecialchars(cleanLabNumber($lab['lab_number'])); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                
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
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="pc-actions">
                        <div class="action-buttons">
                            <button type="button" class="btn btn-occupied" id="markAllOccupiedBtn">
                                <i class="fas fa-lock"></i> Mark All as Occupied
                            </button>
                            <button type="button" class="btn btn-vacant" id="markAllVacantBtn">
                                <i class="fas fa-unlock"></i> Mark All as Vacant
                            </button>
                        </div>
                        
                        <div class="lab-info">
                            <p class="lab-number">Lab <?php echo htmlspecialchars(cleanLabNumber($selected_lab['lab_number'])); ?></p>
                            <p>Total PCs: <?php echo $selected_lab['pc_count'] ?: 50; ?></p>
                        </div>
                    </div>
                    
                    <!-- PC Grid Container -->
                    <div class="pc-grid-container">
                        <!-- Grid Controls -->
                        <div class="grid-controls">
                            <div class="form-group">
                                <label for="grid-size">PC Size:</label>
                                <select id="grid-size" class="form-control" onchange="changeGridSize(this.value)">
                                    <option value="small">Small</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="large">Large</option>
                                    <option value="xl">Extra Large</option>
                                </select>
                            </div>
                            
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="pc-search" class="form-control" placeholder="Search PC number...">
                            </div>
                        </div>
                        
                        <!-- PC Grid -->
                        <div class="pc-grid size-medium">
                            <?php
                            $total_pcs = $selected_lab['pc_count'] ?: 50;
                            for ($i = 1; $i <= $total_pcs; $i++):
                                // Determine PC status
                                $status = 'vacant'; // Default status
                                if (in_array($i, $reserved_pcs)) {
                                    $status = 'reserved';
                                } elseif (isset($pc_status[$i]) && $pc_status[$i] == 'occupied') {
                                    $status = 'occupied';
                                }
                                
                                $status_text = ucfirst($status);
                                $icon = $status == 'vacant' ? 'unlock' : ($status == 'occupied' ? 'lock' : 'user-clock');
                            ?>
                            <div class="pc-item" data-pc-number="<?php echo $i; ?>">
                                <div class="pc-header">PC <?php echo $i; ?></div>
                                <?php if ($status != 'reserved'): ?>
                                <button type="button" class="pc-status <?php echo $status; ?>" 
                                        data-lab-id="<?php echo $selected_lab_id; ?>" 
                                        data-pc-number="<?php echo $i; ?>" 
                                        data-status="<?php echo $status; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                    <?php echo $status_text; ?>
                                </button>
                                <?php else: ?>
                                <div class="pc-status <?php echo $status; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                    <?php echo $status_text; ?>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide messages after 5 seconds
            setTimeout(function() {
                var messages = document.querySelectorAll('.success-message, .error-message');
                messages.forEach(function(message) {
                    message.style.display = 'none';
                });
            }, 5000);
            
            // Set up PC status toggle buttons
            const toggleButtons = document.querySelectorAll('.pc-status[data-status]');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const labId = this.dataset.labId;
                    const pcNumber = this.dataset.pcNumber;
                    const status = this.dataset.status;
                    
                    togglePcStatus(labId, pcNumber, status);
                });
            });
            
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
        });
        
        // Function to toggle PC status via AJAX
        function togglePcStatus(labId, pcNumber, status) {
            showLoading();
            
            // Create form data
            const formData = new FormData();
            formData.append('toggle_pc_status', '1');
            formData.append('lab_id', labId);
            formData.append('pc_number', pcNumber);
            formData.append('status', status);
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                hideLoading();
                
                // Update PC button status
                const button = document.querySelector(`.pc-status[data-pc-number="${pcNumber}"]`);
                if (button) {
                    const newStatus = status === 'vacant' ? 'occupied' : 'vacant';
                    const newIcon = newStatus === 'vacant' ? 'unlock' : 'lock';
                    const newText = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    
                    button.dataset.status = newStatus;
                    button.className = `pc-status ${newStatus}`;
                    button.innerHTML = `<i class="fas fa-${newIcon}"></i>${newText}`;
                    
                    // Show success message
                    const container = document.querySelector('.container');
                    const successMessage = document.createElement('div');
                    successMessage.className = 'success-message';
                    successMessage.textContent = `PC ${pcNumber} status updated successfully.`;
                    
                    // Insert at the top
                    container.insertBefore(successMessage, container.firstChild);
                    
                    // Auto-hide after 5 seconds
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 5000);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                
                // Show error message
                const container = document.querySelector('.container');
                const errorMessage = document.createElement('div');
                errorMessage.className = 'error-message';
                errorMessage.textContent = 'Error updating PC status. Please try again.';
                
                // Insert at the top
                container.insertBefore(errorMessage, container.firstChild);
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
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
        
        // Function to change the grid size
        function changeGridSize(size) {
            const pcGrid = document.querySelector('.pc-grid');
            
            if (pcGrid) {
                // Remove all size classes
                pcGrid.classList.remove('size-small', 'size-medium', 'size-large', 'size-xl');
                
                // Add the selected size class
                pcGrid.classList.add(`size-${size}`);
            }
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
