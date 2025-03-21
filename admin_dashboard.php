<?php
session_start();
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "my_database";

// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure only admins can access
if (!isset($_SESSION["admin_id"])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            color: #333;
            position: relative;
        }

        /* Top Navbar */
        .navbar {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;  /* Ensure navbar stays on top */
            background-color: #2c3e50;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 16px;
            padding: 10px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .navbar a:hover {
            background-color: #1abc9c;  /* New hover color */
            color: white;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
        }

        /* Main Content */
        .content {
            margin-top: 100px; /* Account for the height of the navbar */
            padding: 30px;
            margin: 30px auto;
            width: 85%;
            text-align: center;
        }

        h1 {
            color: #2980b9;
            font-size: 28px;
            margin-bottom: 10px;
        }

        /* Logout Button */
        .logout-container a {
            color: white;
            background-color: #e74c3c;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }

        .logout-container a:hover {
            background-color: #c0392b;
        }

        footer {
            text-align: center;
            padding: 15px;
            background-color: #2c3e50;
            color: white;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: center;
            }

            .content {
                margin-top: 130px;
                width: 100%;
            }

            .navbar .nav-links {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .search-btn {
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .search-btn:hover {
            background-color: #3498db;
        }

        #studentResults {
            margin-top: 20px;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .student-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-top: 15px;
        }

        .student-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 3px solid #2980b9;
        }

        .student-info {
            flex: 1;
            text-align: left;
        }

        .student-info h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .student-info p {
            margin: 5px 0;
            color: #555;
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_sit_in_requests.php">Manage Sit-in Requests</a>
            <a href="approved_sit_in_sessions.php">Approved Sit-in Sessions</a>
            <a href="add_subject.php">Add Subject</a>
            <a href="manage_subjects.php">Manage Subjects</a>
            <a href="add_announcement.php">Add Announcement</a>
            <a href="announcements.php">Announcements</a>
            <a href="#" id="searchBtn">Search</a>
        </div>
        <div class="logout-container">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <!-- Search Modal -->
    <div id="searchModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Search Student</h2>
            <form id="studentSearchForm">
                <div class="form-group">
                    <label for="studentId">Student ID Number:</label>
                    <input type="text" id="studentId" name="studentId" required>
                </div>
                <button type="submit" class="search-btn">Search</button>
            </form>
            <div id="studentResults"></div>
        </div>
    </div>

    <div class="content">
        <h1>Admin Dashboard</h1>
        <!-- Your dashboard content here -->
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the modal
            var modal = document.getElementById("searchModal");
            
            // Get the button that opens the modal
            var btn = document.getElementById("searchBtn");
            
            // Get the <span> element that closes the modal
            var span = document.getElementsByClassName("close")[0];
            
            // When the user clicks the button, open the modal 
            btn.onclick = function() {
                modal.style.display = "block";
            }
            
            // When the user clicks on <span> (x), close the modal
            span.onclick = function() {
                modal.style.display = "none";
            }
            
            // When the user clicks anywhere outside of the modal, close it
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            // Handle form submission
            document.getElementById("studentSearchForm").addEventListener("submit", function(e) {
                e.preventDefault();
                var studentId = document.getElementById("studentId").value;
                
                // AJAX request to search for student
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "search_student.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                        document.getElementById("studentResults").innerHTML = this.responseText;
                    }
                }
                xhr.send("studentId=" + studentId);
            });
        });
    </script>
</body>
</html>
