<?php
// Step 1: Connect to the database
$servername = "localhost";
$username = "root";  // Replace with your username
$password = "";      // Replace with your password
$dbname = "my_database";  // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the student ID from the session (assuming it's stored there)
session_start();
$student_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 1; // Default to 1 if not set

// Step 2: Retrieve subjects from the database
$sql = "SELECT s.*, 
               COALESCE(sir.status, 'available') as request_status 
        FROM subjects s
        LEFT JOIN sit_in_requests sir ON s.id = sir.subject_id AND sir.student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Initialize $subjects array
$subjects = [];

if ($result->num_rows > 0) {
    // Fetch all subjects into an array
    while($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

// Close connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations</title>
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            text-align: left;
        }

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
            height: 60px;
        }

        .container {
            position: relative;
            margin-top: 60px;
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .reservations-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            text-align: center;
        }

        .reservations-section h2 {
            color: #2980B9;
            font-size: 24px;
            margin-bottom: 0px;
        }

        .reservations-section table {
            width: 100%;
            border-collapse: collapse;
        }

        .reservations-section th, .reservations-section td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            color: #333;
        }

        .reservations-section th {
            background-color: #2980B9;
            color: white;
        }

        .reserve-btn {
            background-color: #2980B9;
            color: white;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
        }

        .reserve-btn:disabled {
            background-color: #bdc3c7;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
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

<!-- Page Content -->
<div class="container">
    <div class="reservations-section">
        <h2>📅 Available Subjects for Reservation</h2>
        <table>
            <tr>
                <th>Subject Name</th>
                <th>Lab Number</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Sessions</th>
                <th>Status</th> <!-- New Status Column -->
                <th>Action</th>
            </tr>
            <?php foreach ($subjects as $subject) { ?>
                <tr id="subject-<?php echo $subject['id']; ?>">
                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['lab_number']); ?></td>
                    <td><?php echo htmlspecialchars($subject['date']); ?></td>
                    <td><?php echo htmlspecialchars($subject['start_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['end_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['sessions']); ?></td>
                    <td><?php echo htmlspecialchars($subject['request_status']); ?></td> <!-- Display Status -->
                    <td>
                        <?php if ($subject['request_status'] == 'available'): ?>
                            <button class="reserve-btn" data-subject-id="<?php echo $subject['id']; ?>" data-action="reserve">Reserve</button>
                        <?php else: ?>
                            <button class="reserve-btn" data-subject-id="<?php echo $subject['id']; ?>" data-action="reserve" disabled>
                                <?php echo ucfirst($subject['request_status']); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
   $(document).ready(function() {
    $(".reserve-btn").click(function() {
        var subjectId = $(this).data("subject-id");
        var action = $(this).data("action"); // 'approve' or 'reject'
        var button = $(this); // Store the button element

        console.log("Button clicked. Subject ID:", subjectId, "Action:", action);

        // Ensure both subjectId and action are available
        if (!subjectId || !action) {
            alert("Missing subject ID or action.");
            return;
        }

        // Assuming you have a way to get the student ID
        var studentId = <?php echo $student_id; ?>;  // Use the student ID from PHP

        $.ajax({
    url: "reserve_subject.php",  // The PHP script that handles the insertion
    type: "POST",
    data: {
        subject_id: subjectId,
        student_id: studentId,
        feedback: "Looking forward to attending!"  // You can adjust this if needed
    },
    success: function(response) {
        console.log("Server Response: ", response);  // Log the response for debugging
        if (response == "approved") {
            button.text("Approved").attr("disabled", true);
            $("#subject-" + subjectId + " td:nth-child(7)").text("Approved"); // Update status
        } else if (response == "rejected") {
            button.text("Rejected").attr("disabled", true);
            $("#subject-" + subjectId + " td:nth-child(7)").text("Rejected"); // Update status
        } else if (response == "pending") {
            button.text("Pending").attr("disabled", true);
            $("#subject-" + subjectId + " td:nth-child(7)").text("Pending"); // Update status
        } else {
            alert("Error reserving the subject. Please try again.");
        }
    },
    error: function(jqXHR, textStatus, errorThrown) {
        console.error("AJAX Error: ", textStatus, errorThrown);
        alert("There was an error with the request.");
    }
});

    });
});
</script>

</body>
</html>