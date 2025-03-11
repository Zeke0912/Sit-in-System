<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";  // Replace with your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['approve'])) {
    $request_id = $_POST['request_id'];
    $feedback = $_POST['feedback'];

    // Update the status to 'reserved' and add feedback
    $sql = "UPDATE sit_in_requests SET status = 'reserved', feedback = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $feedback, $request_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Redirect back to the sit_in_requests.php page after approval
        header("Location: sit_in_requests.php");
        exit();
    } else {
        echo "Error: Request could not be approved.";
    }

    $stmt->close();
}

// Close the connection
$conn
