<?php
// Step 1: Check if the user is logged in and is an admin
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");  // Redirect to login page if not logged in
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";  // Replace with your database name

// Step 2: Create connection to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// Step 3: Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_name = $_POST['subject_name'];
    $lab_number = $_POST['lab_number'];
    $date = $_POST['date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $sessions = $_POST['sessions'];

    // Step 4: Insert data into the subjects table, with default status as 'available'
    $sql = "INSERT INTO subjects (subject_name, lab_number, date, start_time, end_time, sessions, status)
            VALUES ('$subject_name', '$lab_number', '$date', '$start_time', '$end_time', '$sessions', 'available')";
    
    if ($conn->query($sql) === TRUE) {
        echo "New subject added successfully with status 'available'!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Step 5: Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subject</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="container">
        <h2>Add a New Subject</h2>

        <form method="POST" action="add_subject.php">
            <label for="subject_name">Subject Name</label>
            <input type="text" id="subject_name" name="subject_name" required>

            <label for="lab_number">Lab Number</label>
            <input type="text" id="lab_number" name="lab_number" required>

            <label for="date">Date</label>
            <input type="date" id="date" name="date" required>

            <label for="start_time">Start Time</label>
            <input type="time" id="start_time" name="start_time" required>

            <label for="end_time">End Time</label>
            <input type="time" id="end_time" name="end_time" required>

            <label for="sessions">Sessions</label>
            <input type="number" id="sessions" name="sessions" required>

            <button type="submit">Add Subject</button>
        </form>
    </div>

</body>
</html>
