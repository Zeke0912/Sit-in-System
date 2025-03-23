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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            margin-top: 120px; /* Increased from 100px to prevent overlap */
            padding: 30px;
            margin: 120px auto 30px; /* Adjusted top margin */
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
                padding: 10px 0;
            }

            .content {
                margin-top: 200px; /* Increased to account for multi-line nav */
                width: 100%;
            }

            .navbar .nav-links {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 5px;
                margin: 10px 0;
            }
            
            .navbar a {
                font-size: 14px;
                padding: 8px 10px;
            }
            
            .charts-container {
                gap: 10px;
            }
            
            .chart-container {
                width: 100%;
                max-width: 300px;
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
            background-color: rgba(0,0,0,0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 25px;
            border: 1px solid #888;
            width: 60%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease-in-out;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close:hover,
        .close:focus {
            color: #e74c3c;
            text-decoration: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 16px;
            color: #2c3e50;
        }

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: border 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .search-btn, 
        .fetch-btn, 
        .submit-btn {
            background-color: #2980b9;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.2s;
        }

        .search-btn:hover, 
        .fetch-btn:hover, 
        .submit-btn:hover {
            background-color: #3498db;
            transform: translateY(-2px);
        }
        
        .search-btn:active,
        .fetch-btn:active,
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .fetch-btn {
            margin-top: 12px;
            width: 100%;
        }
        
        .submit-btn {
            margin-top: 25px;
            width: 100%;
            padding: 14px;
            font-size: 16px;
            background-color: #27ae60;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        .submit-btn:hover {
            background-color: #2ecc71;
        }

        /* Enhanced student info display */
        .student-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border-left: 4px solid #3498db;
        }
        
        .student-profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2980b9;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        }
        
        .student-info-details {
            flex: 1;
        }
        
        .student-summary p {
            margin: 8px 0;
            font-size: 15px;
        }
        
        .student-name {
            font-weight: bold;
            font-size: 20px !important;
            color: #2c3e50;
            margin-bottom: 12px !important;
        }
        
        .sessions-count {
            font-weight: bold;
            color: #27ae60;
            font-size: 16px !important;
        }
        
        .modal h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        #studentResults, 
        #sitInResults {
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
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
        
        /* Stats Container Styles */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin-bottom: 25px;
        }
        
        .stats-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            min-width: 120px;
            max-width: 150px;
            flex: 1;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            background-color: #f8f9fa;
            color: #3498db;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 10px;
            font-size: 18px;
        }
        
        .stats-info h3 {
            font-size: 12px;
            margin: 0 0 8px 0;
            color: #7f8c8d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-count {
            font-size: 22px;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        
        /* Charts Container */
        .charts-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 15px;
            width: 300px; /* Increased from 250px */
            height: 300px; /* Increased from 250px */
            position: relative;
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .chart-title {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 10px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <div class="navbar">
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_sit_in_requests.php">Manage Sit-in Requests</a>
            <a href="approved_sit_in_sessions.php">Sit in Records</a>
            <a href="active_sitin.php">Active Sit-ins</a>
            <a href="add_subject.php">Add Subject</a>
            <a href="announcements.php">Announcements</a>
            <a href="#" id="searchBtn">Search</a>
            <a href="#" id="sitInBtn">Sit-in</a>
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

    <!-- Direct Sit-in Modal -->
    <div id="sitInModal" class="modal">
        <div class="modal-content">
            <span class="close sitInClose">&times;</span>
            <h2>Direct Sit-in Registration</h2>
            <form id="sitInForm">
                <div class="form-group">
                    <label for="sitInStudentId">Student ID Number:</label>
                    <input type="text" id="sitInStudentId" name="studentId" placeholder="Enter student ID" required>
                    <button type="button" id="fetchStudentBtn" class="fetch-btn">Search</button>
                </div>
                
                <div id="studentInfo" style="display: none;">
                    <div class="student-summary" style="background-color: #e8f4f8; border-left: 5px solid #3498db; padding: 20px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                        <div class="student-info-details" style="width: 100%;">
                            <p class="student-name" style="font-size: 22px !important; color: #2c3e50; margin-bottom: 15px !important; font-weight: bold;"><span id="studentName"></span></p>
                            <hr style="border: 0; height: 1px; background-color: #ddd; margin: 10px 0;">
                            <p style="font-size: 16px; font-weight: bold; color: #2c3e50; margin: 10px 0;"><strong>ID Number:</strong> <span id="studentIdDisplay"></span></p>
                            <p style="margin: 10px 0;"><strong>Course:</strong> <span id="studentCourse"></span></p>
                            <p style="margin: 10px 0;"><strong>Year:</strong> <span id="studentYear"></span></p>
                            <p class="sessions-count" style="font-size: 16px !important; margin: 10px 0; font-weight: bold;"><strong>Remaining Sessions:</strong> <span id="remainingSessions"></span></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subjectId">Select Laboratory:</label>
                        <select id="subjectId" name="subjectId" required>
                            <option value="">Select a laboratory</option>
                            <?php
                            // Fetch and list all subjects
                            $subjectSql = "SELECT id, subject_name, lab_number FROM subjects";
                            $subjectResult = $conn->query($subjectSql);
                            
                            if ($subjectResult->num_rows > 0) {
                                while($subject = $subjectResult->fetch_assoc()) {
                                    echo '<option value="' . $subject['id'] . '">' . $subject['lab_number'] . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose of Sit-in:</label>
                        <select id="purpose" name="purpose" required>
                            <option value="">Select a purpose</option>
                            <option value="Java">Java</option>
                            <option value="PHP">PHP</option>
                            <option value="ASP.NET">ASP.NET</option>
                            <option value="C#">C#</option>
                            <option value="Python">Python</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="otherPurposeGroup" style="display: none;">
                        <label for="otherPurpose">Specify Purpose:</label>
                        <input type="text" id="otherPurpose" name="otherPurpose" placeholder="Please specify purpose">
                    </div>
                    
                    <button type="submit" class="submit-btn">Register Sit-in Session</button>
                </div>
            </form>
            <div id="sitInResults"></div>
        </div>
    </div>

    <div class="content">
        <h1>Admin Dashboard</h1>
        
        <!-- Current Sit-in Statistics -->
        <div class="stats-container">
            <?php
            // Query to count active sit-ins
            $activeSitInQuery = "SELECT COUNT(*) as active_count FROM sit_in_requests WHERE is_active = 1";
            $activeSitInResult = $conn->query($activeSitInQuery);
            $activeSitInCount = 0;
            
            if ($activeSitInResult && $activeSitInRow = $activeSitInResult->fetch_assoc()) {
                $activeSitInCount = $activeSitInRow['active_count'];
            }
            
            // Query to count total students in current sit-ins
            $studentsQuery = "SELECT COUNT(DISTINCT student_id) as student_count FROM sit_in_requests WHERE is_active = 1";
            $studentsResult = $conn->query($studentsQuery);
            $studentCount = 0;
            
            if ($studentsResult && $studentsRow = $studentsResult->fetch_assoc()) {
                $studentCount = $studentsRow['student_count'];
            }
            
            // Get purpose statistics for pie chart (programming languages)
            $purposeStatsSql = "SELECT purpose, COUNT(*) as count 
                               FROM sit_in_requests 
                               WHERE is_active = 1 
                               GROUP BY purpose";
            $purposeStatsResult = $conn->query($purposeStatsSql);
            $purposeData = [];
            while ($row = $purposeStatsResult->fetch_assoc()) {
                $purposeData[$row['purpose']] = (int)$row['count'];
            }
            
            // Get lab statistics for pie chart
            $labStatsSql = "SELECT s.lab_number, COUNT(*) as count 
                           FROM sit_in_requests r
                           JOIN subjects s ON r.subject_id = s.id
                           WHERE r.is_active = 1 
                           GROUP BY s.lab_number";
            $labStatsResult = $conn->query($labStatsSql);
            $labData = [];
            while ($row = $labStatsResult->fetch_assoc()) {
                $labData[$row['lab_number']] = (int)$row['count'];
            }
            ?>
            
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stats-info">
                    <div class="stats-count"><?php echo $activeSitInCount; ?></div>
                    <h3>Active Sit-ins</h3>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-info">
                    <div class="stats-count"><?php echo $studentCount; ?></div>
                    <h3>Students In Labs</h3>
                </div>
            </div>
        </div>
        <!-- End of Current Sit-in Statistics -->
        
        <!-- Charts for Current Sit-ins -->
        <div class="charts-container">
            <!-- Programming Languages Chart -->
            <div class="chart-container">
                <div class="chart-title">Programming Languages</div>
                <canvas id="purposeChart"></canvas>
            </div>
            
            <!-- Labs Chart -->
            <div class="chart-container">
                <div class="chart-title">Laboratory Usage</div>
                <canvas id="labChart"></canvas>
            </div>
        </div>
        <!-- End of Charts for Current Sit-ins -->
        
        <!-- Your dashboard content here -->
    </div>

    <footer>
        &copy; <?php echo date("Y"); ?> Sit-in Monitoring System
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the search modal
            var searchModal = document.getElementById("searchModal");
            
            // Get the search button that opens the modal
            var searchBtn = document.getElementById("searchBtn");
            
            // Get the <span> element that closes the search modal
            var searchClose = document.getElementsByClassName("close")[0];
            
            // When the user clicks the search button, open the search modal 
            searchBtn.onclick = function() {
                searchModal.style.display = "block";
            }
            
            // When the user clicks on <span> (x), close the search modal
            searchClose.onclick = function() {
                searchModal.style.display = "none";
            }
            
            // Get the sit-in modal
            var sitInModal = document.getElementById("sitInModal");
            
            // Get the sit-in button that opens the modal
            var sitInBtn = document.getElementById("sitInBtn");
            
            // Get the <span> element that closes the sit-in modal
            var sitInClose = document.getElementsByClassName("sitInClose")[0];
            
            // When the user clicks the sit-in button, open the sit-in modal 
            sitInBtn.onclick = function() {
                sitInModal.style.display = "block";
            }
            
            // When the user clicks on <span> (x), close the sit-in modal
            sitInClose.onclick = function() {
                sitInModal.style.display = "none";
            }
            
            // When the user clicks anywhere outside of the modals, close them
            window.onclick = function(event) {
                if (event.target == searchModal) {
                    searchModal.style.display = "none";
                }
                if (event.target == sitInModal) {
                    sitInModal.style.display = "none";
                }
            }

            // Handle search form submission
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
            
            // Handle fetch student button click for sit-in form
            document.getElementById("fetchStudentBtn").addEventListener("click", function() {
                var studentId = document.getElementById("sitInStudentId").value;
                
                if (!studentId) {
                    alert("Please enter a student ID");
                    return;
                }
                
                // Show loading indicator
                document.getElementById("fetchStudentBtn").textContent = "Loading...";
                document.getElementById("fetchStudentBtn").disabled = true;
                
                // Clear previous results
                document.getElementById("studentInfo").style.display = "none";
                document.getElementById("sitInResults").innerHTML = "";
                
                // AJAX request to fetch student info
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "register_direct_sitin.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE) {
                        // Reset button
                        document.getElementById("fetchStudentBtn").textContent = "Search";
                        document.getElementById("fetchStudentBtn").disabled = false;
                        
                        if (this.status === 200) {
                            try {
                                console.log("Raw response text:", this.responseText);
                                var response = JSON.parse(this.responseText);
                                console.log("Full parsed response:", response);
                                
                                if (response.success) {
                                    // Display student info
                                    var student = response.student;
                                    console.log("FULL STUDENT OBJECT:", student);
                                    
                                    // Format name with optional middle name
                                    document.getElementById("studentName").textContent = student.firstname + " " + (student.middlename ? student.middlename + " " : "") + student.lastname;
                                    
                                    // Fix ID number display - ensure it's always shown
                                    var inputStudentId = document.getElementById("sitInStudentId").value;
                                    console.log("Student ID from database (idno):", student.idno);
                                    console.log("Student ID from input field:", inputStudentId);
                                    
                                    // Use student ID from database if available, otherwise use the input value
                                    var displayId = student.idno || inputStudentId;
                                    document.getElementById("studentIdDisplay").textContent = displayId;
                                    
                                    document.getElementById("studentCourse").textContent = student.course;
                                    document.getElementById("studentYear").textContent = student.year;
                                    
                                    // Always use 30 as default for remaining sessions if not set
                                    var remainingSessions = (student.remaining_sessions !== null && student.remaining_sessions !== undefined) ? 
                                        parseInt(student.remaining_sessions) : 30;
                                    
                                    console.log("Final remaining sessions value:", remainingSessions);
                                    document.getElementById("remainingSessions").textContent = remainingSessions;
                                    
                                    // Check remaining sessions and style accordingly
                                    var remainingSessionsSpan = document.getElementById("remainingSessions");
                                    if (parseInt(remainingSessions) <= 5) {
                                        remainingSessionsSpan.style.color = "#e74c3c"; // Red for low sessions
                                    } else {
                                        remainingSessionsSpan.style.color = "#27ae60"; // Green for enough sessions
                                    }
                                    
                                    document.getElementById("studentInfo").style.display = "block";
                                    
                                    // Auto-scroll to the form
                                    document.getElementById("studentInfo").scrollIntoView({behavior: "smooth"});
                                } else {
                                    alert(response.message);
                                }
                            } catch (e) {
                                console.error("Error parsing JSON:", e, this.responseText);
                                alert("Error fetching student information. Response is not valid JSON.");
                            }
                        } else {
                            console.error("Server error:", this.status);
                            alert("Server error: " + this.status + ". Please try again later.");
                        }
                    }
                };
                
                xhr.onerror = function() {
                    document.getElementById("fetchStudentBtn").textContent = "Fetch Student";
                    document.getElementById("fetchStudentBtn").disabled = false;
                    console.error("Request failed");
                    alert("Network error. Please check your connection and try again.");
                };
                
                xhr.send("action=fetch&studentId=" + studentId);
            });
            
            // Handle purpose dropdown change
            document.getElementById("purpose").addEventListener("change", function() {
                var otherPurposeGroup = document.getElementById("otherPurposeGroup");
                if (this.value === "Other") {
                    otherPurposeGroup.style.display = "block";
                } else {
                    otherPurposeGroup.style.display = "none";
                }
            });
            
            // Handle sit-in form submission
            document.getElementById("sitInForm").addEventListener("submit", function(e) {
                e.preventDefault();
                
                var studentId = document.getElementById("sitInStudentId").value;
                var subjectId = document.getElementById("subjectId").value;
                var purpose = document.getElementById("purpose").value;
                var studentName = document.getElementById("studentName").textContent;
                var studentCourse = document.getElementById("studentCourse").textContent;
                var studentYear = document.getElementById("studentYear").textContent;
                
                if (purpose === "Other") {
                    purpose = document.getElementById("otherPurpose").value;
                }
                
                if (!studentId || !subjectId || !purpose) {
                    alert("Please fill all required fields");
                    return;
                }
                
                // Clear previous results
                document.getElementById("sitInResults").innerHTML = "";
                
                // Show loading indicator on the button
                var submitBtn = document.querySelector(".submit-btn");
                var originalText = submitBtn.textContent;
                submitBtn.textContent = "Processing...";
                submitBtn.disabled = true;
                
                // AJAX request to register sit-in
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "register_direct_sitin.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                        // Reset button
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                        
                        document.getElementById("sitInResults").innerHTML = this.responseText;
                        
                        // If success message is displayed, reset the form after 3 seconds
                        if (document.querySelector(".success-message")) {
                            setTimeout(function() {
                                document.getElementById("studentInfo").style.display = "none";
                                document.getElementById("sitInForm").reset();
                            }, 5000);
                        }
                    }
                }
                
                // Make sure to pass the student ID number that was entered
                console.log("Submitting student ID:", studentId);
                
                xhr.send("studentId=" + studentId + "&subjectId=" + subjectId + "&purpose=" + purpose + 
                        "&studentName=" + encodeURIComponent(studentName) + "&studentCourse=" + 
                        encodeURIComponent(studentCourse) + "&studentYear=" + encodeURIComponent(studentYear));
            });
            
            // Chart.js setup for Purpose Pie Chart
            const purposeCtx = document.getElementById('purposeChart').getContext('2d');
            const purposeData = <?php echo json_encode(array_values($purposeData)); ?>;
            const purposeLabels = <?php echo json_encode(array_keys($purposeData)); ?>;
            
            // Colors for purpose chart
            const purposeColors = [
                '#1abc9c', // Turquoise
                '#3498db', // Blue
                '#e67e22', // Orange 
                '#f1c40f', // Yellow
                '#9b59b6', // Purple
                '#34495e'  // Dark blue
            ];
            
            new Chart(purposeCtx, {
                type: 'pie',
                data: {
                    labels: purposeLabels,
                    datasets: [{
                        data: purposeData,
                        backgroundColor: purposeColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.formattedValue;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.raw / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Chart.js setup for Lab Pie Chart
            const labCtx = document.getElementById('labChart').getContext('2d');
            const labData = <?php echo json_encode(array_values($labData)); ?>;
            const labLabels = <?php echo json_encode(array_keys($labData)); ?>;
            
            // Colors for lab chart
            const labColors = [
                '#ff9ff3', // Pink
                '#feca57', // Yellow
                '#ff6b6b', // Red
                '#1dd1a1', // Green
                '#54a0ff', // Blue
                '#5f27cd'  // Purple
            ];
            
            new Chart(labCtx, {
                type: 'pie',
                data: {
                    labels: labLabels,
                    datasets: [{
                        data: labData,
                        backgroundColor: labColors,
                        borderWidth: 1,
                        borderColor: '#fff',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.formattedValue;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.raw / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
