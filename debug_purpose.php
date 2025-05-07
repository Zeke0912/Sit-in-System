<?php
include 'connection.php';

// Test the database connection
echo "<h2>Database Connection Status:</h2>";
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "Connected successfully to database: " . $conn->host_info . "<br>";
    echo "Database name: " . ($conn->query("SELECT DATABASE()")->fetch_row()[0] ?? "unknown") . "<br><br>";
}

// Insert purposes directly (for testing)
echo "<h2>Adding test purposes:</h2>";
$test_purposes = [
    'Java', 'PHP', 'ASP.NET', 'C#', 'Python', 'C Programming',
    'Database', 'Digital & Logic Design', 'Embedded Systems & IoT',
    'System Integration & Architecture', 'Computer Application',
    'Project Management', 'IT Trends', 'Technopreneurship', 'Capstone', 'Other'
];

// Get a valid student ID
$student_query = "SELECT idno FROM users LIMIT 1";
$student_result = $conn->query($student_query);

if ($student_result && $student_result->num_rows > 0) {
    $student_id = $student_result->fetch_assoc()['idno'];
    
    // Insert test purposes
    foreach ($test_purposes as $purpose) {
        $insert = $conn->prepare("INSERT INTO sit_in_requests (student_id, lab_number, purpose, status) VALUES (?, '528', ?, 'pending')");
        $insert->bind_param("is", $student_id, $purpose);
        $insert->execute();
        echo "Added: $purpose<br>";
    }
} else {
    echo "Error: No users found in database<br>";
}

// Check existing purposes
echo "<h2>Current Purposes in Database:</h2>";
$query = "SELECT DISTINCT purpose FROM sit_in_requests WHERE purpose IS NOT NULL AND purpose != '' ORDER BY purpose";
$result = $conn->query($query);

if ($result) {
    if ($result->num_rows > 0) {
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['purpose']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "No purposes found in the database.<br>";
    }
} else {
    echo "Error querying purposes: " . $conn->error . "<br>";
}

// Direct HTML link to admin page
echo "<a href='lab_schedules_admin.php' style='display:inline-block; margin-top:20px; padding:10px; background-color:#4361ee; color:white; text-decoration:none; border-radius:5px;'>Go to Lab Schedules Admin</a>";

$conn->close();
?> 
