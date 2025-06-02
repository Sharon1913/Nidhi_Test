<?php
// Ensure session persistence
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'user' || !isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "tihan_project_management");
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error, 0);
    die("Connection failed: " . $conn->connect_error);
}

$employee_id = $_SESSION['employee_id'];

// Handle marking a single notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_single_notification_read' && isset($_POST['notification_id'])) {
    ob_start(); // Start output buffering
    $notification_id = intval($_POST['notification_id']);
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND recipient_role = 'user'");
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read: ' . $stmt->error]);
    }
    
    $stmt->close();
    exit();
}


$conn = new mysqli("localhost", "root", "", "tihan_project_management");
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error, 0); // Log the error
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];
$employee_id = $_SESSION['employee_id'];

// Fetch user profile
$stmt = $conn->prepare("SELECT first_name, last_name, profile_picture, is_profile_updated FROM users WHERE employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$user_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Initialize profile variables and username
$first_name = $user_profile['first_name'] ?? '';
$last_name = $user_profile['last_name'] ?? '';
$profile_picture = $user_profile['profile_picture'] ?? '';
$is_profile_updated = $user_profile['is_profile_updated'] ?? 0;
$username = str_replace(' ', '_', strtolower($first_name . '_' . $last_name));

// Handle profile update
$profile_error = $profile_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Handle profile picture upload
    $new_profile_picture = $profile_picture;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['jpg', 'jpeg', 'png'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_types)) {
            $profile_error = "Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $profile_error = "Profile picture size exceeds 2MB limit.";
        } else {
            $target_dir = "Uploads/profile_pictures/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $new_profile_picture = $target_dir . time() . "_" . $username . "_" . basename($file["name"]);
            if (!move_uploaded_file($file["tmp_name"], $new_profile_picture)) {
                $profile_error = "Error uploading profile picture.";
                $new_profile_picture = $profile_picture;
            }
        }
    }
    
    if (!$profile_error) {
        // Prepare the update query with proper binding
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_picture = ?, is_profile_updated = 1 WHERE employee_id = ? AND email = ?");
        $stmt->bind_param("sssss", $first_name, $last_name, $new_profile_picture, $employee_id, $email);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $profile_success = "Profile updated successfully.";
                $profile_picture = $new_profile_picture;
                $is_profile_updated = 1;
                $username = str_replace(' ', '_', strtolower($first_name . '_' . $last_name));
            } else {
                $profile_error = "No profile updated. Please check if your employee ID and email are correct.";
            }
        } else {
            $profile_error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle password change
$password_error = $password_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE employee_id = ?");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stored_password = $result['password'];
    $stmt->close();
    
    if (!password_verify($current_password, $stored_password)) {
        $password_error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "New password must be at least 8 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE employee_id = ?");
        $stmt->bind_param("ss", $hashed_password, $employee_id);
        if ($stmt->execute()) {
            $password_success = "Password changed successfully.";
        } else {
            $password_error = "Failed to change password.";
        }
        $stmt->close();
    }
}

$task_filter = isset($_GET['task_filter']) ? $_GET['task_filter'] : 'all';
$task_query = "SELECT t.*, p.name as project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.employee_id = ?";
if ($task_filter !== 'all') {
    $task_query .= " AND t.status = ?";
}
$stmt = $conn->prepare($task_query);
if ($task_filter === 'all') {
    $stmt->bind_param("s", $employee_id);
} else {
    $stmt->bind_param("ss", $employee_id, $task_filter);
}
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

// Handle file upload with status update
// Handle file upload with status update
$upload_error = $upload_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['file'])) {
    $task_id = $_POST['task_id'];
    $upload_type = $_POST['upload_type'];
    $task_status = $_POST['task_status'];
    $file_description = $upload_type === 'other' ? trim($_POST['file_description']) : null;
    $file = $_FILES['file'];
    $max_size = in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['mp4', 'mov', 'avi']) ? 50 * 1024 * 1024 : 10 * 1024 * 1024;

    if ($file['size'] > $max_size) {
        $upload_error = "File size exceeds limit (" . ($max_size / (1024 * 1024)) . "MB).";
    } else {
        $target_dir = "Uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $target_file = $target_dir . time() . "_" . $username . "_" . basename($file["name"]);
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO file_uploads (task_id, employee_id, file_path, upload_type, file_description, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issss", $task_id, $employee_id, $target_file, $upload_type, $file_description);
            if ($stmt->execute()) {
                $upload_success = "File uploaded successfully.";

                // Update task status
                $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $task_status, $task_id);
                $stmt->execute();

                // Create notification for admin if task is marked as completed
                if ($task_status == 'completed') {
                    $stmt = $conn->prepare("SELECT project_id FROM tasks WHERE id = ?");
                    $stmt->bind_param("i", $task_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $project_id = $result['project_id'];

                    $message = "Task ID {$task_id} marked as completed by {$employee_id}. Please verify.";
                    $stmt = $conn->prepare("INSERT INTO notifications (recipient_role, project_id, task_id, message, uploaded_at) VALUES ('admin', ?, ?, ?, NOW())");
                    $stmt->bind_param("iis", $project_id, $task_id, $message);
                    $stmt->execute();
                }
                $stmt->close();
            } else {
                $upload_error = "Failed to upload file to database.";
            }
        } else {
            $upload_error = "Error moving uploaded file to server.";
        }
    

    }
}


// Fetch uploaded files history
$stmt = $conn->prepare("SELECT fu.*, t.title as task_title, p.name as project_name 
                        FROM file_uploads fu 
                        JOIN tasks t ON fu.task_id = t.id 
                        JOIN projects p ON t.project_id = p.id 
                        WHERE fu.employee_id = ? 
                        ORDER BY fu.uploaded_at DESC");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$upload_history = $stmt->get_result();
$stmt->close();

// Fetch projects and tasks
$stmt = $conn->prepare("SELECT p.* FROM projects p JOIN project_assignments pa ON p.id = pa.project_id WHERE pa.employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$projects = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT t.*, p.name as project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT t.*, p.name as project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

// Fetch notifications for the user
$stmt = $conn->prepare("
    SELECT n.id, n.project_id, n.task_id, n.message, n.uploaded_at, 
           p.name AS project_name, t.title AS task_title
    FROM notifications n
    JOIN projects p ON n.project_id = p.id
    JOIN tasks t ON n.task_id = t.id
    WHERE n.recipient_role = 'user' AND t.employee_id = ? AND n.is_read = FALSE
    ORDER BY n.uploaded_at DESC");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$notifications_result = $stmt->get_result();
$stmt->close();

$total_tasks = 0;
$completed_tasks = 0;
$pending_tasks = 0;
$delayed_tasks = 0;
$today = date('Y-m-d');

$tasks->data_seek(0);
while ($task = $tasks->fetch_assoc()) {
    $total_tasks++;
    if ($task['status'] == 'completed') {
        $completed_tasks++;
    } elseif ($task['due_date'] < $today && $task['status'] != 'completed') {
        $delayed_tasks++;
        $stmt = $conn->prepare("UPDATE tasks SET status = 'delayed' WHERE id = ?");
        $stmt->bind_param("i", $task['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $pending_tasks++;
    }
}

$remarks_error = $remarks_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remarks'])) {
    $task_id = $_POST['task_id'];
    $remarks = trim($_POST['remarks']);
    $stmt = $conn->prepare("UPDATE tasks SET remarks = ?, status = 'delayed' WHERE id = ? AND employee_id = ?");
    $stmt->bind_param("sis", $remarks, $task_id, $employee_id);
    if ($stmt->execute()) {
        $remarks_success = "Remarks submitted successfully.";
    } else {
        $remarks_error = "Failed to submit remarks.";
    }
    $stmt->close();
}

// Fetch uploaded files history
// Fetch uploaded files history
$stmt = $conn->prepare("
    SELECT fu.*, t.title as task_title, p.name as project_name 
    FROM file_uploads fu 
    JOIN tasks t ON fu.task_id = t.id 
    JOIN projects p ON t.project_id = p.id 
    WHERE fu.employee_id = ? AND t.status = 'completed'
    ORDER BY fu.uploaded_at DESC");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$upload_history = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TiHAN Project Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f1f5f9;
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --white: #ffffff;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: var(--dark);
            line-height: 1.6;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--gradient-1);
            padding: 2rem 0;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 2rem;
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .logo img {
            width: 100px;
            height: 50px;
            object-fit: contain;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px;
        }
        .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .nav-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            font-size: 1.2rem;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: var(--light);
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .header-left p {
            color: var(--gray);
            font-size: 0.875rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--gradient-2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.2rem;
            color: #f1be02;
            padding: 0.5rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .notification-icon:hover {
            background: rgba(252, 252, 255, 0.2);
            transform: scale(1.1);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            width: 350px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: #f9fafb;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .notification-message {
            font-size: 0.85rem;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }

        .notification-empty {
            padding: 1.5rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-1);
        }

        .stat-card.success::before { background: var(--gradient-4); }
        .stat-card.warning::before { background: var(--gradient-2); }
        .stat-card.danger::before { background: linear-gradient(135deg, #ff6b6b, #ee5a52); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-card .stat-icon { background: var(--gradient-1); }
        .stat-card.success .stat-icon { background: var(--gradient-4); }
        .stat-card.warning .stat-icon { background: var(--gradient-2); }
        .stat-card.danger .stat-icon { background: linear-gradient(135deg, #ff6b6b, #ee5a52); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        .stat-label {
            color: var(--gray);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 0 0 1px #e2e8f0;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .modern-table thead {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        }

        .modern-table th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            border-bottom: 2px solid #e2e8f0;
        }

        .modern-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
            color: var(--gray);
            transition: all 0.3s ease;
        }

        .modern-table tbody tr {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .modern-table tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.01);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-delayed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-danger {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Form Styles */
        .form-section {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .form-header {
            background: var(--gradient-1);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .form-header i {
            font-size: 1.25rem;
        }

        .form-body {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            color: var(--dark);
            background: white;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.3s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 500;
            animation: slideIn 0.5s ease-out;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Progress Bars */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 50px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient-4);
            border-radius: 50px;
            transition: width 1s ease;
            animation: progressAnimation 2s ease-out;
        }

        @keyframes progressAnimation {
            from { width: 0; }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading States */
        .loading {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* File Upload Styles */
        .file-upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .file-upload-area:hover,
        .file-upload-area.dragover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .file-upload-icon {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .file-upload-area:hover .file-upload-icon {
            color: var(--primary);
            transform: scale(1.1);
        }

        .file-upload-text {
            color: var(--gray);
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .file-upload-subtext {
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Mobile Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.4s ease-out;
        }

        .modal-header {
            padding: 1.5rem 2rem;
            background: var(--gradient-1);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 16px 16px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            transform: scale(1.1);
        }

        .modal-body {
            padding: 2rem;
        }

        .profile-picture-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .profile-picture:hover {
            transform: scale(1.05);
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray);
            transition: all 0.3s ease;
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                max-height: 95vh;
            }
            
            .profile-picture {
                width: 100px;
                height: 100px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                padding: 1rem;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .tab.active {
                border-bottom: 2px solid var(--primary);
            }
        }
    </style>
</head>
<body <?php if (!$is_profile_updated) echo 'onload="showProfileModal()"'; ?>>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="assets/images/tihan_logo.webp" alt="TiHAN Logo">
                </div>
                <div>
                    <div style="font-size: 1.25rem;">TiHAN - NIDHI</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="#dashboard" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#projects" class="nav-link" data-section="projects">
                    <i class="fas fa-project-diagram"></i>
                    <span>My Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#tasks" class="nav-link" data-section="tasks">
                    <i class="fas fa-tasks"></i>
                    <span>My Tasks</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#uploads" class="nav-link" data-section="uploads">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Upload Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#remarks" class="nav-link" data-section="remarks">
                    <i class="fas fa-comment-alt"></i>
                    <span>Add Remarks</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#history" class="nav-link" data-section="history">
                    <i class="fas fa-history"></i>
                    <span>Upload History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user_logout.php" class="nav-link" onclick="window.location.href='index.php'; return false;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Welcome<?php echo $first_name ? ', ' . htmlspecialchars($first_name) : ''; ?>!</h1>
                <p>Here's what's happening with your projects today.</p>
            </div>
            <div class="header-right">
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <span class="notification-count"><?php echo $notifications_result->num_rows; ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-avatar" onclick="showProfileModal()">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($email, 0, 2)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notification Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <?php if (mysqli_num_rows($notifications_result) > 0): ?>
                <?php while ($notification = mysqli_fetch_assoc($notifications_result)): ?>
                    <div class="notification-item">
                        <div class="notification-header">
                            <span class="notification-title">
                                <?= htmlspecialchars($notification['project_name']) ?> - <?= htmlspecialchars($notification['task_title']) ?>
                            </span>
                            <span class="notification-time">
                                <?= date('M d, Y H:i', strtotime($notification['uploaded_at'])) ?>
                            </span>
                        </div>
                        <div class="notification-message">
                            <?= htmlspecialchars($notification['message']) ?>
                        </div>
                        <button class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="markNotificationAsRead(<?php echo $notification['id']; ?>, this)">
                            OK
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i><br>
                    No new notifications
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Modal -->
        <div class="modal" id="profileModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php echo $is_profile_updated ? 'Your Profile' : 'Complete Your Profile'; ?></h2>
                    <button class="modal-close" onclick="closeProfileModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if ($profile_success) { ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $profile_success; ?>
                        </div>
                    <?php } ?>
                    <?php if ($profile_error) { ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $profile_error; ?>
                        </div>
                    <?php } ?>
                    <?php if ($password_success) { ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $password_success; ?>
                        </div>
                    <?php } ?>
                    <?php if ($password_error) { ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $password_error; ?>
                        </div>
                    <?php } ?>
                    
                    <div class="profile-picture-container">
                        <?php if ($profile_picture): ?>
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-picture">
                        <?php else: ?>
                            <div class="profile-picture" style="background: var(--gradient-2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                <?php echo strtoupper(substr($email, 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        <button class="btn-primary" onclick="document.getElementById('profilePictureInput').click()">Change Picture</button>
                    </div>
                    
                    <div class="tabs">
                        <div class="tab active" data-tab="profile">Profile</div>
                        <div class="tab" data-tab="password">Change Password</div>
                    </div>
                    
                    <div class="tab-content active" id="profile-tab">
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="file" name="profile_picture" id="profilePictureInput" style="display: none;" accept=".jpg,.jpeg,.png">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Employee ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee_id); ?>" disabled>
                            </div>
                            <button type="submit" class="btn-primary" id="profileBtn">
                                <span class="btn-text">
                                    <i class="fas fa-save"></i>
                                    Save Profile
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Saving...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                    
                    <div class="tab-content" id="password-tab">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn-primary" id="passwordBtn">
                                <span class="btn-text">
                                    <i class="fas fa-lock"></i>
                                    Change Password
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Changing...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section fade-in">
            <div class="stats-grid">
    <div class="stat-card slide-up" onclick="showTasks('all')">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-tasks"></i>
            </div>
        </div>
        <div class="stat-number"><?php echo $total_tasks; ?></div>
        <div class="stat-label">Total Tasks</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: 100%;"></div>
        </div>
    </div>
    
    <div class="stat-card success slide-up" style="animation-delay: 0.1s;" onclick="showCompletedUploads()">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="stat-number"><?php echo $completed_tasks; ?></div>
        <div class="stat-label">Completed</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0; ?>%;"></div>
        </div>
    </div>
    
    <div class="stat-card warning slide-up" style="animation-delay: 0.2s;" onclick="showTasks('pending')">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
        <div class="stat-number"><?php echo $pending_tasks; ?></div>
        <div class="stat-label">Pending</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($pending_tasks / $total_tasks) * 100 : 0; ?>%;"></div>
        </div>
    </div>
    
    <div class="stat-card danger slide-up" style="animation-delay: 0.3s;" onclick="showTasks('delayed')">
        <div class="stat-header">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
        <div class="stat-number"><?php echo $delayed_tasks; ?></div>
        <div class="stat-label">Delayed</div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($delayed_tasks / $total_tasks) * 100 : 0; ?>%; background: linear-gradient(135deg, #ff6b6b, #ee5a52);"></div>
        </div>
    </div>
</div>
            </div>

            <!-- Projects Section -->
            <div id="projects-section" class="content-section" style="display: none;">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-project-diagram"></i>
                            My Projects
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($projects->num_rows > 0) { ?>
                            <div class="table-container">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Project Name</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $projects->data_seek(0);
                                        while ($project = $projects->fetch_assoc()) {
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($project['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($project['category']); ?></td>
                                                <td><span class="status-badge status-pending">Active</span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-project-diagram" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <p>No projects assigned yet.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Tasks Section -->
            <div id="tasks-section" class="content-section" style="display: none;">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-tasks"></i>
                            My Tasks<?php if ($task_filter !== 'all') { echo ' - ' . ucfirst($task_filter); } ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($tasks->num_rows > 0) { ?>
                            <div class="table-container">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Task Title</th>
                                            <th>Assigned Date</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $tasks->data_seek(0);
                                        while ($task = $tasks->fetch_assoc()) {
                                            $status_class = 'status-pending';
                                            if ($task['status'] == 'completed') {
                                                $status_class = 'status-completed';
                                            } elseif ($task['status'] == 'delayed') {
                                                $status_class = 'status-delayed';
                                            }
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($task['project_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($task['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($task['remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-tasks" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <p>No <?php echo $task_filter === 'all' ? 'tasks' : $task_filter . ' tasks'; ?> assigned yet.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div id="uploads-section" class="content-section" style="display: none;">
                <div class="form-section fade-in">
                    <div class="form-header">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Upload Report</h3>
                    </div>
                    <div class="form-body">
                        <?php if ($upload_success) { ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $upload_success; ?>
                            </div>
                        <?php } ?>
                        <?php if ($upload_error) { ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $upload_error; ?>
                            </div>
                        <?php } ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Select Task</label>
                                    <select name="task_id" class="form-control" required>
                                        <option value="">Choose a task...</option>
                                        <?php
                                        $tasks->data_seek(0);
                                        while ($task = $tasks->fetch_assoc()) {
                                            echo "<option value='{$task['id']}'>" . htmlspecialchars($task['title']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Task Status</label>
                                    <select name="task_status" class="form-control" required>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Report Type</label>
                                    <select name="upload_type" id="upload_type" class="form-control" required>
                                        <option value="weekly_report">Weekly Report</option>
                                        <option value="monthly_report">Monthly Report</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="file_description_group" style="display: none;">
                                    <label class="form-label">File Description</label>
                                    <input type="text" name="file_description" id="file_description" class="form-control" placeholder="Specify file type/purpose">
                                </div>
                            </div>
                            
                            <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">Click to upload or drag and drop</div>
                                <div class="file-upload-subtext">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, PNG, JPG, JPEG, MP4, MOV, AVI (Max 10MB, 50MB for videos)</div>
                                <input type="file" name="file" id="fileInput" style="display: none;" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.mp4,.mov,.avi">
                            </div>
                            
                            <button type="submit" class="btn-primary" id="uploadBtn">
                                <span class="btn-text">
                                    <i class="fas fa-upload"></i>
                                    Upload Report
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Uploading...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Remarks Section -->
            <div id="remarks-section" class="content-section" style="display: none;">
                <div class="form-section fade-in">
                    <div class="form-header">
                        <i class="fas fa-comment-alt"></i>
                        <h3>Add Remarks for Delay</h3>
                    </div>
                    <div class="form-body">
                        <?php if ($remarks_success) { ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $remarks_success; ?>
                            </div>
                        <?php } ?>
                        <?php if ($remarks_error) { ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $remarks_error; ?>
                            </div>
                        <?php } ?>
                        
                        <form method="POST" id="remarksForm">
                            <div class="form-group">
                                <label class="form-label">Select Task</label>
                                <select name="task_id" class="form-control" required>
                                    <option value="">Choose a task...</option>
                                    <?php
                                    $tasks->data_seek(0);
                                    while ($task = $tasks->fetch_assoc()) {
                                        echo "<option value='{$task['id']}'>" . htmlspecialchars($task['title']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Reason for Delay</label>
                                <textarea name="remarks" class="form-control" rows="5" placeholder="Please explain the reason for the delay..." required style="resize: vertical; min-height: 120px;"></textarea>
                            </div>
                            
                            <button type="submit" class="btn-primary" id="remarksBtn">
                                <span class="btn-text">
                                    <i class="fas fa-paper-plane"></i>
                                    Submit Remarks
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Submitting...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Upload History Section -->
            <div id="history-section" class="content-section" style="display: none;">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            Upload History
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($upload_history->num_rows > 0) { ?>
                            <div class="table-container">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Task</th>
                                            <th>File Name</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Upload Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($upload = $upload_history->fetch_assoc()) { ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($upload['project_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($upload['task_title']); ?></td>
                                                <td><?php echo htmlspecialchars(basename($upload['file_path'])); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $upload['upload_type'])); ?></td>
                                                <td><?php echo htmlspecialchars($upload['file_description'] ?? '-'); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($upload['uploaded_at'])); ?></td>
                                                <td><a href="<?php echo htmlspecialchars($upload['file_path']); ?>" class="btn-view" style="padding: 0.5rem 1rem;" download>Download</a></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <p>No files uploaded yet.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>

        // Notification functionality
window.toggleNotifications = function() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('active');
    
    // Mark all notifications as viewed when dropdown is opened (optional, can remove if only using OK button)
    if (dropdown.classList.contains('active')) {
        fetch('user_dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_viewed'
        });
    }
};

window.markNotificationAsRead = function(notificationId, button) {
    fetch('user_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=mark_single_notification_read&notification_id=${notificationId}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Remove notification from UI
                    const notificationItem = document.querySelector(`.notification-item`);
                    if (notificationItem) notificationItem.remove();

            
            // Update notification count
                    const countElement = document.querySelector('.notification-count');
                    let count = parseInt(countElement?.textContent || '0') - 1;
                    if (count > 0) {
                        countElement.textContent = count;
                    } else {
                        countElement?.remove();
                    }
                    
                    // Show empty state if no notifications left
                    const dropdown = document.getElementById('notificationDropdown');
                    if (!dropdown.querySelector('.notification-item')) {
                dropdown.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i><br>
                        No new notifications
                    </div>
                `;
                    }
                    // Close the dropdown
            dropdown.classList.remove('active');
                } else {
                    alert('Error processing action. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing action. Please try again.');
            });
        }
        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const sections = document.querySelectorAll('.content-section');

            function showSection(sectionId) {
                // Hide all sections
                sections.forEach(section => section.style.display = 'none');
                // Remove active class from all links
                navLinks.forEach(link => link.classList.remove('active'));
                
                // Show target section
                const targetSection = document.getElementById(sectionId + '-section');
                if (targetSection) {
                    targetSection.style.display = 'block';
                    targetSection.classList.add('fade-in');
                }
                
                // Activate corresponding nav link
                const targetLink = document.querySelector(`.nav-link[data-section="${sectionId}"]`);
                if (targetLink) {
                    targetLink.classList.add('active');
                }
            }
// Navigation click handler
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');
            showSection(sectionId);
            // Reset task filter when navigating via sidebar
            if (sectionId === 'tasks') {
                renderTasks('all');
            }
        });
    });

    // Function to render tasks with filter
    window.renderTasks = function(filter) {
        const tbody = document.getElementById('tasks-table-body');
        const filterLabel = document.getElementById('task-filter-label');
        if (!tbody || !window.allTasks) return;

        // Update filter label
        filterLabel.textContent = filter === 'all' ? '' : ` - ${filter.charAt(0).toUpperCase() + filter.slice(1)}`;

        // Filter tasks
        const filteredTasks = filter === 'all' ? window.allTasks : window.allTasks.filter(task => task.status === filter);
        
        // Clear table
        tbody.innerHTML = '';

        if (filteredTasks.length > 0) {
            filteredTasks.forEach(task => {
                const statusClass = task.status === 'completed' ? 'status-completed' :
                                 task.status === 'delayed' ? 'status-delayed' : 'status-pending';
                const row = `
                    <tr>
                        <td><strong>${task.project_name}</strong></td>
                        <td>${task.title}</td>
                        <td>${task.assigned_date}</td>
                        <td>${task.due_date}</td>
                        <td><span class="status-badge ${statusClass}">${task.status.charAt(0).toUpperCase() + task.status.slice(1)}</span></td>
                        <td>${task.remarks}</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        } else {
            tbody.parentNode.parentNode.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: var(--gray);">
                    <i class="fas fa-tasks" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>No ${filter === 'all' ? 'tasks' : filter + ' tasks'} assigned yet.</p>
                </div>
            `;
        }
    };

// Function to show tasks with filter
    window.showTasks = function(filter) {
        showSection('tasks');
        renderTasks(filter);
    };

    // Function to show completed uploads
    window.showCompletedUploads = function() {
        showSection('history');
    };

    // Initial render of tasks if tasks section is active
    if (document.getElementById('tasks-section').style.display === 'block') {
        renderTasks('all');
    }
            
            // File upload functionality
            const uploadType = document.getElementById('upload_type');
            const fileDescriptionGroup = document.getElementById('file_description_group');
            const fileDescription = document.getElementById('file_description');
            
            uploadType.addEventListener('change', function() {
                if (this.value === 'other') {
                    fileDescriptionGroup.style.display = 'block';
                    fileDescription.required = true;
                } else {
                    fileDescriptionGroup.style.display = 'none';
                    fileDescription.required = false;
                }
            });
            
            // File input change handler
            const fileInput = document.getElementById('fileInput');
            fileInput.addEventListener('change', function() {
                const fileName = this.files[0]?.name;
                if (fileName) {
                    document.querySelector('.file-upload-text').textContent = fileName;
                    document.querySelector('.file-upload-icon').innerHTML = '<i class="fas fa-file"></i>';
                }
            });
            
            // Form submission handlers
            document.getElementById('uploadForm').addEventListener('submit', function(e) {
                const btn = document.getElementById('uploadBtn');
                const btnText = btn.querySelector('.btn-text');
                const loading = btn.querySelector('.loading');
                
                btnText.style.display = 'none';
                loading.style.display = 'flex';
                btn.disabled = true;
            });
            
            document.getElementById('remarksForm').addEventListener('submit', function(e) {
                const btn = document.getElementById('remarksBtn');
                const btnText = btn.querySelector('.btn-text');
                const loading = btn.querySelector('.loading');
                
                btnText.style.display = 'none';
                loading.style.display = 'flex';
                btn.disabled = true;
            });
            
            // Drag and drop file upload
            const uploadArea = document.querySelector('.file-upload-area');
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    uploadArea.classList.add('dragover');
                });
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    uploadArea.classList.remove('dragover');
                });
            });
            
            uploadArea.addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    const fileName = files[0].name;
                    document.querySelector('.file-upload-text').textContent = fileName;
                    document.querySelector('.file-upload-icon').innerHTML = '<i class="fas fa-file"></i>';
                }
            });

            // Profile JavaScript
            window.showProfileModal = function() {
                const modal = document.getElementById('profileModal');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.closeProfileModal = function() {
                const modal = document.getElementById('profileModal');
                modal.style.display = 'none';
                document.body.style.overflow = '';
            };

            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(this.getAttribute('data-tab') + '-tab').classList.add('active');
                });
            });

            const profilePictureInput = document.getElementById('profilePictureInput');
            profilePictureInput.addEventListener('change', function() {
                if (this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.querySelector('.profile-picture');
                        if (img.tagName === 'IMG') {
                            img.src = e.target.result;
                        } else {
                            img.style.display = 'none';
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.className = 'profile-picture';
                            img.parentNode.insertBefore(newImg, img);
                        }
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });

            document.getElementById('profileForm').addEventListener('submit', function(e) {
                const btn = document.getElementById('profileBtn');
                const btnText = btn.querySelector('.btn-text');
                const loading = btn.querySelector('.loading');
                btnText.style.display = 'none';
                loading.style.display = 'flex';
                btn.disabled = true;
            });

            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                const btn = document.getElementById('passwordBtn');
                const btnText = btn.querySelector('.btn-text');
                const loading = btn.querySelector('.loading');
                btnText.style.display = 'none';
                loading.style.display = 'flex';
                btn.disabled = true;
            });
            
            // Sidebar toggle for mobile
            window.toggleSidebar = function() {
                const sidebar = document.getElementById('sidebar');
                sidebar.style.transform = sidebar.style.transform === 'translateX(-100%)' ? 'translateX(0)' : 'translateX(-100%)';
            };



// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notificationDropdown');
    const icon = document.querySelector('.notification-icon');
    if (!dropdown.contains(e.target) && !icon.contains(e.target)) {
        dropdown.classList.remove('active');
        }
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?> 