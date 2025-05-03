<?php
include 'connection.php';

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Connection Status:</h2>";
echo "Connected successfully to database.<br><br>";

// Get purposes from sit_in_requests table
echo "<h2>Purposes from sit_in_requests table:</h2>";
$purpose_query = "SELECT DISTINCT purpose FROM sit_in_requests WHERE purpose IS NOT NULL AND purpose != '' ORDER BY purpose";
$purpose_result = $conn->query($purpose_query);

if ($purpose_result) {
    echo "Query executed successfully.<br>";
    echo "Number of rows returned: " . $purpose_result->num_rows . "<br><br>";
    
    if ($purpose_result->num_rows > 0) {
        echo "<strong>Purposes found:</strong><br>";
        while ($row = $purpose_result->fetch_assoc()) {
            echo "- " . htmlspecialchars($row['purpose']) . "<br>";
        }
    } else {
        echo "No purposes found in the database with the current query.<br>";
    }
} else {
    echo "Error executing query: " . $conn->error;
}

// Check table structure
echo "<h2>sit_in_requests Table Structure:</h2>";
$structure_query = "DESCRIBE sit_in_requests";
$structure_result = $conn->query($structure_query);

if ($structure_result && $structure_result->num_rows > 0) {
    echo "<table border='1'>
            <tr>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
            </tr>";
    
    while ($row = $structure_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . ($row['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "Could not get table structure: " . $conn->error;
}

// Check for any rows in the table
echo "<h2>Sample data from sit_in_requests:</h2>";
$sample_query = "SELECT * FROM sit_in_requests LIMIT 5";
$sample_result = $conn->query($sample_query);

if ($sample_result && $sample_result->num_rows > 0) {
    echo "Found " . $sample_result->num_rows . " rows in the table.<br><br>";
    
    // Display the table headers
    echo "<table border='1'><tr>";
    $field_info = $sample_result->fetch_fields();
    foreach ($field_info as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // Reset the pointer to the beginning
    $sample_result->data_seek(0);
    
    // Display the data
    while ($row = $sample_result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . (is_null($value) ? "NULL" : htmlspecialchars($value)) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No rows found in the table or error executing query: " . $conn->error;
}

$conn->close();
?> 