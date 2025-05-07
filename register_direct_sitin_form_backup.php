<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct Sit-in Registration</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a6cf7;
            --primary-dark: #3a56d8;
            --secondary-color: #2b3a67;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --maintenance-color: #9b59b6;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --text-color: #333;
            --border-color: #e0e0e0;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius: 8px;
        }
        
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-header {
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }
        
        .form-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: #666;
            font-size: 16px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px 20px;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74,108,247,0.25);
            outline: none;
        }
        
        .form-section {
            margin-bottom: 30px;
            background-color: var(--light-color);
            padding: 20px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }
        
        .form-section h3 {
            margin-top: 0;
            color: var(--secondary-color);
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 12px 20px;
            font-size: 16px;
            line-height: 1.5;
            border-radius: var(--border-radius);
            transition: var(--transition);
            cursor: pointer;
            color: #fff;
            background-color: var(--primary-color);
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .student-info {
            background-color: rgba(74,108,247,0.1);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(74,108,247,0.2);
        }
        
        .student-info h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 22px;
            border-bottom: 1px solid rgba(74,108,247,0.2);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .student-info p {
            margin-bottom: 10px;
            font-size: 16px;
            color: var(--text-color);
        }
        
        .student-info strong {
            color: var(--secondary-color);
        }
        
        /* Custom styling for the maintenance warning */
        .maintenance-warning {
            background-color: #f8d7da;
            border-left: 5px solid var(--maintenance-color);
            color: #721c24;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        /* Success message styles */
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Error message styles */
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <h2><i class="fas fa-user-graduate"></i> Direct Sit-in Registration</h2>
        <p>Register students directly for sit-in sessions with automatic approval</p>
    </div>
    
    <form id="direct-sitin-form" method="POST" action="register_direct_sitin.php">
        <div class="form-section">
            <h3><i class="fas fa-id-card"></i> Student Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="studentId">Student ID Number:</label>
                    <input type="text" id="studentId" name="studentId" class="form-control" required placeholder="Enter student ID number">
                </div>
                <div class="form-group">
                    <div class="btn" id="fetch-student-btn" style="margin-top: 28px;">
                        <i class="fas fa-search"></i> Find Student
                    </div>
                </div>
            </div>
            
            <div id="student-details" class="student-info" style="display: none;">
                <h3>Student Details</h3>
                <div class="form-row">
                    <div class="form-group">
                        <p><strong>Name:</strong> <span id="studentName"></span></p>
                        <input type="hidden" id="studentNameInput" name="studentName">
                    </div>
                    <div class="form-group">
                        <p><strong>ID Number:</strong> <span id="studentIdDisplay"></span></p>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <p><strong>Course:</strong> <span id="studentCourse"></span></p>
                        <input type="hidden" id="studentCourseInput" name="studentCourse">
                    </div>
                    <div class="form-group">
                        <p><strong>Year:</strong> <span id="studentYear"></span></p>
                        <input type="hidden" id="studentYearInput" name="studentYear">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <p><strong>Remaining Sessions:</strong> <span id="remainingSessions"></span></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3><i class="fas fa-desktop"></i> Laboratory & PC Selection</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="subject">Laboratory:</label>
                    <select id="subject" name="subjectId" class="form-control" required>
                        <option value="">Select Laboratory</option>
                        <!-- Options will be populated from database -->
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
            
            <!-- PC Selection Container -->
            <div id="pc-selection-container">
                <p>Please select a laboratory first to view available PCs.</p>
            </div>
            <input type="hidden" id="pc_number" name="pc_number" value="">
        </div>
        
        <button type="submit" class="btn btn-block">
            <i class="fas fa-check-circle"></i> Register Student for Sit-in
        </button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch and populate labs/subjects
        fetch('register_direct_sitin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=fetch_labs'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const subjectSelect = document.getElementById('subject');
                data.labs.forEach(lab => {
                    const option = document.createElement('option');
                    option.value = lab.id;
                    option.textContent = `Lab ${lab.lab_number}`;
                    subjectSelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading labs:', error));
        
        // Handle fetch student button click
        document.getElementById('fetch-student-btn').addEventListener('click', function() {
            const studentId = document.getElementById('studentId').value;
            if (!studentId) {
                alert('Please enter a student ID');
                return;
            }
            
            // Show loading state
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
            
            // Fetch student data
            fetch('register_direct_sitin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=fetch&studentId=' + studentId
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                this.innerHTML = '<i class="fas fa-search"></i> Find Student';
                
                if (data.success) {
                    // Show student details
                    document.getElementById('student-details').style.display = 'block';
                    
                    // Populate data
                    const student = data.student;
                    document.getElementById('studentName').textContent = `${student.firstname} ${student.lastname}`;
                    document.getElementById('studentIdDisplay').textContent = student.idno;
                    document.getElementById('studentCourse').textContent = student.course;
                    document.getElementById('studentYear').textContent = student.year;
                    document.getElementById('remainingSessions').textContent = student.remaining_sessions;
                    
                    // Set hidden inputs
                    document.getElementById('studentNameInput').value = `${student.firstname} ${student.lastname}`;
                    document.getElementById('studentCourseInput').value = student.course;
                    document.getElementById('studentYearInput').value = student.year;
                } else {
                    alert(data.message || 'Error finding student');
                    document.getElementById('student-details').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.innerHTML = '<i class="fas fa-search"></i> Find Student';
                alert('Error connecting to server');
            });
        });

        // Handle Enter key on studentId field
        document.getElementById('studentId').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('fetch-student-btn').click();
            }
        });
    });
</script>

<!-- Include PC selection script -->
<script src="js/direct_pc_selection.js"></script>

</body>
</html> 
