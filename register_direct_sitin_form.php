<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Sit-In Registration</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/direct_pc_selection.css">
    <style>
        /* Additional styling for this form */
        .card {
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: white;
        }
        
        .card-header {
            background-color: #3498db;
            color: white;
            padding: 15px 20px;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
        }
        
        .card-header h2 i {
            margin-right: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 15px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-primary {
            background-color: #3498db;
        }
        
        .btn-success {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: #e74c3c;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .student-info {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .student-details h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .student-details p {
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        
        .student-details p i {
            width: 20px;
            margin-right: 10px;
            color: #3498db;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-group {
                flex-basis: 100%;
            }
        }
        
        .search-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        #searchResults {
            padding: 15px;
            border-radius: 8px;
        }
        
        .error-message {
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            border-radius: 4px;
        }
        
        .success-message {
            padding: 15px;
            margin-bottom: 20px;
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav>
    <ul>
        <li><a href="admin_home.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="control_pc.php"><i class="fas fa-desktop"></i> Control PC</a></li>
        <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li><a href="register_direct_sitin.php" class="active"><i class="fas fa-user-plus"></i> Register Sit-In</a></li>
        <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
    <div class="logout-container">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<!-- Page Content -->
<div class="container">
    <h1><i class="fas fa-user-plus"></i> Direct Sit-In Registration</h1>
    
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-search"></i> Search Student</h2>
        </div>
        <div class="card-body">
            <div class="search-section">
                <div class="form-row">
                    <div class="form-group">
                        <label for="studentId">Student ID:</label>
                        <input type="text" id="studentId" class="form-control" placeholder="Enter student ID number">
                    </div>
                    <div class="form-group" style="flex: 0 0 auto; align-self: flex-end;">
                        <button id="searchBtn" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="searchResults"></div>
        </div>
    </div>
    
    <div id="registrationForm" style="display: none;">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-calendar-check"></i> Register for Sit-In</h2>
            </div>
            <div class="card-body">
                <form id="sitInForm" method="post" action="register_direct_sitin.php">
                    <!-- Hidden fields for the student information -->
                    <input type="hidden" id="hiddenStudentId" name="studentId">
                    <input type="hidden" id="hiddenStudentName" name="studentName">
                    <input type="hidden" id="hiddenStudentCourse" name="studentCourse">
                    <input type="hidden" id="hiddenStudentYear" name="studentYear">
                    
                    <div class="student-info">
                        <div class="student-details">
                            <h4 id="studentNameDisplay">Student Name</h4>
                            <p><i class="fas fa-id-card"></i> <span id="studentIdDisplay">Student ID</span></p>
                            <p><i class="fas fa-graduation-cap"></i> <span id="studentCourseDisplay">Course</span></p>
                            <p><i class="fas fa-user-graduate"></i> <span id="studentYearDisplay">Year</span></p>
                            <p><i class="fas fa-layer-group"></i> <strong>Remaining Sessions:</strong> <span id="remainingSessionsDisplay">0</span></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subjectId">Laboratory:</label>
                            <select id="subjectId" name="subjectId" class="form-control" required>
                                <option value="">Select Laboratory</option>
                                <!-- Options will be populated dynamically -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose:</label>
                            <select id="purpose" name="purpose" class="form-control" required>
                                <option value="">Select Purpose</option>
                                <option value="Java">Java</option>
                                <option value="PHP">PHP</option>
                                <option value="ASP.NET">ASP.NET</option>
                                <option value="C#">C#</option>
                                <option value="Python">Python</option>
                                <option value="C Programming">C Programming</option>
                                <option value="Database">Database</option>
                                <option value="Digital & Logic Design">Digital & Logic Design</option>
                                <option value="Embedded Systems & IoT">Embedded Systems & IoT</option>
                                <option value="System Integration & Architecture">System Integration & Architecture</option>
                                <option value="Computer Application">Computer Application</option>
                                <option value="Project Management">Project Management</option>
                                <option value="IT Trends">IT Trends</option>
                                <option value="Technopreneurship">Technopreneurship</option>
                                <option value="Capstone">Capstone</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- PC Selection Section -->
                    <div class="form-group">
                        <label for="pc-selection-container">Select PC:</label>
                        <div id="pc-selection-container">
                            <p class="text-muted">Please select a laboratory first to view available PCs.</p>
                        </div>
                        <input type="hidden" id="pc_number" name="pc_number">
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-check-circle"></i> Register Student for Sit-In
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div id="responseMessage"></div>
</div>

<script src="js/direct_pc_selection.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch laboratories when the page loads
        fetchLaboratories();
        
        // Add event listener to search button
        document.getElementById('searchBtn').addEventListener('click', searchStudent);
        
        // Add event listener for enter key on student ID field
        document.getElementById('studentId').addEventListener('keyup', function(event) {
            if (event.key === 'Enter') {
                searchStudent();
            }
        });
        
        // Add event listener for form submission
        document.getElementById('sitInForm').addEventListener('submit', function(event) {
            event.preventDefault();
            registerSitIn();
        });
    });
    
    // Function to fetch laboratories
    function fetchLaboratories() {
        fetch('admin_get_labs.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('subjectId');
                    data.laboratories.forEach(lab => {
                        const option = document.createElement('option');
                        option.value = lab.id;
                        option.textContent = lab.lab_number;
                        select.appendChild(option);
                    });
                } else {
                    console.error('Error fetching laboratories:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
    
    // Function to search for a student
    function searchStudent() {
        const studentId = document.getElementById('studentId').value.trim();
        
        if (!studentId) {
            document.getElementById('searchResults').innerHTML = '<div class="error-message">Please enter a student ID.</div>';
            return;
        }
        
        document.getElementById('searchResults').innerHTML = 
            '<div class="text-center p-4">' +
            '<i class="fas fa-spinner fa-spin fa-2x"></i>' +
            '<p class="mt-2">Searching for student...</p>' +
            '</div>';
        
        // Create form data for the request
        const formData = new FormData();
        formData.append('action', 'fetch');
        formData.append('studentId', studentId);
        
        // Send the request
        fetch('register_direct_sitin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStudentInfo(data.student);
            } else {
                document.getElementById('searchResults').innerHTML = 
                    '<div class="error-message">' + data.message + '</div>';
                document.getElementById('registrationForm').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('searchResults').innerHTML = 
                '<div class="error-message">Error searching for student. Please try again.</div>';
        });
    }
    
    // Function to display student information
    function displayStudentInfo(student) {
        // Display student information
        document.getElementById('studentNameDisplay').textContent = student.firstname + ' ' + student.lastname;
        document.getElementById('studentIdDisplay').textContent = student.idno;
        document.getElementById('studentCourseDisplay').textContent = student.course;
        document.getElementById('studentYearDisplay').textContent = student.year;
        document.getElementById('remainingSessionsDisplay').textContent = student.remaining_sessions || '0';
        
        // Set hidden fields
        document.getElementById('hiddenStudentId').value = student.idno;
        document.getElementById('hiddenStudentName').value = student.firstname + ' ' + student.lastname;
        document.getElementById('hiddenStudentCourse').value = student.course;
        document.getElementById('hiddenStudentYear').value = student.year;
        
        // Show registration form
        document.getElementById('registrationForm').style.display = 'block';
        
        // Success message in search results
        document.getElementById('searchResults').innerHTML = 
            '<div class="success-message">' +
            '<i class="fas fa-check-circle"></i> Student found. Please complete the registration form below.' +
            '</div>';
        
        // Scroll to registration form
        document.getElementById('registrationForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Function to register sit-in
    function registerSitIn() {
        const form = document.getElementById('sitInForm');
        const formData = new FormData(form);
        
        // Validate form
        if (!formData.get('subjectId')) {
            document.getElementById('responseMessage').innerHTML = 
                '<div class="error-message">Please select a laboratory.</div>';
            return;
        }
        
        if (!formData.get('purpose')) {
            document.getElementById('responseMessage').innerHTML = 
                '<div class="error-message">Please select a purpose.</div>';
            return;
        }
        
        if (!formData.get('pc_number')) {
            document.getElementById('responseMessage').innerHTML = 
                '<div class="error-message">Please select a PC.</div>';
            return;
        }
        
        // Show loading message
        document.getElementById('responseMessage').innerHTML = 
            '<div class="text-center p-4">' +
            '<i class="fas fa-spinner fa-spin fa-2x"></i>' +
            '<p class="mt-2">Processing registration...</p>' +
            '</div>';
        
        // Send the registration request
        fetch('register_direct_sitin.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('responseMessage').innerHTML = html;
            // Scroll to response message
            document.getElementById('responseMessage').scrollIntoView({ behavior: 'smooth' });
            
            // If success message is present, reset the form
            if (html.includes('successfully')) {
                document.getElementById('sitInForm').reset();
                document.getElementById('registrationForm').style.display = 'none';
                document.getElementById('searchResults').innerHTML = '';
                document.getElementById('studentId').value = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('responseMessage').innerHTML = 
                '<div class="error-message">Error processing registration. Please try again.</div>';
        });
    }
</script>

</body>
</html> 
