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

    // Set role as 'student' by default (You can later add logic for admin creation if needed)
    $role = 'student'; // Default role for the user is 'student'

    // Database Connection
    $servername = "localhost";
    $dbusername = "root";
    $dbpassword = "";
    $dbname = "my_database";

    $conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Insert user data into database, including role
    $sql = "INSERT INTO users (idno, lastname, firstname, middlename, course, year, email, username, password_hash, photo, role) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $idno, $lastname, $firstname, $middlename, $course, $year, $email, $username, $password_hash, $target_file, $role);

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
    <title>CCS Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .registration-page {
            background: #f5f5f5;
        }
        
        .card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 480px;
            max-width: 100%;
            margin: 20px auto;
        }
        
        .card-header {
            background: #2C3E50;
            color: white;
            padding: 20px;
            position: relative;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 600;
        }
        
        .card-header p {
            font-size: 14px;
            margin-top: 5px;
            opacity: 0.8;
        }
        
        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2C3E50;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            color: #333;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #2C3E50;
            border: none;
            border-radius: 4px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-submit:hover {
            background: #3498db;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #7f8c8d;
        }
        
        /* Fix for file input */
        .file-input {
            margin: 10px 0;
        }
    </style>
</head>
<body class="registration-page">
    <div class="card">
        <div class="card-header">
            <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
            <h1>Student Registration</h1>
            <p>Create your CCS account</p>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="idno">ID Number</label>
                    <input type="text" id="idno" name="idno" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="lastname">Last Name</label>
                        <input type="text" id="lastname" name="lastname" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="firstname">First Name</label>
                        <input type="text" id="firstname" name="firstname" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="middlename">Middle Name</label>
                    <input type="text" id="middlename" name="middlename" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course">Course</label>
                        <select id="course" name="course" class="form-control" required>
                            <option value="" disabled selected>Select Course</option>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                            <option value="BSECE">BSECE</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year Level</label>
                        <select id="year" name="year" class="form-control" required>
                            <option value="" disabled selected>Select Year</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="profile_photo">Profile Photo</label>
                    <input type="file" id="profile_photo" name="profile_photo" class="file-input" accept="image/*">
                </div>
                
                <button type="submit" name="create" class="btn-submit">Create Account</button>
                
                <div class="form-footer">
                    Already have an account? <a href="index.php">Sign in here</a>
                </div>
            </form>
        </div>
    </div>

    <?php
    if (isset($error_message)) {
        echo "<p style='color: #e74c3c; text-align: center; margin-top: 15px; background: rgba(231, 76, 60, 0.1); padding: 10px; border-radius: 8px;'>$error_message</p>";
    }
    ?>
</body>
</html>
