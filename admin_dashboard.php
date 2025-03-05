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

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit();
}

// Handle Add Subject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_subject"])) {
    $subject_name = $_POST["subject_name"];
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $sessions = $_POST["sessions"];

    $sql = "INSERT INTO subjects (subject_name, date, start_time, end_time, sessions) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $subject_name, $date, $start_time, $end_time, $sessions);

    if ($stmt->execute()) {
        echo "<script>alert('Subject added successfully.'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Handle Edit Subject
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_subject"])) {
    $id = $_POST["id"];
    $subject_name = $_POST["subject_name"];
    $date = $_POST["date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $sessions = $_POST["sessions"];

    $sql = "UPDATE subjects SET subject_name = ?, date = ?, start_time = ?, end_time = ?, sessions = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $subject_name, $date, $start_time, $end_time, $sessions, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Subject updated successfully.'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Handle Delete Subject
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $sql = "DELETE FROM subjects WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo "<script>alert('Subject deleted successfully.'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Handle Approval & Rejection
if (isset($_GET['approve']) || isset($_GET['reject'])) {
    $id = isset($_GET['approve']) ? $_GET['approve'] : $_GET['reject'];
    $status = isset($_GET['approve']) ? "approved" : "rejected";

    // Get the student ID and course for the request
    $sql = "SELECT student_id, course FROM sit_in_requests WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($student_id, $course);
    $stmt->fetch();
    $stmt->close();

    // Check the total approved sessions for the student
    $sql = "SELECT COUNT(*) FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($approved_sessions);
    $stmt->fetch();
    $stmt->close();

    // Determine the maximum sessions based on the course
    $max_sessions = ($course == 'BSIT' || $course == 'BSCS') ? 30 : 15;

    if ($status == 'approved' && $approved_sessions >= $max_sessions) {
        echo "<script>alert('Student has reached the maximum number of sit-in sessions.'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        $sql = "UPDATE sit_in_requests SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            echo "<script>alert('Request has been $status.'); window.location.href = 'admin_dashboard.php';</script>";
        } else {
            echo "Error updating request.";
        }
    }
}

// Handle Add Announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_announcement"])) {
    $title = $_POST["title"];
    $content = $_POST["content"];
    $date = date("Y-m-d");

    $sql = "INSERT INTO announcements (title, content, date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $title, $content, $date);

    if ($stmt->execute()) {
        echo "<script>alert('Announcement added successfully.'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Get announcements
$sql = "SELECT * FROM announcements ORDER BY date DESC";
$result = $conn->query($sql);
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}
?>

<!-- Admin Dashboard -->
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 80%;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #0056b3;
        }
        a {
            color: #0056b3;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        form {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"], input[type="date"], input[type="time"], input[type="number"], textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #0056b3;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #004494;
        }
        .logout-container {
            position: absolute;
            top: 20px;
            right: 20px;
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
    </style>
</head>
<body>
    <div class="logout-container">
        <a href="admin_dashboard.php?logout=true">Logout</a>
    </div>
    <div class="container">
        <h1>Welcome, Admin <?php echo $_SESSION['admin_name']; ?>!</h1>

        <h2>Manage Sit-in Requests</h2>
        <table>
            <tr>
                <th>Request ID</th>
                <th>Student Name</th>
                <th>Course</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Remaining Sessions</th>
                <th>Action</th>
            </tr>

            <?php
            $sql = "SELECT sit_in_requests.*, users.course FROM sit_in_requests JOIN users ON sit_in_requests.student_id = users.idno WHERE sit_in_requests.status = 'pending'";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                // Calculate remaining sessions
                $student_id = $row['student_id'];
                $course = $row['course'];
                $max_sessions = ($course == 'BSIT' || $course == 'BSCS') ? 30 : 15;

                $sql2 = "SELECT COUNT(*) FROM sit_in_requests WHERE student_id = ? AND status = 'approved'";
                $stmt2 = $conn->prepare($sql2);
                $stmt2->bind_param("s", $student_id);
                $stmt2->execute();
                $stmt2->bind_result($approved_sessions);
                $stmt2->fetch();
                $stmt2->close();

                $remaining_sessions = $max_sessions - $approved_sessions;
            ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['student_name']; ?></td>
                    <td><?php echo $row['course']; ?></td>
                    <td><?php echo $row['subject']; ?></td>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo $remaining_sessions; ?></td>
                    <td>
                        <a href="admin_dashboard.php?approve=<?php echo $row['id']; ?>">Approve</a> | 
                        <a href="admin_dashboard.php?reject=<?php echo $row['id']; ?>">Reject</a>
                    </td>
                </tr>
            <?php } ?>
        </table>

        <h2>Approved Sit-in Sessions</h2>
        <table>
            <tr>
                <th>Request ID</th>
                <th>Student Name</th>
                <th>Course</th>
                <th>Subject</th>
                <th>Date</th>
            </tr>

            <?php
            $sql = "SELECT * FROM sit_in_requests WHERE status = 'approved'";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['student_name']; ?></td>
                    <td><?php echo $row['course']; ?></td>
                    <td><?php echo $row['subject']; ?></td>
                    <td><?php echo $row['date']; ?></td>
                </tr>
            <?php } ?>
        </table>

        <h2>Add Subject</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <label for="subject_name">Subject Name:</label>
            <input type="text" id="subject_name" name="subject_name" required><br>

            <label for="date">Date:</label>
            <input type="date" id="date" name="date" required><br>

            <label for="start_time">Start Time:</label>
            <input type="time" id="start_time" name="start_time" required><br>

            <label for="end_time">End Time:</label>
            <input type="time" id="end_time" name="end_time" required><br>

            <label for="sessions">Sessions:</label>
            <input type="number" id="sessions" name="sessions" required><br>

            <button type="submit" name="add_subject">Add Subject</button>
        </form>

        <h2>Manage Subjects</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Subject Name</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Sessions</th>
                <th>Action</th>
            </tr>

            <?php
            $sql = "SELECT * FROM subjects";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['subject_name']; ?></td>
                    <td><?php echo $row['date']; ?></td>
                    <td><?php echo $row['start_time']; ?></td>
                    <td><?php echo $row['end_time']; ?></td>
                    <td><?php echo $row['sessions']; ?></td>
                    <td>
                        <a href="admin_dashboard.php?edit=<?php echo $row['id']; ?>">Edit</a> | 
                        <a href="admin_dashboard.php?delete=<?php echo $row['id']; ?>">Delete</a>
                    </td>
                </tr>
            <?php } ?>
        </table>

        <?php
        if (isset($_GET['edit'])) {
            $id = $_GET['edit'];
            $sql = "SELECT * FROM subjects WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
        ?>
            <h2>Edit Subject</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <label for="subject_name">Subject Name:</label>
                <input type="text" id="subject_name" name="subject_name" value="<?php echo $row['subject_name']; ?>" required><br>

                <label for="date">Date:</label>
                <input type="date" id="date" name="date" value="<?php echo $row['date']; ?>" required><br>

                <label for="start_time">Start Time:</label>
                <input type="time" id="start_time" name="start_time" value="<?php echo $row['start_time']; ?>" required><br>

                <label for="end_time">End Time:</label>
                <input type="time" id="end_time" name="end_time" value="<?php echo $row['end_time']; ?>" required><br>

                <label for="sessions">Sessions:</label>
                <input type="number" id="sessions" name="sessions" value="<?php echo $row['sessions']; ?>" required><br>

                <button type="submit" name="edit_subject">Update Subject</button>
            </form>
        <?php } ?>

        <h2>Add New Announcement</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required>

            <label for="content">Content:</label>
            <textarea id="content" name="content" rows="5" required></textarea>

            <button type="submit" name="add_announcement">Add Announcement</button>
        </form>

        <h2>📢 Announcements</h2>
        <?php foreach ($announcements as $announcement) { ?>
            <div class="announcement">
                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <p class="date"><?php echo htmlspecialchars($announcement['date']); ?></p>
                <p><?php echo htmlspecialchars($announcement['content']); ?></p>
            </div>
        <?php } ?>
    </div>
</body>
</html>