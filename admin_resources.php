<?php
include 'connection.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle form submission for adding/editing resources
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_resource'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $resource_type = $_POST['resource_type'];
        $resource_url = '';
        
        // Handle file uploads
        if ($resource_type === 'file' || $resource_type === 'image') {
            if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === 0) {
                $upload_dir = 'uploads/resources/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['resource_file']['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $upload_path)) {
                    $resource_url = $upload_path;
                } else {
                    $error_message = "Failed to upload file. Please try again.";
                }
            } else {
                $error_message = "Please select a file to upload.";
            }
        } else {
            // For links and videos, just store the URL
            $resource_url = $_POST['resource_url'];
        }
        
        if (!isset($error_message)) {
            $admin_id = $_SESSION['user_id'];
            
            $query = "INSERT INTO resources (title, description, resource_type, resource_url, created_by, is_active) 
                      VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $title, $description, $resource_type, $resource_url, $admin_id);
            
            if ($stmt->execute()) {
                $success_message = "Resource added successfully!";
            } else {
                $error_message = "Error adding resource: " . $conn->error;
            }
        }
    } elseif (isset($_POST['edit_resource'])) {
        $resource_id = $_POST['resource_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $resource_type = $_POST['resource_type'];
        $current_url = $_POST['current_url'];
        $resource_url = $current_url;
        
        // Handle file upload for edit if a new file is provided
        if (($resource_type === 'file' || $resource_type === 'image') && isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === 0) {
            $upload_dir = 'uploads/resources/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['resource_file']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $upload_path)) {
                // Delete the old file if it exists and is within our uploads directory
                if (strpos($current_url, 'uploads/resources/') === 0 && file_exists($current_url)) {
                    unlink($current_url);
                }
                $resource_url = $upload_path;
            } else {
                $error_message = "Failed to upload new file. Please try again.";
            }
        } elseif ($resource_type === 'link' || $resource_type === 'video') {
            // For links and videos, update the URL
            $resource_url = $_POST['resource_url'];
        }
        
        if (!isset($error_message)) {
            $query = "UPDATE resources SET title = ?, description = ?, resource_type = ?, resource_url = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $title, $description, $resource_type, $resource_url, $resource_id);
            
            if ($stmt->execute()) {
                $success_message = "Resource updated successfully!";
            } else {
                $error_message = "Error updating resource: " . $conn->error;
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $resource_id = $_POST['resource_id'];
        $is_active = $_POST['is_active'] ? 0 : 1; // Toggle the status
        
        $query = "UPDATE resources SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $is_active, $resource_id);
        
        if ($stmt->execute()) {
            $status_message = $is_active ? "Resource activated successfully!" : "Resource deactivated successfully!";
        } else {
            $error_message = "Error updating resource status: " . $conn->error;
        }
    } elseif (isset($_POST['delete_resource'])) {
        $resource_id = $_POST['resource_id'];
        $current_url = $_POST['current_url'];
        
        // First, check if this is a file we need to delete
        if (strpos($current_url, 'uploads/resources/') === 0 && file_exists($current_url)) {
            unlink($current_url);
        }
        
        $query = "DELETE FROM resources WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $resource_id);
        
        if ($stmt->execute()) {
            $success_message = "Resource deleted successfully!";
        } else {
            $error_message = "Error deleting resource: " . $conn->error;
        }
    }
}

// Check if we're editing a resource
$editing = false;
$edit_resource = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $resource_id = $_GET['edit'];
    $query = "SELECT * FROM resources WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $editing = true;
        $edit_resource = $result->fetch_assoc();
    }
}

// Fetch all resources
$query = "SELECT r.*, CONCAT(a.firstname, ' ', a.lastname) as admin_name 
          FROM resources r
          LEFT JOIN users a ON r.created_by = a.idno
          ORDER BY r.created_at DESC";
$result = $conn->query($query);
$resources = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $resources[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources Management</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary-color: #4cc9f0;
            --accent-color: #f72585;
            --text-color: #2c3e50;
            --text-light: #7f8c8d;
            --bg-color: #f8f9fa;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            --card-radius: 10px;
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
        }
        
        /* Containers */
        .container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 0;
        }
        
        /* Content Wrapper */
        .content-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* Alert Container */
        .alerts-container {
            margin-bottom: 20px;
        }
        
        /* Section Styling */
        .section-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background-color: #4361ee;
            color: white;
            border-radius: 8px 8px 0 0;
            margin: 0;
        }
        
        .section-header h2 {
            margin: 0;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .section-header h2 i {
            margin-right: 10px;
            color: white;
        }
        
        .form-section, .resources-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            height: fit-content;
            width: 100%;
        }
        
        .form-content, .resources-content {
            padding: 20px;
        }
        
        /* Form Styles */
        .resource-form {
            padding: 0;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #f9f9f9;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            background-color: white;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-submit {
            background-color: #4361ee;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            background-color: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(67, 97, 238, 0.3);
        }
        
        .btn-cancel {
            background: linear-gradient(to right, #95a5a6, #7f8c8d);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(127, 140, 141, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-cancel:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(127, 140, 141, 0.3);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.5s ease;
            width: 100%;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            border-left: 5px solid #2ecc71;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            color: #c0392b;
            border-left: 5px solid #e74c3c;
        }
        
        /* Conditional Field Styles */
        .conditional-field {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* File Upload Styles */
        .file-upload-wrapper {
            position: relative;
            width: 100%;
            height: 40px;
        }
        
        .file-upload-wrapper:after {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            background: #f9f9f9;
            padding: 8px 15px;
            display: block;
            width: calc(100% - 40px);
            pointer-events: none;
            z-index: 20;
            height: 40px;
            line-height: 24px;
            color: #999;
            border-radius: 8px 0 0 8px;
            font-weight: 300;
            border: 1px solid #e1e1e1;
            border-right: none;
            font-size: 0.9rem;
        }
        
        .file-upload-wrapper:before {
            content: 'Upload';
            position: absolute;
            top: 0;
            right: 0;
            display: inline-block;
            height: 40px;
            background: linear-gradient(to right, var(--primary-color), var(--primary-light));
            color: #fff;
            font-weight: 600;
            z-index: 25;
            font-size: 14px;
            line-height: 40px;
            padding: 0 15px;
            text-transform: uppercase;
            pointer-events: none;
            border-radius: 0 8px 8px 0;
        }
        
        .file-upload-wrapper:hover:before {
            background: linear-gradient(to right, var(--primary-light), var(--primary-color));
        }
        
        .file-upload-wrapper input {
            opacity: 0;
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 99;
            height: 40px;
            margin: 0;
            padding: 0;
            display: block;
            cursor: pointer;
            width: 100%;
        }
        
        .current-file-info {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        
        .current-file-info p {
            margin: 0 0 10px 0;
            font-weight: 600;
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .file-preview img, .url-preview {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #f1f1f1;
            transition: all 0.3s ease;
        }
        
        .file-preview img:hover, .url-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .file-preview i {
            font-size: 2rem;
        }
        
        .file-preview a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .file-note {
            margin-top: 10px !important;
            font-size: 0.85rem !important;
            color: var(--text-light) !important;
            font-weight: normal !important;
        }
        
        /* Table Styles */
        .resources-table-container {
            overflow-x: auto;
        }
        
        .resources-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .resources-table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            color: #495057;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .resources-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
            font-size: 0.9rem;
            background-color: white;
        }
        
        .resources-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .resources-table tbody tr:hover td {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .resources-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .inactive-row td {
            background-color: rgba(236, 240, 241, 0.5) !important;
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Resource Title & Preview */
        .resource-title-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .title-text {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .preview-badge {
            padding: 3px 8px;
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
        }
        
        .preview-badge:hover {
            background-color: rgba(52, 152, 219, 0.2);
        }
        
        .preview-badge.video-preview {
            background-color: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .preview-badge.video-preview:hover {
            background-color: rgba(231, 76, 60, 0.2);
        }
        
        /* Resource Type Badge */
        .resource-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        
        .resource-type-badge i {
            margin-right: 5px;
        }
        
        .resource-type-badge.link {
            background: linear-gradient(to right, #3498db, #2980b9);
        }
        
        .resource-type-badge.video {
            background: linear-gradient(to right, #e74c3c, #c0392b);
        }
        
        .resource-type-badge.file {
            background: linear-gradient(to right, #f39c12, #d35400);
        }
        
        .resource-type-badge.image {
            background: linear-gradient(to right, #1abc9c, #16a085);
        }
        
        /* Description Styles */
        .resource-description-cell {
            max-width: 200px;
            line-height: 1.5;
            position: relative;
        }
        
        .short-desc, .full-desc {
            color: var(--text-color);
        }
        
        .toggle-desc {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 0.8rem;
            padding: 5px 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }
        
        .toggle-desc:hover {
            color: var(--primary-dark);
        }
        
        /* Resource Metadata */
        .resource-meta {
            font-size: 0.8rem;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .resource-meta div {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            width: 80px;
        }
        
        .status-badge.active {
            background-color: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }
        
        .status-badge.hidden {
            background-color: rgba(149, 165, 166, 0.2);
            color: #7f8c8d;
        }
        
        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .btn-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 0.8rem;
        }
        
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-view {
            background: linear-gradient(to bottom right, #3498db, #2980b9);
        }
        
        .btn-edit {
            background: linear-gradient(to bottom right, #f39c12, #d35400);
        }
        
        .btn-show {
            background: linear-gradient(to bottom right, #2ecc71, #27ae60);
        }
        
        .btn-hide {
            background: linear-gradient(to bottom right, #95a5a6, #7f8c8d);
        }
        
        .btn-delete {
            background: linear-gradient(to bottom right, #e74c3c, #c0392b);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: var(--card-radius);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 20px;
            display: block;
        }
        
        .empty-state p {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 10px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.show {
            opacity: 1;
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        
        .modal.show .modal-content {
            transform: scale(1);
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--text-color);
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: #95a5a6;
            cursor: pointer;
            z-index: 10;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            color: #e74c3c;
            background: white;
            transform: rotate(90deg);
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        /* File type icons coloring */
        .file-type-icon.pdf { color: #e74c3c; }
        .file-type-icon.doc { color: #3498db; }
        .file-type-icon.xls { color: #2ecc71; }
        .file-type-icon.ppt { color: #f39c12; }
        .file-type-icon.zip { color: #9b59b6; }
        .file-type-icon.img { color: #1abc9c; }
        
        /* Responsive Adjustments */
        @media (max-width: 1200px) {
            .content-wrapper {
                grid-template-columns: 40% 60%;
                gap: 15px;
            }
        }
        
        @media (max-width: 992px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
            
            .form-section {
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 0;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .alert {
                margin-top: 15px;
            }
            
            nav ul li a {
                padding: 15px 10px;
                font-size: 0.9rem;
            }
            
            nav ul li a i {
                margin-right: 5px;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .table-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Sit-in Monitoring</h2>
            <p>Admin Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="computer_control.php"><i class="fas fa-desktop"></i> Computer Control</a></li>
            <li><a href="manage_sit_in_requests.php"><i class="fas fa-tasks"></i> Manage Requests</a></li>
            <li><a href="todays_sit_in_records.php"><i class="fas fa-calendar-day"></i> Today's Records</a></li>
            <li><a href="active_sitin.php"><i class="fas fa-user-clock"></i> Active Sit-ins</a></li>
            <li><a href="add_subject.php"><i class="fas fa-book"></i> Add Subject</a></li>
            <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="lab_schedules_admin.php"><i class="fas fa-calendar-alt"></i> Lab Schedules</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Search</a></li>
            <li><a href="register_sitin.php"><i class="fas fa-sign-in-alt"></i> Register Sit-in</a></li>
            <li><a href="admin_students.php"><i class="fas fa-users"></i> Students</a></li>
            <li><a href="admin_resources.php" class="active"><i class="fas fa-book-open"></i> Resources</a></li>
        </ul>
        <div class="logout-button">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Main Title -->
        <div class="main-title">
            <h1>Resources Management</h1>
        </div>
        
        <!-- Page Content -->
        <div class="container">
            <!-- Alert messages -->
            <?php if (isset($success_message) || isset($error_message)): ?>
                <div class="alerts-container">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="content-wrapper">
                <!-- Add New Resource Form (now on top) -->
                <div class="form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-plus-circle"></i> Add New Resource</h2>
                    </div>
                    
                    <div class="form-content">
                        <div class="resource-form">
                            <form method="post" enctype="multipart/form-data">
                                <?php if ($editing): ?>
                                    <input type="hidden" name="resource_id" value="<?php echo $edit_resource['id']; ?>">
                                    <input type="hidden" name="current_url" value="<?php echo $edit_resource['resource_url']; ?>">
                                <?php endif; ?>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="title"><i class="fas fa-heading"></i> Title:</label>
                                        <input type="text" id="title" name="title" class="form-control" value="<?php echo $editing ? htmlspecialchars($edit_resource['title']) : ''; ?>" required placeholder="Enter resource title...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="resource_type"><i class="fas fa-tag"></i> Resource Type:</label>
                                        <select id="resource_type" name="resource_type" class="form-control" required>
                                            <option value="">-- Select Resource Type --</option>
                                            <option value="link" <?php echo ($editing && $edit_resource['resource_type'] === 'link') ? 'selected' : ''; ?>>External Link</option>
                                            <option value="video" <?php echo ($editing && $edit_resource['resource_type'] === 'video') ? 'selected' : ''; ?>>Video (YouTube)</option>
                                            <option value="file" <?php echo ($editing && $edit_resource['resource_type'] === 'file') ? 'selected' : ''; ?>>Downloadable File</option>
                                            <option value="image" <?php echo ($editing && $edit_resource['resource_type'] === 'image') ? 'selected' : ''; ?>>Image Resource</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group" style="width: 100%;">
                                        <label for="description"><i class="fas fa-align-left"></i> Description:</label>
                                        <textarea id="description" name="description" class="form-control" required placeholder="Provide a detailed description of this resource..." style="height: 80px;"><?php echo $editing ? htmlspecialchars($edit_resource['description']) : ''; ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <!-- Resource URL field for links/videos -->
                                    <div id="resourceUrlGroup" class="form-group conditional-field" style="width: 100%;">
                                        <label for="resource_url"><i id="urlIcon" class="fas fa-link"></i> <span id="urlLabel">Resource URL:</span></label>
                                        <div class="url-input-container">
                                            <input type="url" id="resource_url" name="resource_url" class="form-control" value="<?php echo ($editing && ($edit_resource['resource_type'] === 'link' || $edit_resource['resource_type'] === 'video')) ? htmlspecialchars($edit_resource['resource_url']) : ''; ?>" placeholder="Enter the full URL (including https://)">
                                            
                                            <?php if ($editing && $edit_resource['resource_type'] === 'video'): ?>
                                                <div>
                                                    <i class="fab fa-youtube" style="font-size: 24px; color: #e74c3c;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <small id="urlHelp" class="form-text" style="margin-top: 5px; display: none; color: var(--text-light);"></small>
                                    </div>
                                    
                                    <!-- File upload field for files/images -->
                                    <div id="resourceFileGroup" class="form-group conditional-field" style="width: 100%;">
                                        <label for="resource_file"><i id="fileIcon" class="fas fa-file"></i> <span id="fileLabel">Upload File:</span></label>
                                        
                                        <div class="file-upload-wrapper" data-text="Select your file!">
                                            <input type="file" id="resource_file" name="resource_file" class="form-control">
                                        </div>
                                        
                                        <?php if ($editing && ($edit_resource['resource_type'] === 'file' || $edit_resource['resource_type'] === 'image')): ?>
                                            <div class="current-file-info">
                                                <p>Current file:</p>
                                                <?php if ($edit_resource['resource_type'] === 'image'): ?>
                                                    <div class="file-preview">
                                                        <img src="<?php echo htmlspecialchars($edit_resource['resource_url']); ?>" alt="Resource thumbnail" class="url-preview">
                                                        <span><?php echo basename($edit_resource['resource_url']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="file-preview">
                                                        <?php
                                                            $extension = pathinfo($edit_resource['resource_url'], PATHINFO_EXTENSION);
                                                            $icon_class = 'far fa-file';
                                                            $icon_color = '';
                                                            
                                                            if (in_array($extension, ['pdf'])) {
                                                                $icon_class = 'far fa-file-pdf file-type-icon pdf';
                                                            } else if (in_array($extension, ['doc', 'docx'])) {
                                                                $icon_class = 'far fa-file-word file-type-icon doc';
                                                            } else if (in_array($extension, ['xls', 'xlsx', 'csv'])) {
                                                                $icon_class = 'far fa-file-excel file-type-icon xls';
                                                            } else if (in_array($extension, ['ppt', 'pptx'])) {
                                                                $icon_class = 'far fa-file-powerpoint file-type-icon ppt';
                                                            } else if (in_array($extension, ['zip', 'rar', '7z'])) {
                                                                $icon_class = 'far fa-file-archive file-type-icon zip';
                                                            } else if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                                $icon_class = 'far fa-file-image file-type-icon img';
                                                            }
                                                        ?>
                                                        <i class="<?php echo $icon_class; ?>"></i>
                                                        <a href="<?php echo htmlspecialchars($edit_resource['resource_url']); ?>" target="_blank">
                                                            <?php echo basename($edit_resource['resource_url']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <p class="file-note">Upload a new file only if you want to replace the current one.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="<?php echo $editing ? 'edit_resource' : 'add_resource'; ?>" class="btn-submit">
                                        <i class="fas <?php echo $editing ? 'fa-save' : 'fa-plus-circle'; ?>"></i> <?php echo $editing ? 'Update Resource' : 'Add Resource'; ?>
                                    </button>
                                    
                                    <?php if ($editing): ?>
                                        <a href="admin_resources.php" class="btn-cancel">
                                            <i class="fas fa-times"></i> Cancel Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Resources List -->
                <div class="resources-section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> Resources List</h2>
                    </div>
                    
                    <div class="resources-content">
                        <?php if (empty($resources)): ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <p>No resources have been added yet.</p>
                                <p>Use the form to add your first resource.</p>
                            </div>
                        <?php else: ?>
                            <div class="resources-table-container">
                                <table class="resources-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Added By</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resources as $index => $resource): ?>
                                            <tr class="<?php echo $resource['is_active'] ? '' : 'inactive-row'; ?>">
                                                <td class="text-center"><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="resource-title-cell">
                                                        <span class="title-text"><?php echo htmlspecialchars($resource['title']); ?></span>
                                                        
                                                        <?php if ($resource['resource_type'] === 'image'): ?>
                                                            <span class="preview-badge" data-preview="<?php echo htmlspecialchars($resource['resource_url']); ?>">
                                                                <i class="fas fa-image"></i> Preview
                                                            </span>
                                                        <?php elseif ($resource['resource_type'] === 'video'): ?>
                                                            <?php
                                                            // Extract video ID for preview
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
                                                            
                                                            if ($videoId): 
                                                            ?>
                                                                <span class="preview-badge video-preview" data-video="<?php echo $videoId; ?>">
                                                                    <i class="fab fa-youtube"></i> Preview
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="resource-type-badge <?php echo $resource['resource_type']; ?>">
                                                        <?php 
                                                            $icon = '';
                                                            switch ($resource['resource_type']) {
                                                                case 'link':
                                                                    $icon = '<i class="fas fa-link"></i>';
                                                                    break;
                                                                case 'video':
                                                                    $icon = '<i class="fas fa-video"></i>';
                                                                    break;
                                                                case 'file':
                                                                    $icon = '<i class="fas fa-file"></i>';
                                                                    break;
                                                                case 'image':
                                                                    $icon = '<i class="fas fa-image"></i>';
                                                                    break;
                                                            }
                                                            echo $icon . ' ' . ucfirst($resource['resource_type']); 
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="resource-description-cell">
                                                        <?php 
                                                            // Show truncated description with toggle
                                                            $description = htmlspecialchars($resource['description']);
                                                            $short_desc = strlen($description) > 100 ? 
                                                                substr($description, 0, 100) . '...' : 
                                                                $description;
                                                            
                                                            echo '<div class="short-desc">' . $short_desc . '</div>';
                                                            
                                                            if (strlen($description) > 100) {
                                                                echo '<div class="full-desc" style="display:none;">' . nl2br($description) . '</div>';
                                                                echo '<button class="toggle-desc" data-state="short">
                                                                    <span class="show-more"><i class="fas fa-chevron-down"></i> Show more</span>
                                                                    <span class="show-less" style="display:none;"><i class="fas fa-chevron-up"></i> Show less</span>
                                                                </button>';
                                                            }
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="resource-meta">
                                                        <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($resource['admin_name']); ?></div>
                                                        <div><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($resource['is_active']): ?>
                                                        <span class="status-badge active">
                                                            <i class="fas fa-eye"></i> Visible
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="status-badge hidden">
                                                            <i class="fas fa-eye-slash"></i> Hidden
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="table-actions">
                                                        <a href="<?php echo htmlspecialchars($resource['resource_url']); ?>" target="_blank" class="btn-icon btn-view" title="View Resource">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                        
                                                        <a href="admin_resources.php?edit=<?php echo $resource['id']; ?>" class="btn-icon btn-edit" title="Edit Resource">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <form method="post" class="d-inline" style="display:inline;">
                                                            <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                            <input type="hidden" name="is_active" value="<?php echo $resource['is_active']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn-icon <?php echo $resource['is_active'] ? 'btn-hide' : 'btn-show'; ?>" title="<?php echo $resource['is_active'] ? 'Hide Resource' : 'Show Resource'; ?>">
                                                                <i class="fas fa-<?php echo $resource['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <button type="button" class="btn-icon btn-delete delete-confirm" data-id="<?php echo $resource['id']; ?>" data-url="<?php echo htmlspecialchars($resource['resource_url']); ?>" title="Delete Resource">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modals -->
    <div id="imagePreviewModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h3>Image Preview</h3>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="" alt="Resource preview" style="max-width: 100%; max-height: 500px;">
            </div>
        </div>
    </div>

    <div id="videoPreviewModal" class="modal">
        <div class="modal-content" style="width: 80%; max-width: 800px;">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h3>Video Preview</h3>
            </div>
            <div class="modal-body">
                <div class="video-container">
                    <iframe id="videoFrame" width="100%" height="450" src="" frameborder="0" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <span class="modal-close">&times;</span>
            <div class="modal-body text-center">
                <div style="font-size: 60px; color: #e74c3c; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Confirm Deletion</h3>
                <p style="margin-bottom: 25px; color: #7f8c8d;">Are you sure you want to delete this resource? This action cannot be undone.</p>
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button type="button" id="cancelDelete" class="btn btn-secondary">Cancel</button>
                    <button type="button" id="confirmDelete" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resourceTypeSelect = document.getElementById('resource_type');
            const resourceUrlGroup = document.getElementById('resourceUrlGroup');
            const resourceFileGroup = document.getElementById('resourceFileGroup');
            const urlIcon = document.getElementById('urlIcon');
            const urlLabel = document.getElementById('urlLabel');
            const fileIcon = document.getElementById('fileIcon');
            const fileLabel = document.getElementById('fileLabel');
            const urlHelp = document.getElementById('urlHelp');
            
            // Function to show/hide form fields based on resource type
            function toggleFormFields() {
                const resourceType = resourceTypeSelect.value;
                
                if (resourceType === 'link' || resourceType === 'video') {
                    resourceUrlGroup.style.display = 'block';
                    resourceFileGroup.style.display = 'none';
                    
                    // Update icon and label based on type
                    if (resourceType === 'link') {
                        urlIcon.className = 'fas fa-link';
                        urlLabel.textContent = 'Website URL:';
                        urlHelp.textContent = 'Enter the full URL including https://';
                        urlHelp.style.display = 'block';
                    } else if (resourceType === 'video') {
                        urlIcon.className = 'fab fa-youtube';
                        urlLabel.textContent = 'YouTube Video URL:';
                        urlHelp.textContent = 'Enter the YouTube video URL (supports youtube.com and youtu.be links)';
                        urlHelp.style.display = 'block';
                    }
                } else if (resourceType === 'file' || resourceType === 'image') {
                    resourceUrlGroup.style.display = 'none';
                    resourceFileGroup.style.display = 'block';
                    
                    // Update icon and label based on type
                    if (resourceType === 'file') {
                        fileIcon.className = 'fas fa-file';
                        fileLabel.textContent = 'Upload Document:';
                    } else if (resourceType === 'image') {
                        fileIcon.className = 'fas fa-image';
                        fileLabel.textContent = 'Upload Image:';
                    }
                } else {
                    resourceUrlGroup.style.display = 'none';
                    resourceFileGroup.style.display = 'none';
                }
            }
            
            // Set initial state
            toggleFormFields();
            
            // Add event listener for changes
            resourceTypeSelect.addEventListener('change', toggleFormFields);
            
            // Custom file input
            const fileInput = document.getElementById('resource_file');
            const fileWrapper = document.querySelector('.file-upload-wrapper');
            
            if (fileInput && fileWrapper) {
                fileInput.addEventListener('change', function(e) {
                    let fileName = '';
                    
                    if (this.files && this.files.length > 0) {
                        fileName = e.target.value.split('\\').pop();
                    }
                    
                    if (fileName) {
                        fileWrapper.setAttribute('data-text', fileName);
                    } else {
                        fileWrapper.setAttribute('data-text', 'Select your file!');
                    }
                });
            }
            
            // Modal handling
            const imagePreviewModal = document.getElementById('imagePreviewModal');
            const videoPreviewModal = document.getElementById('videoPreviewModal');
            const deleteModal = document.getElementById('deleteModal');
            const previewImage = document.getElementById('previewImage');
            const videoFrame = document.getElementById('videoFrame');
            
            // Show modals
            function showModal(modal) {
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
                document.body.style.overflow = 'hidden';
            }
            
            // Hide modals
            function hideModal(modal) {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }, 300);
                
                // Reset video src to stop playback when closing
                if (modal === videoPreviewModal) {
                    videoFrame.src = '';
                }
            }
            
            // Close modal when clicking X or outside
            document.querySelectorAll('.modal-close').forEach(close => {
                close.addEventListener('click', function() {
                    hideModal(this.closest('.modal'));
                });
            });
            
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideModal(this);
                    }
                });
            });
            
            // Image preview
            document.querySelectorAll('.preview-badge:not(.video-preview)').forEach(badge => {
                badge.addEventListener('click', function() {
                    const imageUrl = this.getAttribute('data-preview');
                    previewImage.src = imageUrl;
                    showModal(imagePreviewModal);
                });
            });
            
            // Video preview
            document.querySelectorAll('.video-preview').forEach(badge => {
                badge.addEventListener('click', function() {
                    const videoId = this.getAttribute('data-video');
                    videoFrame.src = `https://www.youtube.com/embed/${videoId}`;
                    showModal(videoPreviewModal);
                });
            });
            
            // Toggle description
            document.querySelectorAll('.toggle-desc').forEach(button => {
                button.addEventListener('click', function() {
                    const state = this.getAttribute('data-state');
                    const parent = this.parentNode;
                    const shortDesc = parent.querySelector('.short-desc');
                    const fullDesc = parent.querySelector('.full-desc');
                    const showMore = this.querySelector('.show-more');
                    const showLess = this.querySelector('.show-less');
                    
                    if (state === 'short') {
                        shortDesc.style.display = 'none';
                        fullDesc.style.display = 'block';
                        showMore.style.display = 'none';
                        showLess.style.display = 'inline-flex';
                        this.setAttribute('data-state', 'full');
                    } else {
                        shortDesc.style.display = 'block';
                        fullDesc.style.display = 'none';
                        showMore.style.display = 'inline-flex';
                        showLess.style.display = 'none';
                        this.setAttribute('data-state', 'short');
                    }
                });
            });
            
            // Delete confirmation
            let resourceToDelete = null;
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            const cancelDeleteBtn = document.getElementById('cancelDelete');
            
            document.querySelectorAll('.delete-confirm').forEach(button => {
                button.addEventListener('click', function() {
                    resourceToDelete = {
                        id: this.getAttribute('data-id'),
                        url: this.getAttribute('data-url')
                    };
                    showModal(deleteModal);
                });
            });
            
            // Handle deletion
            confirmDeleteBtn.addEventListener('click', function() {
                if (resourceToDelete) {
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.style.display = 'none';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'resource_id';
                    idInput.value = resourceToDelete.id;
                    
                    const urlInput = document.createElement('input');
                    urlInput.type = 'hidden';
                    urlInput.name = 'current_url';
                    urlInput.value = resourceToDelete.url;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_resource';
                    actionInput.value = '1';
                    
                    form.appendChild(idInput);
                    form.appendChild(urlInput);
                    form.appendChild(actionInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
            
            cancelDeleteBtn.addEventListener('click', function() {
                hideModal(deleteModal);
            });
            
            // Animate alert messages
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(alert => {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-10px)';
                        alert.style.transition = 'all 0.5s ease';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 500);
                    });
                }, 5000);
            }
            
            // Smooth scrolling for edit mode
            if (window.location.href.includes('edit=')) {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>
</html> 
