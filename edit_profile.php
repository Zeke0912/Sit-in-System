<?php
session_start();
require 'config/db.php';

// Initialize error/success messages
$error = '';
$success = '';

$user_id = $_SESSION['user_id'];

// Fetch user details
$query = "SELECT firstname, lastname, email, course, year, idno, photo, username FROM users WHERE idno = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $idno = $_POST['idno'];
    $username = $_POST['username'];
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if the new email is already in use
        $check_email_sql = "SELECT idno FROM users WHERE email = ? AND idno != ?";
        $stmt = $conn->prepare($check_email_sql);
        $stmt->bind_param("ss", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Email is already in use by another account!";
        } else {
            // Check if the new username is already in use
            $check_username_sql = "SELECT idno FROM users WHERE username = ? AND idno != ?";
            $stmt = $conn->prepare($check_username_sql);
            $stmt->bind_param("ss", $username, $user_id);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Username is already in use by another account!";
            } else {
                // Handle file upload
                if (!empty($_FILES["profile_image"]["name"])) {
                    $target_dir = "uploads/";
                    $file_ext = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
                    $allowed_ext = ["jpg", "jpeg", "png", "gif"];
                    
                    if (!in_array($file_ext, $allowed_ext)) {
                        $error = "Invalid file type! Only JPG, PNG, and GIF are allowed.";
                    } else {
                        $target_file = $target_dir . time() . "_" . basename($_FILES["profile_image"]["name"]);
                        if (!move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                            $error = "Failed to upload image!";
                        }
                    }
                } else {
                    $target_file = $user['photo']; // Keep old image
                }

                if (empty($error)) {
                    // Update user details
                    $update_sql = "UPDATE users SET firstname=?, lastname=?, email=?, course=?, year=?, photo=?, username=? WHERE idno=?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("ssssssss", $firstname, $lastname, $email, $course, $year, $target_file, $username, $user_id);

                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $_SESSION['user_info'] = [
                                'firstname' => $firstname,
                                'lastname' => $lastname,
                                'email' => $email,
                                'course' => $course,
                                'year' => $year,
                                'idno' => $idno,
                                'photo' => $target_file,
                                'username' => $username
                            ];

                            // Handle password change
                            if (!empty($old_password) && !empty($new_password) && !empty($confirm_password)) {
                                if ($new_password !== $confirm_password) {
                                    $error = "New password and confirm password do not match!";
                                } else {
                                    // Verify old password
                                    $password_query = "SELECT password_hash FROM users WHERE idno = ?";
                                    $stmt = $conn->prepare($password_query);
                                    $stmt->bind_param("s", $user_id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $user_data = $result->fetch_assoc();

                                    if (password_verify($old_password, $user_data['password_hash'])) {
                                        // Update password
                                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                        $update_password_sql = "UPDATE users SET password_hash=? WHERE idno=?";
                                        $stmt = $conn->prepare($update_password_sql);
                                        $stmt->bind_param("ss", $hashed_password, $user_id);
                                        $stmt->execute();
                                    } else {
                                        $error = "Old password is incorrect!";
                                    }
                                }
                            }

                            if (empty($error)) {
                                header("Location: home.php?success=1"); // Redirect to home dashboard
                                exit();
                            }
                        } else {
                            $error = "No changes were made!";
                        }
                    } else {
                        $error = "Error updating profile: " . $stmt->error;
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Profile</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .edit-profile-container {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .tab-nav {
            margin-bottom: 25px;
            border-bottom: 2px solid #ddd;
        }

        .tab-button {
            padding: 12px 25px;
            background: #e9e9e9;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            margin-right: 5px;
        }

        .tab-button.active {
            background: #4CAF50;
            color: white;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .photo-upload {
            display: flex;
            gap: 20px;
            align-items: center;
            margin: 20px 0;
        }

        img {
            border-radius: 4px;
            border: 2px solid #ddd;
        }

        button[type="submit"] {
            background: #4CAF50;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert.success {
            background: #dff0d8;
            color: #3c763d;
        }

        .alert.error {
            background: #f2dede;
            color: #a94442;
        }

        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            
            .photo-upload {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="edit-profile-container">
        <h2>Edit Profile</h2>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="alert success">Profile updated successfully!</div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            <div class="two-column">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="firstname" value="<?php echo htmlspecialchars($user['firstname'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="lastname" value="<?php echo htmlspecialchars($user['lastname'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="two-column">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" name="idno" value="<?php echo htmlspecialchars($user['idno'] ?? ''); ?>" readonly>
                </div>
            </div>

            <div class="two-column">
                <div class="form-group">
                    <label>Course</label>
                    <select name="course" required>
                        <option value="computer_science" <?= ($user['course'] ?? '') === 'computer_science' ? 'selected' : '' ?>>Computer Science</option>
                        <option value="business" <?= ($user['course'] ?? '') === 'business' ? 'selected' : '' ?>>Information Technology</option>
                        <option value="engineering" <?= ($user['course'] ?? '') === 'engineering' ? 'selected' : '' ?>>Criminology</option>
                        <option value="arts" <?= ($user['course'] ?? '') === 'arts' ? 'selected' : '' ?>>Customs Administration</option>
                        <option value="education" <?= ($user['course'] ?? '') === 'education' ? 'selected' : '' ?>>Education</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year" required>
                        <option value="1" <?= ($user['year'] ?? '') == 1 ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= ($user['year'] ?? '') == 2 ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= ($user['year'] ?? '') == 3 ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4" <?= ($user['year'] ?? '') == 4 ? 'selected' : '' ?>>4th Year</option>
                    </select>
                </div>
            </div>

            <div class="photo-upload">
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_image">
                </div>
                <img src="<?php echo htmlspecialchars($user['photo'] ?? 'uploads/default.png'); ?>" width="120">
            </div>

            <div class="two-column">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Old Password</label>
                    <input type="password" name="old_password">
                </div>
            </div>

            <div class="two-column">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password">
                </div>
            </div>

            <button type="submit">Update Profile</button>
        </form>
    </div>
</body>
</html>