<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add points column to users table if it doesn't exist
$sql = "SHOW COLUMNS FROM users LIKE 'points'";
$result = $conn->query($sql);
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE users ADD COLUMN points INT DEFAULT 0";
    if ($conn->query($sql) === TRUE) {
        echo "Points column added to users table.<br>";
    } else {
        echo "Error adding points column: " . $conn->error . "<br>";
    }
} else {
    echo "Points column already exists in users table.<br>";
}

// Create session_points table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS session_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    session_id INT NOT NULL,
    points INT NOT NULL,
    awarded_at DATETIME NOT NULL,
    UNIQUE KEY unique_session_student (session_id, student_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table session_points created or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create bonus_logs table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS bonus_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    points_used INT NOT NULL,
    sessions_added INT NOT NULL,
    awarded_at DATETIME NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table bonus_logs created or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

echo "Points system tables setup complete.";

$conn->close();
?> 