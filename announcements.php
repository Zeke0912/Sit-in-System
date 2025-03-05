<?php
session_start();
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission to add a new announcement (only for admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_announcement"])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
        $title = $_POST["title"];
        $content = $_POST["content"];
        $date = date("Y-m-d");

        $sql = "INSERT INTO announcements (title, content, date) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $title, $content, $date);

        if ($stmt->execute()) {
            echo "<script>alert('Announcement added successfully.'); window.location.href = 'announcements.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "<script>alert('You do not have permission to add announcements.'); window.location.href = 'announcements.php';</script>";
    }
}

// Get announcements
$sql = "SELECT * FROM announcements ORDER BY date DESC";
$result = $conn->query($sql);
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
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
    <style>
        /* General Styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        /* Navbar */
        nav {
            background: #2C3E50;
            width: 100%;
            padding: 12px 0;
            position: fixed;
            top: 0;
            left: 0;
            text-align: center;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
        }

        nav ul li {
            margin: 0 15px;
        }

        nav ul li a {
            text-decoration: none;
            color: white;
            font-size: 16px;
            padding: 10px 15px;
            transition: 0.3s;
        }

        nav ul li a:hover {
            background: #1ABC9C;
            border-radius: 5px;
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
            transition: 0.3s;
        }

        .logout-container a:hover {
            background: #c0392b;
        }

        /* Page Layout */
        .container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding-top: 80px;
            width: 90%;
            max-width: 1200px;
            margin: auto;
            gap: 20px;
        }

        /* Announcements Section */
        .announcements-section {
            background: #fff5e6;
            border-left: 5px solid #e67e22;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .announcements-section h2 {
            color: #D35400;
        }

        .announcement {
            margin-bottom: 20px;
        }

        .announcement h3 {
            margin: 0;
            color: #333;
        }

        .announcement p {
            margin: 5px 0;
            color: #666;
        }

        .announcement .date {
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>

<!-- ✅ Navigation Bar -->
<nav>
    <ul>
        <li><a href="home.php">Dashboard</a></li>
        <li><a href="reservations.php">Reservations</a></li>
        <li><a href="announcements.php">Announcements</a></li>
    </ul>
    <div class="logout-container">
        <a href="logout.php">Logout</a>
    </div>
</nav>

<!-- ✅ Page Content Layout -->
<div class="container">

    <!-- ✅ Announcements Section -->
    <div class="announcements-section">
        <h2>📢 Announcements</h2>
        <?php foreach ($announcements as $announcement) { ?>
            <div class="announcement">
                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <p class="date"><?php echo htmlspecialchars($announcement['date']); ?></p>
                <p><?php echo htmlspecialchars($announcement['content']); ?></p>
            </div>
        <?php } ?>
    </div>

</div>

</body>
</html>