<?php
include 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch all active resources
$query = "SELECT r.*, CONCAT(a.firstname, ' ', a.lastname) as admin_name 
          FROM resources r
          LEFT JOIN users a ON r.created_by = a.idno
          WHERE r.is_active = 1
          ORDER BY r.created_at DESC";
$result = $conn->query($query);
$resources = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }
}

// Group resources by type for better organization
$grouped_resources = [
    'link' => [],
    'video' => [],
    'file' => [],
    'image' => []
];

foreach ($resources as $resource) {
    $grouped_resources[$resource['resource_type']][] = $resource;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Updated Navigation Styles - Sidebar */
        :root {
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary-color: #4cc9f0;
            --accent-color: #f72585;
            --text-color: #2c3e50;
            --text-light: #7f8c8d;
            --bg-color: #f8f9fa;
            --link-color: #3498db;
            --video-color: #e74c3c;
            --file-color: #f39c12;
            --image-color: #1abc9c;
            --sidebar-width: 240px;
            --sidebar-bg: #2c3e50;
            --sidebar-color: #ecf0f1;
        }
        
        body {
            background-color: var(--bg-color);
            background-image: linear-gradient(135deg, rgba(67, 97, 238, 0.05) 0%, rgba(76, 201, 240, 0.05) 100%);
            background-attachment: fixed;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            overflow-x: hidden;
        }
        
        /* Sidebar Navigation Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
        }
        
        .sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .sidebar-header p {
            margin: 5px 0 0;
            font-size: 14px;
            opacity: 0.7;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        
        .sidebar-menu li {
            padding: 0;
            margin: 1px 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--sidebar-color);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu li a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--primary-light);
        }
        
        .sidebar-menu li a:hover {
            background-color: rgba(255, 255, 255, 0.07);
        }
        
        .sidebar-menu li a i {
            width: 24px;
            margin-right: 10px;
            text-align: center;
        }
        
        .logout-button {
            margin: 15px;
            margin-top: auto;
        }
        
        .logout-button a {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .logout-button a:hover {
            background-color: #c0392b;
        }
        
        .logout-button a i {
            margin-right: 8px;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: margin-left 0.3s;
            width: calc(100% - var(--sidebar-width));
            background-color: #f0f2f5;
        }
        
        /* Main Title */
        .main-title {
            margin-bottom: 30px;
            padding-left: 10px;
        }
        
        .main-title h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin: 0;
            padding: 0;
            font-weight: 600;
            border-bottom: none;
        }
        
        /* Main container */
        .container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 0;
            background-color: transparent;
            border-radius: 0;
            box-shadow: none;
        }
        
        .intro-text {
            font-size: 1rem;
            line-height: 1.6;
            color: var(--text-color);
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            border-left: 5px solid #4361ee;
        }
        
        .intro-text i {
            color: #f39c12;
            margin-right: 10px;
        }
        
        /* Resources Container Styles */
        .resources-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .resource-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            flex: 1 1 300px;
            min-width: 300px;
            max-width: 100%;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
        }
        
        .resource-section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            padding: 15px 20px;
            margin: 0;
            display: flex;
            align-items: center;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        
        .resource-section-title.link {
            background-color: var(--link-color);
        }
        
        .resource-section-title.video {
            background-color: var(--video-color);
        }
        
        .resource-section-title.file {
            background-color: var(--file-color);
        }
        
        .resource-section-title.image {
            background-color: #1abc9c;
        }
        
        .resource-section-title i {
            margin-right: 10px;
            color: white;
        }
        
        .resource-cards {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 20px;
            flex-grow: 1;
        }
        
        .resource-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 15px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            border: 1px solid #eee;
        }
        
        .resource-card::before {
            display: none;
        }
        
        .resource-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-color);
            line-height: 1.4;
        }
        
        .resource-description {
            margin-bottom: 15px;
            color: var(--text-color);
            line-height: 1.5;
            flex-grow: 1;
            font-size: 0.9rem;
        }
        
        .resource-meta {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }
        
        .resource-preview {
            margin-bottom: 15px;
            text-align: center;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #eee;
        }
        
        .resource-action {
            text-align: center;
        }
        
        .btn-access {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9rem;
            width: auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .no-resources {
            text-align: center;
            padding: 40px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            width: 100%;
        }
        
        /* Image lightbox */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            z-index: 1000;
            text-align: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .lightbox.active {
            opacity: 1;
        }
        
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            margin: auto;
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            border-radius: 5px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .lightbox.active img {
            transform: scale(1);
        }
        
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            background-color: rgba(0,0,0,0.3);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .lightbox-close:hover {
            background-color: rgba(231, 76, 60, 0.8);
            transform: rotate(90deg);
        }
        
        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .resource-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .resource-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .resource-cards {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            /* Show mobile menu toggle button */
            .mobile-menu-toggle {
                display: block;
            }
        }
        
        /* Animations */
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
        
        /* Mobile Menu Toggle Button */
        .mobile-menu-toggle {
            display: none; /* Hidden by default on desktop */
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 110;
            background-color: var(--sidebar-bg);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Sit-in Monitoring</h2>
            <p>Student Portal</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a></li>
            <li><a href="student_sit_in_records.php"><i class="fas fa-history"></i> Records</a></li>
            <li><a href="redeem_points.php"><i class="fas fa-gift"></i> Redeem Points</a></li>
            <li><a href="lab_schedules.php"><i class="fas fa-calendar-alt"></i> Lab Schedules</a></li>
            <li><a href="resources.php" class="active"><i class="fas fa-book-open"></i> Resources</a></li>
            <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
        </ul>
        <div class="logout-button">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Main Title -->
        <div class="main-title">
            <h1>Learning Resources</h1>
        </div>
        
       
            <div class="resources-container">
                <?php if (empty($resources)): ?>
                    <div class="no-resources">
                        <i class="fas fa-info-circle pulse"></i>
                        <p>No resources are currently available. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <!-- Links Section -->
                    <?php if (!empty($grouped_resources['link'])): ?>
                        <div class="resource-section">
                            <h2 class="resource-section-title link">
                                <i class="fas fa-link"></i> Helpful Links
                            </h2>
                            <div class="resource-cards">
                                <?php foreach ($grouped_resources['link'] as $resource): ?>
                                    <div class="resource-card link">
                                        <span class="resource-type-badge link"><i class="fas fa-link"></i> Link</span>
                                        <div class="resource-title"><?php echo $resource['title']; ?></div>
                                        <div class="resource-description"><?php echo $resource['description']; ?></div>
                                        <div class="resource-meta">
                                            <span><i class="fas fa-user"></i> <?php echo $resource['admin_name']; ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                                        </div>
                                        <div class="resource-action">
                                            <a href="<?php echo $resource['resource_url']; ?>" target="_blank" class="btn-access link">
                                                <i class="fas fa-external-link-alt"></i> Visit Link
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Videos Section -->
                    <?php if (!empty($grouped_resources['video'])): ?>
                        <div class="resource-section">
                            <h2 class="resource-section-title video">
                                <i class="fas fa-video"></i> Educational Videos
                            </h2>
                            <div class="resource-cards">
                                <?php foreach ($grouped_resources['video'] as $resource): ?>
                                    <div class="resource-card video">
                                        <span class="resource-type-badge video"><i class="fas fa-video"></i> Video</span>
                                        <div class="resource-title"><?php echo $resource['title']; ?></div>
                                        <div class="resource-preview">
                                            <?php
                                            // Extract video ID from YouTube URL
                                            $videoId = '';
                                            $url = $resource['resource_url'];
                                            
                                            if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id)) {
                                                $videoId = $id[1];
                                            } else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id)) {
                                                $videoId = $id[1];
                                            } else if (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id)) {
                                                $videoId = $id[1];
                                            } else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id)) {
                                                $videoId = $id[1];
                                            }
                                            
                                            if ($videoId) {
                                                echo '<iframe src="https://www.youtube.com/embed/' . $videoId . '" allowfullscreen></iframe>';
                                            } else {
                                                echo '<div class="video-placeholder">
                                                        <i class="fas fa-video fa-3x"></i>
                                                      </div>';
                                            }
                                            ?>
                                        </div>
                                        <div class="resource-description"><?php echo $resource['description']; ?></div>
                                        <div class="resource-meta">
                                            <span><i class="fas fa-user"></i> <?php echo $resource['admin_name']; ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                                        </div>
                                        <div class="resource-action">
                                            <a href="<?php echo $resource['resource_url']; ?>" target="_blank" class="btn-access video">
                                                <i class="fas fa-play"></i> Watch Video
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Files Section -->
                    <?php if (!empty($grouped_resources['file'])): ?>
                        <div class="resource-section">
                            <h2 class="resource-section-title file">
                                <i class="fas fa-file"></i> Downloadable Files
                            </h2>
                            <div class="resource-cards">
                                <?php foreach ($grouped_resources['file'] as $resource): ?>
                                    <div class="resource-card file">
                                        <span class="resource-type-badge file"><i class="fas fa-file"></i> File</span>
                                        <div class="resource-title"><?php echo $resource['title']; ?></div>
                                        <div class="resource-description"><?php echo $resource['description']; ?></div>
                                        <div class="resource-meta">
                                            <span><i class="fas fa-user"></i> <?php echo $resource['admin_name']; ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                                        </div>
                                        <div class="resource-action">
                                            <a href="<?php echo $resource['resource_url']; ?>" download class="btn-access file">
                                                <i class="fas fa-download"></i> Download File
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Images Section -->
                    <?php if (!empty($grouped_resources['image'])): ?>
                        <div class="resource-section">
                            <h2 class="resource-section-title image">
                                <i class="fas fa-image"></i> Educational Images
                            </h2>
                            <div class="resource-cards">
                                <?php foreach ($grouped_resources['image'] as $resource): ?>
                                    <div class="resource-card image">
                                        <span class="resource-type-badge image"><i class="fas fa-image"></i> Image</span>
                                        <div class="resource-title"><?php echo $resource['title']; ?></div>
                                        <div class="resource-preview">
                                            <img src="<?php echo $resource['resource_url']; ?>" alt="<?php echo $resource['title']; ?>" class="image-thumbnail">
                                        </div>
                                        <div class="resource-description"><?php echo $resource['description']; ?></div>
                                        <div class="resource-meta">
                                            <span><i class="fas fa-user"></i> <?php echo $resource['admin_name']; ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                                        </div>
                                        <div class="resource-action">
                                            <a href="javascript:void(0);" class="btn-access image view-image" data-image="<?php echo $resource['resource_url']; ?>">
                                                <i class="fas fa-eye"></i> View Full Size
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Image Lightbox -->
    <div class="lightbox" id="imageLightbox">
        <span class="lightbox-close">&times;</span>
        <img id="lightboxImage" src="" alt="Full size image">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Image lightbox functionality
            const lightbox = document.getElementById('imageLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            const lightboxClose = document.querySelector('.lightbox-close');
            
            // Get all image view buttons
            const viewImageButtons = document.querySelectorAll('.view-image');
            
            viewImageButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const imageUrl = this.getAttribute('data-image');
                    lightboxImage.src = imageUrl;
                    lightbox.style.display = 'block';
                    
                    // Add active class after a small delay for animation
                    setTimeout(() => {
                        lightbox.classList.add('active');
                    }, 10);
                    
                    // Prevent scrolling while lightbox is open
                    document.body.style.overflow = 'hidden';
                });
            });
            
            // Close lightbox when clicking the close button
            lightboxClose.addEventListener('click', function() {
                lightbox.classList.remove('active');
                
                // Wait for animation to complete before hiding
                setTimeout(() => {
                    lightbox.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }, 300);
            });
            
            // Close lightbox when clicking outside the image
            lightbox.addEventListener('click', function(e) {
                if (e.target === lightbox) {
                    lightbox.classList.remove('active');
                    
                    // Wait for animation to complete before hiding
                    setTimeout(() => {
                        lightbox.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }, 300);
                }
            });
            
            // Add hover effect to resource cards
            const resourceCards = document.querySelectorAll('.resource-card');
            resourceCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px)';
                    this.style.boxShadow = '0 15px 30px rgba(0,0,0,0.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });
            
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                    
                    // Change icon based on sidebar state
                    const icon = this.querySelector('i');
                    if (sidebar.classList.contains('active')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
            }
        });
    </script>
</body>
</html> 
