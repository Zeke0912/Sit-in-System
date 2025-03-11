<?php
// Start the session only if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // Start session only once
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login"])) {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Database Connection
    $servername = "localhost";
    $dbusername = "root";
    $dbpassword = "";
    $dbname = "my_database";

    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if user exists
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username); // Binding the username safely
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Verify password using password_verify (bcrypt)
        if (password_verify($password, $row['password_hash'])) {  
            $_SESSION['user_id'] = $row['idno']; // Store user ID in session
            $_SESSION['username'] = $row['username']; 
            $_SESSION['role'] = $row['role']; // Store user role

            // Redirect based on user role (admin or student)
            if ($row['role'] === 'admin') {
                $_SESSION['admin_id'] = $row['idno'];
                $_SESSION['admin_name'] = $row['username'];
                header("Location: admin_dashboard.php"); // Admin dashboard
                exit(); // Ensure no further code is executed
            } else {
                header("Location: home.php"); // Student dashboard
                exit(); // Ensure no further code is executed
            }
        } else {
            $error_message = "❌ Incorrect username or password!";
        }
    } else {
        $error_message = "❌ No user found with that username!";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-in Monitoring System</title>
</head>
<body class="login-page">
    <div class="form-container">
        <h1>CCS Sit-in Monitoring System</h1>
        <img src="ccs.png" alt="CCS" style="width: 150px;"> 

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" placeholder="Username" name="username" required><br>
            <input type="password" placeholder="Password" name="password" required><br>
            <button type="submit" name="login">Login</button><br><br>
        </form>

        <a href="registration.php" class="btn-register">Register</a>
        
        <?php if (isset($error_message)) { 
            echo "<p style='color: red;'>$error_message</p>"; 
        } ?>
    </div>
</body>
</html>
