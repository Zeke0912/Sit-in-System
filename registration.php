<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["create"])) {
    $idno = $_POST["idno"];
    $lastname = $_POST["lastname"];
    $firstname = $_POST["firstname"];
    $middlename = $_POST["middlename"];
    $course = $_POST["course"];
    $year = $_POST["year"];
    $email = $_POST["email"];
    $username = $_POST["username"];
    $password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT);

    // Handle Image Upload
    $target_dir = "uploads/";
    $target_file = $target_dir . time() . "_" . basename($_FILES["profile_photo"]["name"]);

    if (!empty($_FILES["profile_photo"]["name"])) {
        $file_mime_type = mime_content_type($_FILES["profile_photo"]["tmp_name"]);
        $valid_mime_types = ["image/jpeg", "image/png", "image/gif"];

        if (!in_array($file_mime_type, $valid_mime_types)) {
            echo "<script>alert('Invalid file type. Only JPG, JPEG, PNG, and GIF allowed.');</script>";
            exit();
        }

        // Move the uploaded file
        if (!move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            echo "<script>alert('Error uploading file.');</script>";
            exit();
        }
    } else {
        $target_file = "uploads/default.png"; // Default profile picture
    }

    // Database Connection
    $servername = "localhost";
    $dbusername = "root";
    $dbpassword = "";
    $dbname = "my_database";

    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Insert user data into database
    $sql = "INSERT INTO users (idno, lastname, firstname, middlename, course, year, email, username, password_hash, photo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssss", $idno, $lastname, $firstname, $middlename, $course, $year, $email, $username, $password_hash, $target_file);

    if ($stmt->execute()) {
        echo "<script>alert('User Created Successfully'); window.location.href = 'index.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
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
    <title>Registration</title>
</head>
<body>
    <div class="form-container">
        <a class="back-btn" href="index.php">Go Back</a>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <h1>Registration</h1>
            <label>IDNO</label>
            <input type="text" name="idno" required><br>

            <label>Lastname</label>
            <input type="text" name="lastname" required><br>

            <label>Firstname</label>
            <input type="text" name="firstname" required><br>

            <label>Middlename</label>
            <input type="text" name="middlename"><br>

            <label>Course</label>
            <select name="course" required style="padding: 12px 15px;">
                <option value="" disabled selected>-- Select Course --</option>
                <option value="BSIT">BSIT</option>
                <option value="BSCS">BSCS</option>
                <option value="BSIS">BSIS</option>
                <option value="BSECE">BSECE</option>
            </select><br>

            <label>Year Level</label>
            <select name="year" required style="padding: 12px 15px;">
                <option value="" disabled selected>-- Select Year Level --</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select><br>

            <label>Email Address</label>
            <input type="email" name="email" required><br>

            <label>Username</label>
            <input type="text" name="username" required><br>

            <label>Password</label>
            <input type="password" name="password" required><br>

            <label>Profile Photo</label>
            <input type="file" name="profile_photo" accept="image/*"><br>

            <button class="btn" type="submit" name="create">Sign Up</button><br><br>
        </form>
    </div>

    <?php
    if (isset($error_message)) {
        echo "<p style='color: red;'>$error_message</p>";
    }
    ?>
</body>
</html>