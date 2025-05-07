<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication first
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get logged-in user's info
$user_id = $_SESSION['user_id'];
$user = [];

try {
    $sql = "SELECT idno, firstname, lastname, username, email, course, photo, year 
            FROM users 
            WHERE idno = ?";
    $stmt = $conn->prepare($sql);
    
    // Store user_id in variable first
    $bind_user_id = $user_id;
    $stmt->bind_param("s", $bind_user_id); // Pass by reference
    
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc() ?? [];
    $stmt->close();
} catch (Exception $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Get approved sessions
$approved_sessions = 0;
$student_id = $user['idno'] ?? '';

try {
    $sql = "SELECT COUNT(*) FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($sql);
    
    // Bind the variable
    $stmt->bind_param("s", $student_id);
    
    $stmt->execute();
    $stmt->bind_result($approved_sessions);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    die("Error fetching sessions: " . $e->getMessage());
}

// Get approved sessions
$approved_sessions = 0;
try {
    $sql = "SELECT COUNT(*) FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($sql);
    $student_id = $user['idno'] ?? ''; 
    $stmt->bind_param("s", $student_id);    
    $stmt->execute();
    $stmt->bind_result($approved_sessions);
    $stmt->fetch();
    $stmt->close();
} catch (Exception $e) {
    die("Error fetching sessions: " . $e->getMessage());
}

// Determine max sessions
$max_sessions = 15; // Default value
if (isset($user['course'])) {
    $max_sessions = (in_array($user['course'], ['BSIT', 'BSCS'])) ? 30 : 15;
}
$remaining_sessions = $max_sessions - $approved_sessions;

$conn->close();

// Default profile photo with null coalescing
$profile_photo = $user['photo'] ?? "uploads/default.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleRules() {
            var content = document.getElementById("rulesContent");
            if (content.style.display === "none") {
                content.style.display = "block";
            } else {
                content.style.display = "none";
            }
        }
    </script>
</head>
<body>

<!-- ✅ Navigation Bar -->
<nav>
    <ul>
        <li><a href="home.php">Dashboard</a></li> <!-- Updated link -->
        <li><a href="reservations.php">Reservations</a></li>
        <li><a href="student_sit_in_records.php">Sit-in Records</a></li>
        <li><a href="lab_schedules.php">View Lab Schedules</a></li>
        <li><a href="resources.php">Resources</a></li> <!-- Added resources link -->
        <li><a href="announcements.php">Announcements</a></li> <!-- Updated link -->
    </ul>
    <div class="logout-container">
        <a href="logout.php">Logout</a>
    </div>
</nav>

<!-- ✅ Page Content Layout -->
<div class="container">

 <!-- ✅ User Profile Section -->
<div class="profile-container">
    <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile Picture">
    <h3><?= htmlspecialchars($user['username'] ?? 'N/A') ?></h3>
    
    <div class="profile-info">
        <div class="info-row">
            <strong>ID Number:</strong>
            <span><?= htmlspecialchars($user['idno'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <strong>First Name:</strong>
            <span><?= htmlspecialchars($user['firstname'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <strong>Last Name:</strong>
            <span><?= htmlspecialchars($user['lastname'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <strong>Email:</strong>
            <span><?= htmlspecialchars($user['email'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <strong>Course:</strong>
            <span><?= htmlspecialchars($user['course'] ?? 'Not specified') ?></span>
        </div>
        <div class="info-row">
            <strong>Year Level:</strong>
            <span><?= htmlspecialchars($user['year'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <strong>Remaining Sessions:</strong>
            <span><?= $remaining_sessions ?></span>
        </div>
    </div>
    
    <div class="edit-profile-container">
        <a href="edit_profile.php">Edit Profile</a>
    </div>
</div>




    <!-- ✅ Main Dashboard Content -->
    <div class="dashboard-content">

        <!-- ✅ Clickable Laboratory Rules and Regulations -->
        <div class="rules-container">
            <div class="rules-header" onclick="toggleRules()">📖 Click to View Laboratory Rules & Regulations</div>
            <div class="rules-content" id="rulesContent">
                <ul>
                    <li>Maintain silence, proper decorum, and discipline inside the laboratory, Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                    <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                    <li>Surfing the Internet is allowed only with the permission of the Instructor. Downloading and installing of software are strictly prohibited.</li>
                    <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohbited.</li>
                    <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                    <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                    <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                    <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                    <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                    <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                    <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
                </ul>
            </div>
        </div>

        <!-- ✅ Announcements -->
        <div class="announcements-section">
            <h2>📢 Announcements</h2>
            <marquee behavior="scroll" direction="left">
                🎉 Welcome to the Sit-in Monitoring System! | 🏆 Top Performers will be announced soon!
            </marquee>
        </div>

    </div>

</div>

</body>
</html>
