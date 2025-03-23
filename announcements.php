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

// Get announcements
$sql = "SELECT * FROM announcements ORDER BY date DESC";
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
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        /* Navbar */
        nav {
            background: #2C3E50;
            width: 100%;
            padding: 15px 0;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        nav ul {
            list-style: none;
            padding: 0;
            margin: 0 0 0 20px;
            display: flex;
        }

        nav ul li {
            margin: 0 15px;
        }

        nav ul li a {
            text-decoration: none;
            color: white;
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        nav ul li a:hover {
            background: #1ABC9C;
            transform: translateY(-2px);
        }

        nav ul li a.active {
            background: #1ABC9C;
        }

        .logout-container {
            margin-right: 20px;
        }

        .logout-container a {
            text-decoration: none;
            color: white;
            font-size: 16px;
            padding: 10px 15px;
            background: #e74c3c;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .logout-container a:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        /* Page Layout */
        .container {
            display: flex;
            flex-direction: column;
            padding: 120px 30px 30px;
            max-width: 1200px;
            margin: 0 auto;
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

        .delete-btn {
            position: absolute;
            top: 20px;
            right: 0;
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

        /* Responsive Design */
        @media (max-width: 768px) {
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
            
            .container {
                padding-top: 200px; /* Increased for mobile to account for wrapped nav */
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav>
    <ul>
        <?php if ($is_admin): ?>
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="manage_sit_in_requests.php">Manage Sit-in Requests</a></li>
            <li><a href="approved_sit_in_sessions.php">Sit in Records</a></li>
            <li><a href="active_sitin.php">Active Sit-ins</a></li>
            <li><a href="add_subject.php">Add Subject</a></li>
            <li><a href="announcements.php" class="active">Announcements</a></li>
        <?php else: ?>
            <li><a href="home.php">Dashboard</a></li>
            <li><a href="reservations.php">Reservations</a></li>
            <li><a href="announcements.php" class="active">Announcements</a></li>
        <?php endif; ?>
    </ul>
    <div class="logout-container">
        <a href="logout.php">Logout</a>
    </div>
</nav>

<!-- Page Content Layout -->
<div class="container">

    <?php if ($is_admin): ?>
    <!-- Admin: Add Announcement Form -->
    <div class="announcement-form">
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
    </div>
    <?php endif; ?>

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
                    
                    <?php if ($is_admin): ?>
                        <a href="announcements.php?delete=<?php echo $announcement['id']; ?>" 
                           class="delete-btn" 
                           onclick="return confirm('Are you sure you want to delete this announcement?');">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>