<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
$is_admin = isset($_SESSION['admin_id']);

// Handle form submission to add a new announcement (only for admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_announcement"])) {
    if ($is_admin) {
        $title = $_POST["title"];
        $content = $_POST["content"];
        $date = date("Y-m-d");

        $sql = "INSERT INTO announcements (title, content, date) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $title, $content, $date);

        if ($stmt->execute()) {
            echo "<script>alert('Announcement added successfully.'); window.location.href = 'announcements.php';</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('You do not have permission to add announcements.'); window.location.href = 'announcements.php';</script>";
    }
}

// Handle announcement deletion (admin only)
if (isset($_GET['delete']) && $is_admin) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Announcement deleted successfully.'); window.location.href = 'announcements.php';</script>";
    } else {
        echo "<script>alert('Error deleting announcement.');</script>";
    }
    $stmt->close();
}

// Handle announcement edit form display
if (isset($_GET['edit']) && $is_admin) {
    $edit_id = $_GET['edit'];
    $sql = "SELECT * FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_announcement = $result->fetch_assoc();
    $stmt->close();
}

// Handle announcement update submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_announcement"]) && $is_admin) {
    $id = $_POST["announcement_id"];
    $title = $_POST["title"];
    $content = $_POST["content"];
    
    $sql = "UPDATE announcements SET title = ?, content = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $title, $content, $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Announcement updated successfully.'); window.location.href = 'announcements.php';</script>";
    } else {
        echo "<script>alert('Error updating announcement: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Get announcements - fixing the ORDER BY to ensure newest first
$sql = "SELECT * FROM announcements ORDER BY id DESC, date DESC";
$result = $conn->query($sql);
$announcements = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #003f5c;
            background-image: url('assets/bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #333;
            line-height: 1.6;
        }

        <?php if (!$is_admin): ?>
        /* Student Navigation Bar */
        nav {
            background-color: #2c3e50;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            transition: background-color 0.3s;
        }

        nav ul li a:hover, 
        nav ul li a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .logout-container a {
            background-color: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .logout-container a:hover {
            background-color: #c0392b;
        }

        .announcements-container {
            max-width: 800px;
            margin: 50px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-left: 5px solid #e67e22;
        }
        
        .announcements-header {
            background-color: #f9f9f9;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .announcements-header h1 {
            color: #e67e22;
            font-size: 24px;
            margin: 0;
        }
        
        .announcements-header h1 i {
            margin-right: 10px;
        }
        
        .announcements-list {
            padding: 0;
        }
        
        .announcement-item {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .announcement-item:last-child {
            border-bottom: none;
        }
        
        .announcement-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .announcement-date {
            font-size: 14px;
            color: #888;
            margin-bottom: 15px;
            display: block;
        }
        
        .announcement-content {
            color: #555;
            line-height: 1.5;
        }
        <?php endif; ?>

        <?php if ($is_admin): ?>
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
            text-align: center;
            flex: 1;
            padding-bottom: 20px;
        }

        /* Add Announcement Form */
        .announcement-form {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-top: 5px solid #3498db;
        }

        .announcement-form h2 {
            color: #3498db;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .btn-submit {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-left: 10px;
            display: inline-block;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }

        /* Announcements Section */
        .announcements-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-top: 5px solid #e67e22;
        }

        .announcements-section h2 {
            color: #e67e22;
            margin-bottom: 20px;
            font-size: 22px;
        }

        .announcement {
            border-bottom: 1px solid #eee;
            padding: 20px 0;
            position: relative;
        }

        .announcement:last-child {
            border-bottom: none;
        }

        .announcement h3 {
            margin: 0 0 10px;
            color: #333;
            font-size: 18px;
        }

        .announcement-date {
            font-size: 14px;
            color: #888;
            margin-bottom: 10px;
            display: block;
        }

        .announcement-content {
            color: #555;
            line-height: 1.6;
        }

        .admin-actions {
            position: absolute;
            top: 20px;
            right: 0;
            display: flex;
        }

        .edit-btn {
            color: #3498db;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
            margin-right: 15px;
        }

        .edit-btn:hover {
            color: #2980b9;
            transform: scale(1.1);
        }

        .delete-btn {
            color: #e74c3c;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }

        .delete-btn:hover {
            color: #c0392b;
            transform: scale(1.1);
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            width: 100%;
        }
        <?php endif; ?>

        /* Responsive adjustments */
        @media (max-width: 992px) {
            <?php if ($is_admin): ?>
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
            <?php endif; ?>
        }
        
        @media (max-width: 768px) {
            <?php if (!$is_admin): ?>
            nav {
                flex-direction: column;
                padding: 10px 0;
            }
            
            nav ul {
                margin: 10px 0;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav ul li {
                margin: 5px;
            }
            
            nav ul li a {
                font-size: 14px;
                padding: 8px 10px;
            }
            
            .logout-container {
                margin: 10px 0;
            }
            <?php endif; ?>
            
            <?php if ($is_admin): ?>
            body.sidebar-active .main-content {
                margin-left: 0;
                width: 100%;
            }
            <?php endif; ?>
        }
    </style>
</head>
<body>

<?php if ($is_admin): ?>
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
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a>
            <a href="todays_sit_in_records.php"><i class="fas fa-calendar-day"></i> Today's Records</a>
            <a href="approved_sit_in_sessions.php"><i class="fas fa-history"></i> Sit in Records</a>
            <a href="active_sitin.php"><i class="fas fa-user-clock"></i> Active Sit-ins</a>
            <a href="add_subject.php"><i class="fas fa-book"></i> Add Subject</a>
            <a href="announcements.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a>
        </div>
        <div class="logout-container">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
<?php else: ?>
    <!-- Student Navigation Bar -->
    <nav>
        <ul>
            <li><a href="home.php">Dashboard</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="student_sit_in_records.php">Sit-in Records</a></li>
            <li><a href="redeem_points.php">Redeem Points</a></li>
            <li><a href="announcements.php" class="active">Announcements</a></li>
        </ul>
        <div class="logout-container">
            <a href="logout.php">Logout</a>
        </div>
    </nav>
<?php endif; ?>

<?php if ($is_admin): ?>
<!-- Admin Content -->
<div class="main-content">
    <div class="content">
        <!-- Admin: Add Announcement Form -->
        <div class="announcement-form">
            <?php if (isset($edit_announcement)): ?>
                <h2><i class="fas fa-edit"></i> Edit Announcement</h2>
                <form method="POST" action="announcements.php">
                    <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement['id']; ?>">
                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_announcement['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content:</label>
                        <textarea id="content" name="content" required><?php echo htmlspecialchars($edit_announcement['content']); ?></textarea>
                    </div>
                    <button type="submit" name="update_announcement" class="btn-submit">Update Announcement</button>
                    <a href="announcements.php" class="btn-cancel">Cancel</a>
                </form>
            <?php else: ?>
                <h2><i class="fas fa-bullhorn"></i> Add New Announcement</h2>
                <form method="POST" action="announcements.php">
                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content:</label>
                        <textarea id="content" name="content" required></textarea>
                    </div>
                    <button type="submit" name="add_announcement" class="btn-submit">Post Announcement</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Announcements Section -->
        <div class="announcements-section">
            <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
            
            <?php if (empty($announcements)): ?>
                <p>No announcements available at this time.</p>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement">
                        <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                        <span class="announcement-date">
                            <i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($announcement['date'])); ?>
                        </span>
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                        </div>
                        
                        <div class="admin-actions">
                            <a href="announcements.php?edit=<?php echo $announcement['id']; ?>" class="edit-btn">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="announcements.php?delete=<?php echo $announcement['id']; ?>" 
                            class="delete-btn" 
                            onclick="return confirm('Are you sure you want to delete this announcement?');">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
        </footer>
    </div>
</div>
<?php else: ?>
<!-- Student Content -->
<div class="announcements-container">
    <div class="announcements-header">
        <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
    </div>
    <div class="announcements-list">
        <?php if (empty($announcements)): ?>
            <div class="announcement-item">
                <p>No announcements available at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-item">
                    <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                    <span class="announcement-date">
                        <i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($announcement['date'])); ?>
                    </span>
                    <div class="announcement-content">
                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    <?php if ($is_admin): ?>
    // Sidebar Toggle Functionality
    document.getElementById('sidebarToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.body.classList.toggle('sidebar-active');
    });
    <?php endif; ?>
</script>
</body>
</html>