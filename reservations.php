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

// Get logged-in user's info
$username = $_SESSION['username'];

$sql = "SELECT idno, firstname, lastname, username, email, course, photo FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get the total approved sessions for the student
$sql = "SELECT COUNT(*) FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['idno']);
$stmt->execute();
$stmt->bind_result($approved_sessions);
$stmt->fetch();
$stmt->close();

// Determine the maximum sessions based on the course
$max_sessions = ($user['course'] == 'BSIT' || $user['course'] == 'BSCS') ? 30 : 15;
$remaining_sessions = $max_sessions - $approved_sessions;

// Get available subjects
$sql = "SELECT * FROM subjects";
$result = $conn->query($sql);
$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$conn->close();

// Default profile photo
$profile_photo = !empty($user['photo']) ? $user['photo'] : "uploads/default.png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations</title>
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
            flex-direction: row;
            align-items: flex-start;
            padding-top: 80px;
            width: 90%;
            max-width: 1200px;
            margin: auto;
            gap: 20px;
        }

        /* Profile Section */
        .profile-container {
            width: 280px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: sticky;
            top: 100px;
        }

        .profile-container img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid #1ABC9C;
            margin-bottom: 10px;
        }

        .profile-container h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .profile-container p {
            margin: 5px 0;
            color: #666;
        }

        .profile-container a {
            display: inline-block;
            text-decoration: none;
            background: #2C3E50;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            margin-top: 10px;
            transition: 0.3s;
        }

        .profile-container a:hover {
            background: #1ABC9C;
        }

        /* Reservations */
        .reservations-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            flex-grow: 1;
        }

        .reservations-section h2 {
            color: #2980B9;
        }

        .reservations-section table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-section th, .reservations-section td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            color: #333; /* Ensure text color is dark */
        }

        .reservations-section th {
            background-color: #2980B9;
            color: white;
        }

        .reservations-section td a {
            text-decoration: none;
            color: #2980B9;
            font-weight: bold;
        }

        .reservations-section td a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<!-- ✅ Navigation Bar -->
<nav>
    <ul>
        <li><a href="home.php">Dashboard</a></li> <!-- Updated link -->
        <li><a href="reservations.php">Reservations</a></li>
        <li><a href="announcements.php">Announcements</a></li>
    </ul>
    <div class="logout-container">
        <a href="logout.php">Logout</a>
    </div>
</nav>

<!-- ✅ Page Content Layout -->
<div class="container">

    <!-- ✅ User Profile Section -->
    <div class="profile-container">
        <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Picture">
        <h3><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h3>
        <p>Email: <?php echo htmlspecialchars($user['email']); ?></p>
        <p>Remaining Sessions: <?php echo $remaining_sessions; ?></p>
        <a href="edit_profile.php">Edit Profile</a>
    </div>

    <!-- ✅ Reservations -->
    <div class="reservations-section">
        <h2>📅 Available Subjects for Reservation</h2>
        <table>
            <tr>
                <th>Subject Name</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Sessions</th>
                <th>Action</th>
            </tr>
            <?php foreach ($subjects as $subject) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['date']); ?></td>
                    <td><?php echo htmlspecialchars($subject['start_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['end_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['sessions']); ?></td>
                    <td><a href="reserve_subject.php?subject_id=<?php echo $subject['id']; ?>">Reserve</a></td>
                </tr>
            <?php } ?>
        </table>
    </div>

</div>

</body>
</html>