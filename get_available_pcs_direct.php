<?php
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "my_database";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Get subject ID from request
$subject_id = isset($_POST['subjectId']) ? (int)$_POST['subjectId'] : 0;

if ($subject_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid subject ID']);
    exit;
}

// Get the total PC count for this lab from subjects table
$stmt = $conn->prepare("SELECT lab_number, pc_count FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Subject not found']);
    exit;
}

$subject = $result->fetch_assoc();
$lab_number = $subject['lab_number'];
$total_pcs = $subject['pc_count'] ?: 50; // Default to 50 if not set

// Get PCs that are already reserved by students
$stmt = $conn->prepare("
    SELECT pc_number 
    FROM sit_in_requests 
    WHERE subject_id = ? 
    AND pc_number IS NOT NULL 
    AND status IN ('pending', 'approved')
    AND (end_time IS NULL OR is_active = 1)
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$reserved_pcs = [];
while ($row = $result->fetch_assoc()) {
    $reserved_pcs[] = (int)$row['pc_number']; // Cast to integer for consistency
}

// Specifically get PCs that are marked as maintenance
$stmt = $conn->prepare("
    SELECT pc_number 
    FROM pc_status 
    WHERE lab_id = ? 
    AND status = 'maintenance'
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$maintenance_pcs = [];
while ($row = $result->fetch_assoc()) {
    $maintenance_pcs[] = (int)$row['pc_number']; // Cast to integer for consistency
}

// Get PCs that are marked as occupied (but not maintenance)
$stmt = $conn->prepare("
    SELECT pc_number 
    FROM pc_status 
    WHERE lab_id = ? 
    AND status = 'occupied'
");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

$occupied_pcs = [];
while ($row = $result->fetch_assoc()) {
    $occupied_pcs[] = (int)$row['pc_number']; // Cast to integer for consistency
}

// Combine reserved, occupied, and maintenance PCs
$unavailable_pcs = array_unique(array_merge($reserved_pcs, $occupied_pcs, $maintenance_pcs));

// Create an array of available PCs
$available_pcs = [];
for ($i = 1; $i <= $total_pcs; $i++) {
    if (!in_array($i, $unavailable_pcs)) {
        $available_pcs[] = $i;
    }
}

// Return data with separate status arrays for clear identification
echo json_encode([
    'success' => true,
    'lab_number' => $lab_number,
    'total_pcs' => $total_pcs,
    'available_pcs' => $available_pcs,
    'occupied_pcs' => $occupied_pcs,
    'reserved_pcs' => $reserved_pcs,
    'maintenance_pcs' => $maintenance_pcs,
    'unavailable_pcs' => $unavailable_pcs
]);

$stmt->close();
$conn->close();
?> 
