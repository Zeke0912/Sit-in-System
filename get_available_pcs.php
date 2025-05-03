<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Get subject ID from request
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($subject_id <= 0) {
    echo json_encode(['error' => 'Invalid subject ID']);
    exit;
}

// Get the total PC count for this lab from subjects table
$stmt = $conn->prepare("SELECT lab_number, pc_count FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Subject not found']);
    exit;
}

$subject = $result->fetch_assoc();
$lab_number = $subject['lab_number'];
$total_pcs = $subject['pc_count'];

// Get PCs that are already in use or reserved for this lab
$stmt = $conn->prepare("
    SELECT pc_number 
    FROM sit_in_requests 
    WHERE subject_id = ? 
    AND pc_number IS NOT NULL 
    AND status IN ('approved', 'pending')
    AND (end_time IS NULL OR is_active = 1)
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$occupied_pcs = [];
while ($row = $result->fetch_assoc()) {
    $occupied_pcs[] = $row['pc_number'];
}

// Create an array of available PCs
$available_pcs = [];
for ($i = 1; $i <= $total_pcs; $i++) {
    if (!in_array($i, $occupied_pcs)) {
        $available_pcs[] = $i;
    }
}

// Return the list of available PCs
echo json_encode([
    'lab_number' => $lab_number,
    'available_pcs' => $available_pcs,
    'total_pcs' => $total_pcs,
    'occupied_pcs' => $occupied_pcs
]);

$stmt->close();
$conn->close();
?> 