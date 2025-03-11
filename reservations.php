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

// Step 2: Query the database for available subjects
$sql = "SELECT * FROM subjects";  // Replace 'subjects' with your actual table name
$result = $conn->query($sql);

// Step 3: Check if subjects exist and store them in an array
$subjects = [];
if ($result->num_rows > 0) {
    // Fetch subjects from the result set
    while($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
} else {
    echo "No subjects found.";
}

// Close connection
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

        .reservations-section td a {
            text-decoration: none;
            color: #2980B9;
            font-weight: bold;
        }

        .reservations-section td a:hover {
            text-decoration: underline;
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
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Sessions</th>
                <th>Action</th>
            </tr>
            <?php foreach ($subjects as $subject) { ?>
                <tr id="subject-<?php echo $subject['id']; ?>">
                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['date']); ?></td>
                    <td><?php echo htmlspecialchars($subject['start_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['end_time']); ?></td>
                    <td><?php echo htmlspecialchars($subject['sessions']); ?></td>
                    <td><button class="reserve-btn" data-subject-id="<?php echo $subject['id']; ?>">Reserve</button></td>
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

            $.ajax({
                url: "reserve_subject.php",
                type: "POST",
                data: {
                    subject_id: subjectId
                },
                success: function(response) {
                    if(response == "success") {
                        $("#subject-" + subjectId + " .reserve-btn").text("Pending").attr("disabled", true);
                    } else {
                        alert("Error reserving the subject. Please try again.");
                    }
                },
                error: function() {
                    alert("There was an error with the request.");
                }
            });
        });
    });
</script>

</body>
</html>
