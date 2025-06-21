<?php
// Move ini_set() calls before session_start()
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

// Now start the session
session_start();
require_once 'db.php'; // Your database connection file

// Adjusted cache headers to prevent caching while maintaining session
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Debug session (remove in production)
error_log("Session data: " . print_r($_SESSION, true));

// Handle fetching notifications via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch_notifications') {
    // Use a subquery to get the latest notification for each task_id
    $notifications_query = "
        SELECT n.id, n.project_id, n.task_id, n.message, n.uploaded_at, 
               p.name AS project_name, t.title AS task_title, fu.file_path, fu.drive_link, fu.employee_id
        FROM notifications n
        JOIN projects p ON n.project_id = p.id
        JOIN tasks t ON n.task_id = t.id
        LEFT JOIN file_uploads fu ON n.task_id = fu.task_id
        WHERE n.recipient_role = 'admin' AND n.is_read = FALSE
        AND n.id = (
            SELECT n2.id
            FROM notifications n2
            WHERE n2.task_id = n.task_id
            AND n2.recipient_role = 'admin' AND n2.is_read = FALSE
            ORDER BY n2.uploaded_at DESC
            LIMIT 1
        )
        ORDER BY n.uploaded_at DESC";
    $notifications_result = mysqli_query($conn, $notifications_query);

    $notifications = [];
    while ($notification = mysqli_fetch_assoc($notifications_result)) {
        $notifications[] = [
            'id' => $notification['id'],
            'project_name' => htmlspecialchars($notification['project_name']),
            'task_title' => htmlspecialchars($notification['task_title']),
            'task_id' => $notification['task_id'],
            'employee_id' => htmlspecialchars($notification['employee_id'] ?? 'N/A'),
            'message' => htmlspecialchars($notification['message']),
            'uploaded_at' => date('M d, Y H:i', strtotime($notification['uploaded_at'])),
            'file_path' => $notification['file_path'] ? htmlspecialchars($notification['file_path']) : null,
            'drive_link' => $notification['drive_link'] ? htmlspecialchars($notification['drive_link']) : null
        ];
    }

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['notifications' => $notifications]);
    exit();
}

// Handle project deletion
if (isset($_GET['delete_project'])) {
    $project_id = intval($_GET['delete_project']);
    
    // Start transaction
    mysqli_begin_transaction($conn);
    try {
        // Delete related records first (tasks, assignments, notifications, file_uploads)
        $delete_notifications = "DELETE FROM notifications WHERE project_id = ?";
        $stmt = mysqli_prepare($conn, $delete_notifications);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $delete_tasks = "DELETE FROM tasks WHERE project_id = ?";
        $stmt = mysqli_prepare($conn, $delete_tasks);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $delete_assignments = "DELETE FROM project_assignments WHERE project_id = ?";
        $stmt = mysqli_prepare($conn, $delete_assignments);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $delete_file_uploads = "DELETE FROM file_uploads WHERE task_id IN (SELECT id FROM tasks WHERE project_id = ?)";
        $stmt = mysqli_prepare($conn, $delete_file_uploads);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        // Delete the project
        $delete_project = "DELETE FROM projects WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_project);
        mysqli_stmt_bind_param($stmt, "i", $project_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        mysqli_commit($conn);
        header("Location: admin_dashboard.php");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Project deletion error: " . $e->getMessage());
        die("Error deleting project: " . $e->getMessage());
    }
}

// Handle notification actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['notification_id'])) {
    ob_start(); // Start output buffering
    $notification_id = intval($_POST['notification_id']);
    $action = $_POST['action'];
    $task_id = intval($_POST['task_id']);
    $employee_id = isset($_POST['employee_id']) && !empty(trim($_POST['employee_id'])) ? trim($_POST['employee_id']) : null;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Fetch task and project details
        $stmt = mysqli_prepare($conn, "SELECT project_id FROM tasks WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "i", $task_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Execute failed: " . mysqli_stmt_error($stmt));
        }
        $result = mysqli_stmt_get_result($stmt);
        $task = mysqli_fetch_assoc($result);
        if (!$task) {
            throw new Exception("Task not found for task_id: $task_id");
        }
        $project_id = $task['project_id'];
        mysqli_stmt_close($stmt);
        
        if ($action === 'approve') {
            // Update task status to completed
            $stmt = mysqli_prepare($conn, "UPDATE tasks SET status = 'completed', remarks = NULL WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "i", $task_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update task status: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            
            // Notify user
            $message = "Your completion of Task ID {$task_id} has been approved by admin.";
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (recipient_role, project_id, task_id, message, uploaded_at) VALUES ('user', ?, ?, ?, NOW())");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "iis", $project_id, $task_id, $message);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert user notification: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } elseif ($action === 'reject') {
            // Update task status to pending with remark
            $remark = "Task completion rejected by admin. Please revise and resubmit.";
            $stmt = mysqli_prepare($conn, "UPDATE tasks SET status = 'pending', remarks = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "si", $remark, $task_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update task status: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            
            // Notify user
            $message = "Your completion of Task ID {$task_id} was rejected by admin. Reason: {$remark}";
            $stmt = mysqli_prepare($conn, "INSERT INTO notifications (recipient_role, project_id, task_id, message, uploaded_at) VALUES ('user', ?, ?, ?, NOW())");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "iis", $project_id, $task_id, $message);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to insert user notification: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            throw new Exception("Invalid action: $action");
        }
        
        // Mark notification as read
        $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = TRUE WHERE id = ? AND recipient_role = 'admin'");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "i", $notification_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to mark notification as read: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        ob_end_clean();
        $error_message = $e->getMessage();
        error_log("Notification action error: " . $error_message);
        error_log("POST data: " . print_r($_POST, true));
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'error' => $error_message]);
    }
    exit();
}

// Fetch notifications with file details (updated to avoid GROUP BY issue)
$notifications_query = "
    SELECT n.id, n.project_id, n.task_id, n.message, n.uploaded_at, 
           p.name AS project_name, t.title AS task_title, fu.file_path, fu.drive_link, fu.employee_id
    FROM notifications n
    JOIN projects p ON n.project_id = p.id
    JOIN tasks t ON n.task_id = t.id
    LEFT JOIN file_uploads fu ON n.task_id = fu.task_id
    WHERE n.recipient_role = 'admin' AND n.is_read = FALSE
    AND n.id = (
        SELECT n2.id
        FROM notifications n2
        WHERE n2.task_id = n.task_id
        AND n2.recipient_role = 'admin' AND n2.is_read = FALSE
        ORDER BY n2.uploaded_at DESC
        LIMIT 1
    )
    ORDER BY n.uploaded_at DESC";
$notifications_result = mysqli_query($conn, $notifications_query);

// Fetch UGV projects
$ugv_query = "SELECT p.*, COUNT(DISTINCT pa.employee_id) as user_count 
              FROM projects p 
              LEFT JOIN project_assignments pa ON p.id = pa.project_id 
              WHERE p.category = 'UGV' 
              GROUP BY p.id 
              ORDER BY p.created_at DESC";
$ugv_result = mysqli_query($conn, $ugv_query);

// Fetch UAV projects
$uav_query = "SELECT p.*, COUNT(DISTINCT pa.employee_id) as user_count 
              FROM projects p 
              LEFT JOIN project_assignments pa ON p.id = pa.project_id 
              WHERE p.category = 'UAV' 
              GROUP BY p.id 
              ORDER BY p.created_at DESC";
$uav_result = mysqli_query($conn, $uav_query);

// Calculate dashboard stats
$total_projects = mysqli_num_rows($ugv_result) + mysqli_num_rows($uav_result);
$user_count_query = "SELECT COUNT(DISTINCT employee_id) as total_users FROM users WHERE role = 'user'";
$user_count_result = mysqli_query($conn, $user_count_query);
$total_users = mysqli_fetch_assoc($user_count_result)['total_users'];
$task_count_query = "SELECT COUNT(*) as total_tasks FROM tasks";
$task_count_result = mysqli_query($conn, $task_count_query);
$total_tasks = mysqli_fetch_assoc($task_count_result)['total_tasks'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Project Management</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/samarkan?styles=6066" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow: auto;
        }

        .main-content {
            margin-left: 280px;
            flex: 1 0 auto;
            background: var(--light);
            display: flex;
            flex-direction: column;
        }

        .content {
            flex: 1 0 auto;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg,rgb(126, 93, 44),rgb(233, 172, 91));
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
            background: rgba(255, 255, 255, 0.2);
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
            border: 2px solid #ef4444;
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

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-approve, .btn-reject {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gradient-3);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.4);
        }

        .notification-file {
            display: inline-block;
            color: #3b82f6;
            font-size: 0.8rem;
            text-decoration: none;
            margin-bottom: 0.5rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .notification-file:hover {
            background: #e0f2fe;
            text-decoration: underline;
        }

        .notification-empty {
            padding: 1.5rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }

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

        .stat-card.users::before { background: var(--gradient-2); }
        .stat-card.tasks::before { background: var(--gradient-3); }

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
        .stat-card.users .stat-icon { background: var(--gradient-2); }
        .stat-card.tasks .stat-icon { background: var(--gradient-3); }

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
            background: var(--gradient-1);
            border-radius: 50px;
            transition: width 1s ease;
            animation: progressAnimation 2s ease-out;
        }

        .stat-card.users .progress-fill { background: var(--gradient-2); }
        .stat-card.tasks .progress-fill { background: var(--gradient-3); }

        @keyframes progressAnimation {
            from { width: 0; }
        }

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

        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        .footer {
            flex-shrink: 0;
            text-align: center;
            padding-top: 1rem;
            color: #a0aec0;
            font-size: 0.875rem;
            cursor: pointer;
            margin-top: 26rem;
        }

        .footer:hover {
            color: var(--primary);
            text-decoration: underline;
        }

.credits-modal {
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

.credits-modal-content {
    background: var(--white);
    border-radius: 16px;
    width: 90%;
    max-width: 500px; /* Increased width for better layout */
    box-shadow: var(--shadow-lg);
    animation: slideUp 0.4s ease-out;
}

.credits-modal-header {
    padding: 1.5rem 2rem;
    background: var(--gradient-1);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 16px 16px 0 0;
}

.credits-modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: transparent;
    border: none;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.credits-modal-body {
    padding: 2rem;
    text-align: left; /* Changed from center to left for better readability */
}

.credits-modal-body h3 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
}

.credits-list {
    list-style: none;
    padding: 0;
    margin: 1.5rem 0;
}

.credits-list li {
    font-size: 1rem;
    color: var(--dark);
    margin-bottom: 0.8rem;
    padding: 0.8rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-weight: 500;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.credits-list li:last-child {
    border-bottom: none;
}

.role {
    color: #666;
    font-size: 0.85rem;
    font-style: italic;
    font-weight: 400;
}

.credits-modal-body p {
    margin-top: 1.5rem;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 8px;
    font-style: italic;
    color: #555;
    line-height: 1.5;
    text-align: center;
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
    
    .sidebar-toggle {
        display: block;
    }
    
    .credits-modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .credits-modal-header {
        padding: 1rem 1.5rem;
    }
    
    .credits-modal-body {
        padding: 1.5rem;
    }
    
    .credits-list li {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.3rem;
    }
}

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

.content-section {
    padding: 0 2rem;
}
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="assets/images/tihan_logo.webp" alt="TiHAN Logo">
                </div>
                <div>
                    <div style="font-size: 1.65rem; font-family: 'Samarkan', sans-serif; ">NIDHI</div>
                    <div style="font-size: 1.15rem;">Admin</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="#dashboard" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Team Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#projects" class="nav-link" data-section="projects">
                    <i class="fas fa-project-diagram"></i>
                    <span>Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_manage_users.php" class="nav-link" data-section="assign-project">
                    <i class="fas fa-users"></i>
                    <span>Assign Project</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_assign_task.php" class="nav-link" data-section="assign-task">
                    <i class="fas fa-tasks"></i>
                    <span>Assign Task</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_upload_history.php" class="nav-link" data-section="upload-history">
                    <i class="fas fa-history"></i>
                    <span>Upload History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_logout.php" class="nav-link" onclick="window.location.href='index.php'; return false;">
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
                <h1>Welcome, <?= htmlspecialchars($_SESSION['email']) ?>!</h1>
                <p>Manage your projects and tasks efficiently.</p>
            </div>
            <div class="header-right">
                <a href="admin_add_project.php" class="btn btn-primary">Add Project</a>
                <a href="admin_manage_users.php" class="btn btn-secondary">Manage Users</a>
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if (mysqli_num_rows($notifications_result) > 0): ?>
                        <span class="notification-count"><?= mysqli_num_rows($notifications_result) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notification Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <?php if (mysqli_num_rows($notifications_result) > 0): ?>
                <?php while ($notification = mysqli_fetch_assoc($notifications_result)): ?>
                    <div class="notification-item" data-notification-id="<?= $notification['id'] ?>">
                        <div class="notification-header">
                            <span class="notification-title">
                                <?= htmlspecialchars($notification['project_name']) ?> - <?= htmlspecialchars($notification['task_title']) ?>
                            </span>
                            <span class="notification-time">
                                <?= date('M d, Y H:i', strtotime($notification['uploaded_at'])) ?>
                            </span>
                        </div>
                        <div class="notification-message">
                            Employee ID: <?= htmlspecialchars($notification['employee_id'] ?? 'N/A') ?><br>
                            <?= htmlspecialchars($notification['message']) ?>
                        </div>
                        <?php if ($notification['drive_link']): ?>
                            <a href="<?= htmlspecialchars($notification['drive_link']) ?>" class="notification-file" target="_blank">View Drive Link</a>
                        <?php elseif ($notification['file_path']): ?>
                            <a href="<?= htmlspecialchars($notification['file_path']) ?>" class="notification-file" target="_blank">View Uploaded File</a>
                        <?php endif; ?>
                        <div class="notification-actions">
                            <button class="btn-approve" onclick="handleNotificationAction(<?= $notification['id'] ?>, 'approve', <?= $notification['task_id'] ?>)">Approve</button>
                            <button class="btn-reject" onclick="handleNotificationAction(<?= $notification['id'] ?>, 'reject', <?= $notification['task_id'] ?>)">Reject</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i><br>
                    No new notifications
                </div>
            <?php endif; ?>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section fade-in">
                <div class="stats-grid">
                    <div class="stat-card slide-up" onclick="navigateToSection('projects')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $total_projects ?></div>
                        <div class="stat-label">Total Projects</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card users slide-up" style="animation-delay: 0.1s;" onclick="navigateToSection('assign-project')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $total_users ?></div>
                        <div class="stat-label">Users</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card tasks slide-up" style="animation-delay: 0.2s;" onclick="navigateToSection('assign-task')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                        <div class="stat-number">Task</div>
                        <div class="stat-label">assignment</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects Section -->
            <div id="projects-section" class="content-section" style="display: none;">
                <div class="dashboard-main">
                    <div class="dashboard-grid">
                        <!-- UGV Section -->
                        <div class="project-section">
                            <div class="section-header ugv-header">
                                <h2><i class="fas fa-car"></i> Team Ground Vehicle</h2>
                                <span class="project-count"><?= mysqli_num_rows($ugv_result) ?> Projects</span>
                            </div>
                            <div class="projects-container">
                                <?php if (mysqli_num_rows($ugv_result) > 0): ?>
                                    <?php while($project = mysqli_fetch_assoc($ugv_result)): ?>
                                        <!-- Inside the projects-container loop for both UGV and UAV -->
<div class="project-card" onclick="viewProject(<?= $project['id'] ?>)">
    <div class="project-header">
        <h3><?= htmlspecialchars($project['name']) ?></h3>
        <span class="status-badge status-<?= strtolower($project['status']) ?>">
            <?= ucfirst($project['status']) ?>
        </span>
    </div>
    <div class="project-details">
        <p class="project-description">
            <?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...
        </p>
        <div class="project-meta">
            <span class="meta-item">
                <i class="fas fa-users"></i>
                <?= $project['user_count'] ?> Members
            </span>
            <span class="meta-item">
                <i class="fas fa-calendar"></i>
                Due: <?= date('M d, Y', strtotime($project['due_date'])) ?>
            </span>
        </div>
    </div>
    <div class="project-actions">
        <button class="btn-view" onclick="event.stopPropagation(); viewProject(<?= $project['id'] ?>)">
            <i class="fas fa-eye"></i> View Details
        </button>
        <button class="btn-edit" onclick="event.stopPropagation(); window.location.href='admin_edit_project.php?id=<?= $project['id'] ?>'">
            <i class="fas fa-edit"></i> Edit
        </button>
        <button class="btn-view" onclick="event.stopPropagation(); if(confirm('Are you sure you want to delete this project?')) window.location.href='admin_dashboard.php?delete_project=<?= $project['id'] ?>'">
            <i class="fas fa-trash"></i> Delete
        </button>
    </div>
</div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No projects found</p>
                                        <a href="admin_add_project.php?type=UGV" class="btn btn-primary">Add Project</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- UAV Section -->
                        <div class="project-section">
                            <div class="section-header uav-header">
                                <h2><i class="fas fa-helicopter"></i> Team Aerial Vehicle</h2>
                                <span class="project-count"><?= mysqli_num_rows($uav_result) ?> Projects</span>
                            </div>
                            <div class="projects-container">
                                <?php if (mysqli_num_rows($uav_result) > 0): ?>
                                    <?php while($project = mysqli_fetch_assoc($uav_result)): ?>
                                        <!-- Inside the projects-container loop for both UGV and UAV -->
<div class="project-card" onclick="viewProject(<?= $project['id'] ?>)">
    <div class="project-header">
        <h3><?= htmlspecialchars($project['name']) ?></h3>
        <span class="status-badge status-<?= strtolower($project['status']) ?>">
            <?= ucfirst($project['status']) ?>
        </span>
    </div>
    <div class="project-details">
        <p class="project-description">
            <?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...
        </p>
        <div class="project-meta">
            <span class="meta-item">
                <i class="fas fa-users"></i>
                <?= $project['user_count'] ?> Members
            </span>
            <span class="meta-item">
                <i class="fas fa-calendar"></i>
                Due: <?= date('M d, Y', strtotime($project['due_date'])) ?>
            </span>
        </div>
    </div>
    <div class="project-actions">
        <button class="btn-view" onclick="event.stopPropagation(); viewProject(<?= $project['id'] ?>)">
            <i class="fas fa-eye"></i> View Details
        </button>
        <button class="btn-edit" onclick="event.stopPropagation(); window.location.href='admin_edit_project.php?id=<?= $project['id'] ?>'">
            <i class="fas fa-edit"></i> Edit
        </button>
        <button class="btn-view" onclick="event.stopPropagation(); if(confirm('Are you sure you want to delete this project?')) window.location.href='admin_dashboard.php?delete_project=<?= $project['id'] ?>'">
            <i class="fas fa-trash"></i> Delete
        </button>
    </div>
</div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No projects found</p>
                                        <a href="admin_add_project.php?type=UAV" class="btn btn-primary">Add Project</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer" onclick="showCreditsModal()">
            © Copyright 2025 NMICPS TiHAN Foundation | All Rights Reserved
        </div>

        <!-- Credits Modal -->
        <div class="credits-modal" id="creditsModal">
            <div class="credits-modal-content">
                <div class="credits-modal-header">
                    <h2>Project Contributors</h2>
                    <button class="modal-close" onclick="closeCreditsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="credits-modal-body">
                    <h3>Project Contributors</h3>
                    <ul class="credits-list">
                        <li>Dr. P. Rajalakshmi <span class="role">Project Director</span></li>
                        <li>Dr. S. Syam Narayanan <span class="role">Hub Technical Officer</span></li>
                        <li>Sharon Zipporah Sebastian</li>
                        <li>Muhammed Nazim</li>
                    </ul>
                    <p>This project represents the collaborative efforts and professional excellence of our multidisciplinary team.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="admin_dashboard.js"></script>
    <script>
        function viewProject(project_id) {
            window.location.href = `admin_project_details.php?id=${project_id}`;
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            
            if (dropdown.classList.contains('active')) {
                fetchNotifications(); // Fetch latest notifications when opening dropdown
            }
        }

        function fetchNotifications() {
            fetch('admin_dashboard.php?action=fetch_notifications', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                const dropdown = document.getElementById('notificationDropdown');
                const countElement = document.querySelector('.notification-count');
                
                // Update notification dropdown
                if (data.notifications.length > 0) {
                    dropdown.innerHTML = data.notifications.map(notification => `
                        <div class="notification-item" data-notification-id="${notification.id}">
                            <div class="notification-header">
                                <span class="notification-title">
                                    ${notification.project_name} - ${notification.task_title}
                                </span>
                                <span class="notification-time">
                                    ${notification.uploaded_at}
                                </span>
                            </div>
                            <div class="notification-message">
                                Employee ID: ${notification.employee_id}<br>
                                ${notification.message}
                            </div>
                            ${notification.drive_link ? `<a href="${notification.drive_link}" class="notification-file" target="_blank">View Drive Link</a>` : 
                              notification.file_path ? `<a href="${notification.file_path}" class="notification-file" target="_blank">View Uploaded File</a>` : ''}
                            <div class="notification-actions">
                                <button class="btn-approve" onclick="handleNotificationAction(${notification.id}, 'approve', ${notification.task_id})">Approve</button>
                                <button class="btn-reject" onclick="handleNotificationAction(${notification.id}, 'reject', ${notification.task_id})">Reject</button>
                            </div>
                        </div>
                    `).join('');
                    // Update count
                    if (countElement) {
                        countElement.textContent = data.notifications.length;
                    } else if (data.notifications.length > 0) {
                        const icon = document.querySelector('.notification-icon');
                        icon.insertAdjacentHTML('beforeend', `<span class="notification-count">${data.notifications.length}</span>`);
                    }
                } else {
                    dropdown.innerHTML = `
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i><br>
                            No new notifications
                        </div>`;
                    countElement?.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
            });
        }

        // Poll for notifications every 30 seconds
        setInterval(fetchNotifications, 30000);

        // Initial fetch on page load
        document.addEventListener('DOMContentLoaded', fetchNotifications);

        function handleNotificationAction(notificationId, action, taskId) {
            if (!confirm(`Are you sure you want to ${action} this task completion?`)) return;
            const formData = new FormData();
            formData.append('action', action);
            formData.append('notification_id', notificationId);
            formData.append('task_id', taskId);

            fetch('admin_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const notificationItem = document.querySelector(`.notification-item[data-notification-id="${notificationId}"]`);
                    if (notificationItem) notificationItem.remove();
                    const countElement = document.querySelector('.notification-count');
                    let count = parseInt(countElement?.textContent || '0') - 1;
                    if (count > 0) {
                        countElement.textContent = count;
                    } else {
                        countElement?.remove();
                    }
                    const dropdown = document.getElementById('notificationDropdown');
                    if (!dropdown.querySelector('.notification-item')) {
                        dropdown.innerHTML = `
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i><br>
                                No new notifications
                            </div>`;
                    }
                } else {
                    console.error('Server error:', data.error);
                    alert('Error processing action: ' + (data.error || 'Unknown error occurred. Check console for details.'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error.message);
                alert('Error processing action: ' + error.message);
            });
        }

        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link');
            const sections = document.querySelectorAll('.content-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const section = this.getAttribute('data-section');
                    if (section === 'assign-project' || section === 'assign-task') {
                        // Allow default navigation for external pages
                        return;
                    }
                    
                    e.preventDefault();
                    
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Hide all sections
                    sections.forEach(section => section.style.display = 'none');
                    
                    // Show target section
                    const targetSection = section + '-section';
                    const target = document.getElementById(targetSection);
                    if (target) {
                        target.style.display = 'block';
                        target.classList.add('fade-in');
                    }
                });
            });
        });

        function navigateToSection(section) {
            const navLinks = document.querySelectorAll('.nav-link');
            const sections = document.querySelectorAll('.content-section');
            const targetLink = document.querySelector(`.nav-link[data-section="${section}"]`);
            
            if (section === 'assign-project') {
                window.location.href = 'admin_manage_users.php';
                return;
            } else if (section === 'assign-task') {
                window.location.href = 'admin_assign_task.php';
                return;
            }
            
            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));
            // Add active class to target link
            targetLink.classList.add('active');
            
            // Hide all sections
            sections.forEach(section => section.style.display = 'none');
            
            // Show target section
            const targetSection = section + '-section';
            const target = document.getElementById(targetSection);
            if (target) {
                target.style.display = 'block';
                target.classList.add('fade-in');
            }
        }

        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.style.transform = sidebar.style.transform === 'translateX(-100%)' ? 'translateX(0)' : 'translateX(-100%)';
        }

        // Credits modal functionality
    window.showCreditsModal = function() {
        document.getElementById('creditsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
        window.closeCreditsModal = function() {
            document.getElementById('creditsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
            
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const icon = document.querySelector('.notification-icon');
            if (!dropdown.contains(e.target) && !icon.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>