<?php
session_start();
include 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// Handle adding subjects
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subject'])) {
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $available_time = $_POST['available_time'];

    $sql = "INSERT INTO subjects (subject_code, subject_name, available_time) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $subject_code, $subject_name, $available_time);

    if ($stmt->execute()) {
        $message = "✅ Subject added successfully!";
    } else {
        $message = "❌ Error adding subject.";
    }
}

// Handle sit-in approvals and rejections
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $reservation_id = $_POST['reservation_id'];
    $student_id = $_POST['student_id'];
    $hours_requested = $_POST['hours_requested'];
    $action = $_POST['action'];

    if ($action == "approve") {
        // Check remaining hours
        $check_hours_sql = "SELECT remaining_hours FROM users WHERE idno = ?";
        $stmt = $conn->prepare($check_hours_sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();

        if ($student['remaining_hours'] >= $hours_requested) {
            // Deduct hours and approve request
            $update_hours_sql = "UPDATE users SET remaining_hours = remaining_hours - ? WHERE idno = ?";
            $stmt = $conn->prepare($update_hours_sql);
            $stmt->bind_param("is", $hours_requested, $student_id);
            $stmt->execute();

            $approve_sql = "UPDATE sit_in_reservations SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($approve_sql);
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();

            $message = "✅ Sit-in approved successfully!";
        } else {
            $message = "❌ Student does not have enough hours left!";
        }
    } elseif ($action == "reject") {
        $reject_sql = "UPDATE sit_in_reservations SET status = 'rejected' WHERE id = ?";
        $stmt = $conn->prepare($reject_sql);
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();

        $message = "✅ Sit-in request rejected.";
    }
}

// Fetch subjects
$subjects_result = $conn->query("SELECT * FROM subjects");

// Fetch sit-in requests
$sit_in_result = $conn->query("SELECT sit_in_reservations.*, users.firstname, users.lastname, subjects.subject_name 
    FROM sit_in_reservations
    JOIN users ON sit_in_reservations.student_id = users.idno
    JOIN subjects ON sit_in_reservations.subject_id = subjects.id
    WHERE sit_in_reservations.status = 'pending'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Admin Panel</h2>

    <?php if ($message): ?>
        <p style="color: <?= strpos($message, '✅') !== false ? 'green' : 'red' ?>;">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <h3>Add Subject</h3>
    <form method="post">
        <label>Subject Code:</label>
        <input type="text" name="subject_code" required>
        <label>Subject Name:</label>
        <input type="text" name="subject_name" required>
        <label>Available Time:</label>
        <input type="text" name="available_time" placeholder="10:00 AM - 12:00 PM" required>
        <button type="submit" name="add_subject">Add Subject</button>
    </form>

    <h3>Pending Sit-in Requests</h3>
    <table border="1">
        <tr>
            <th>Student</th>
            <th>Subject</th>
            <th>Hours Requested</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $sit_in_result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['firstname'] . " " . $row['lastname']) ?></td>
            <td><?= htmlspecialchars($row['subject_name']) ?></td>
            <td><?= htmlspecialchars($row['hours_requested']) ?></td>
            <td>
                <form method="post">
                    <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                    <input type="hidden" name="student_id" value="<?= $row['student_id'] ?>">
                    <input type="hidden" name="hours_requested" value="<?= $row['hours_requested'] ?>">
                    <button type="submit" name="action" value="approve">Approve</button>
                    <button type="submit" name="action" value="reject">Reject</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
