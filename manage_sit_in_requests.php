<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";  // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php"); // Redirect if not logged in
    exit();
}

// Step 1: Fetch all pending sit-in requests, join with subjects for date and time
$sql = "
    SELECT sit_in_requests.*, subjects.subject_name, subjects.date, subjects.start_time, subjects.end_time
    FROM sit_in_requests
    JOIN subjects ON sit_in_requests.subject_id = subjects.id
    WHERE sit_in_requests.status = 'pending'";

$result = $conn->query($sql);

// Check if the query was successful
if ($result === false) {
    die("Error: " . $conn->error); // Display error if the query fails
}

// Step 2: Process approval/rejection actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        // Update the status to 'approved'
        $update_sql = "UPDATE sit_in_requests SET status = 'approved' WHERE id = ?";
    } elseif ($action == 'reject') {
        // Update the status to 'rejected'
        $update_sql = "UPDATE sit_in_requests SET status = 'rejected' WHERE id = ?";
    }

    // Prepare and execute the update query
    if ($stmt = $conn->prepare($update_sql)) {
        $stmt->bind_param("i", $request_id);  // 'i' for integer type
        $stmt->execute();
        $stmt->close();
    }
    
    // Redirect back to this page after performing the action
    header("Location: manage_sit_in_requests.php");  // Refresh the page to show updated status
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sit-in Requests</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            text-align: left;
        }

        /* Top Navbar */
        .navbar {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;  /* Ensure navbar stays on top */
            background-color: #2c3e50;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 16px;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .navbar a:hover {
            background-color: #1abc9c;  /* New hover color */
            color: white;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
        }

        .navbar .logout-container a {
            color: white;
            background-color: #e74c3c;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }

        .navbar .logout-container a:hover {
            background-color: #c0392b;
        }

        /* Title Container for Manage Sit-in Requests */
        .title-container {
            width: 80%;
            margin: 0px auto;
            padding: 15px;
            background-color: white;  /* White background */
            color: black; /* Dark blue text color */
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow to make the container stand out */
            font-size: 24px;
        }

        .content {
            margin-top: 100px; /* Account for the height of the navbar */
            padding: 30px;
            margin: 30px auto;
            width: 85%;
            text-align: center;
        }

        .container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table th, .requests-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            color: #333; /* Set text color to a darker color for readability */
        }

        .requests-table th {
            background-color: #2980B9;
            color: white;
        }

        .requests-table td button {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .approve-btn {
            background-color: #2C3E50;
            color: white;
        }

        .reject-btn {
            background-color: #e74c3c;
            color: white;
        }

        .approve-btn:hover {
            background-color: #2980B9;
        }

        .reject-btn:hover {
            background-color: #c0392b;
        }

        .error-message {
            color: red;
            margin-top: 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_sit_in_requests.php">Manage Sit-in Requests</a>
            <a href="approved_sit_in_sessions.php">Approved Sit-in Sessions</a>
            <a href="add_subject.php">Add Subject</a>
            <a href="add_announcement.php">Add Announcement</a>
        </div>
        <div class="logout-container">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <!-- Title container -->
    <div class="title-container">
        Manage Sit-in Requests
    </div>

    <!-- Main content container for sit-in request table -->
    <div class="container">
        <?php if ($result && $result->num_rows > 0): ?>
            <table class="requests-table">
                <tr>
                    <th>Student ID</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo htmlspecialchars($row['start_time']); ?></td>
                        <td><?php echo htmlspecialchars($row['end_time']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td>
                            <!-- Buttons for Approve and Reject -->
                            <form method="POST" action="manage_sit_in_requests.php" style="display:inline-block;">
    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
    <button type="submit" name="action" value="approve">Approve</button>
</form>

<form method="POST" action="manage_sit_in_requests.php" style="display:inline-block;">
    <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
    <button type="submit" name="action" value="reject">Reject</button>
</form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p class="error-message">No pending sit-in requests at the moment.</p>
        <?php endif; ?>
    </div>

</body>
</html>