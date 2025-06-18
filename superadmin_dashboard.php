<?php
session_start();
require_once 'db.php'; // Your database connection file

// Debug: Log session data to verify it's active
if (!isset($_SESSION['debug'])) {
    $_SESSION['debug'] = [];
}
$_SESSION['debug'][] = [
    'time' => date('Y-m-d H:i:s'),
    'page' => 'superadmin_dashboard.php',
    'session_role' => $_SESSION['role'] ?? 'not set',
    'session_id' => session_id()
];

// Check if user is logged in and is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    error_log("Session check failed in superadmin_dashboard.php: role=" . ($_SESSION['role'] ?? 'not set'));
    header("Location: index.php?error=unauthorized");
    exit();
}

// Handle user/admin creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user_admin'])) {
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $employee_id = trim($_POST['employee_id']);
    
    // Validate role
    if (!in_array($role, ['admin', 'user'])) {
        $error = "Invalid role selected.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO users (email, password, role, employee_id, is_active) VALUES (?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "ssss", $email, $password, $role, $employee_id);
        if ($stmt->execute()) {
            $message = ucfirst($role) . " added successfully.";
        } else {
            $error = "Failed to add " . $role . ". Email or Employee ID may already exist.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle admin deletion
if (isset($_GET['delete_admin'])) {
    $employee_id = $_GET['delete_admin'];
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE employee_id = ? AND role = 'admin'");
    mysqli_stmt_bind_param($stmt, "s", $employee_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: superadmin_dashboard.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete_user'])) {
    $employee_id = $_GET['delete_user'];
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE employee_id = ? AND role = 'user'");
    mysqli_stmt_bind_param($stmt, "s", $employee_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: superadmin_dashboard.php");
    exit();
}

// Handle user to admin conversion
if (isset($_GET['convert_to_admin'])) {
    $employee_id = $_GET['convert_to_admin'];
    $stmt = mysqli_prepare($conn, "UPDATE users SET role = 'admin' WHERE employee_id = ? AND role = 'user'");
    mysqli_stmt_bind_param($stmt, "s", $employee_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: superadmin_dashboard.php");
    exit();
}

// Handle admin block/suspend
if (isset($_GET['block_admin'])) {
    $employee_id = $_GET['block_admin'];
    $stmt = mysqli_prepare($conn, "UPDATE users SET is_active = 0 WHERE employee_id = ? AND role = 'admin'");
    mysqli_stmt_bind_param($stmt, "s", $employee_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: superadmin_dashboard.php");
    exit();
}

// Handle admin revive
if (isset($_GET['revive_admin'])) {
    $employee_id = $_GET['revive_admin'];
    $stmt = mysqli_prepare($conn, "UPDATE users SET is_active = 1 WHERE employee_id = ? AND role = 'admin'");
    mysqli_stmt_bind_param($stmt, "s", $employee_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: superadmin_dashboard.php");
    exit();
}

<<<<<<< HEAD
=======
// Handle password reset for admin or user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $employee_id = $_POST['employee_id'];
    $new_password = password_hash(trim($_POST['new_password']), PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE employee_id = ? AND role IN ('admin', 'user')");
    mysqli_stmt_bind_param($stmt, "ss", $new_password, $employee_id);
    if (mysqli_stmt_execute($stmt)) {
        $reset_message = "Password for Employee ID $employee_id has been reset successfully.";
    } else {
        $reset_error = "Failed to reset password for Employee ID $employee_id.";
    }
    mysqli_stmt_close($stmt);
}

>>>>>>> origin/rel-code
// Handle project deletion
if (isset($_GET['delete_project'])) {
    $project_id = intval($_GET['delete_project']);
    
    // Delete related tasks
    $delete_tasks = "DELETE FROM tasks WHERE project_id = ?";
    $stmt = mysqli_prepare($conn, $delete_tasks);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete related project assignments
    $delete_assignments = "DELETE FROM project_assignments WHERE project_id = ?";
    $stmt = mysqli_prepare($conn, $delete_assignments);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete related notifications
    $delete_notifications = "DELETE FROM notifications WHERE project_id = ?";
    $stmt = mysqli_prepare($conn, $delete_notifications);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete related file uploads
    $delete_file_uploads = "DELETE FROM file_uploads WHERE task_id IN (SELECT id FROM tasks WHERE project_id = ?)";
    $stmt = mysqli_prepare($conn, $delete_file_uploads);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Delete related project user assignments
    $delete_project_user_assignments = "DELETE FROM project_user_assignments WHERE project_id = ?";
    $stmt = mysqli_prepare($conn, $delete_project_user_assignments);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Now delete the project
    $delete_project = "DELETE FROM projects WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_project);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header("Location: superadmin_dashboard.php");
    exit();
}

// Handle notification actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $action = $_POST['action'];
    $task_id = intval($_POST['task_id']);
    $employee_id = $_POST['employee_id'];
    
    $stmt = mysqli_prepare($conn, "SELECT project_id FROM tasks WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $task = mysqli_fetch_assoc($result);
    $project_id = $task['project_id'];
    mysqli_stmt_close($stmt);
    
    if ($action === 'approve') {
        $stmt = mysqli_prepare($conn, "UPDATE tasks SET status = 'completed', remarks = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $task_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $message = "Your completion of Task ID {$task_id} has been approved by superadmin.";
        $stmt = mysqli_prepare($conn, "INSERT INTO notifications (recipient_role, project_id, task_id, message, uploaded_at) VALUES ('user', ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iis", $project_id, $task_id, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'reject') {
        $remark = "Task completion rejected by superadmin. Please revise and resubmit.";
        $stmt = mysqli_prepare($conn, "UPDATE tasks SET status = 'pending', remarks = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $remark, $task_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $message = "Your completion of Task ID {$task_id} was rejected by superadmin. Reason: {$remark}";
        $stmt = mysqli_prepare($conn, "INSERT INTO notifications (recipient_role, project_id, task_id, message, uploaded_at) VALUES ('user', ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iis", $project_id, $task_id, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = TRUE WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit();
}

// Handle project and team assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_project_team'])) {
    $project_id = intval($_POST['project_id']);
    $admin_id = $_POST['admin_id'];
    $user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    
    // Assign project to admin
    $stmt = mysqli_prepare($conn, "INSERT INTO project_assignments (project_id, employee_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "is", $project_id, $admin_id);
    $project_assigned = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Assign team members
    if (empty($project_id) || empty($admin_id)) {
        $team_error = "Please select project and admin.";
    } else {
        $success_count = 0;
        if (!empty($user_ids)) {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO project_user_assignments (project_id, admin_id, user_id, assigned_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "iss", $project_id, $admin_id, $user_id);
            
            foreach ($user_ids as $user_id) {
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $success_count++;
                    // Notify user
                    $stmt_notify = mysqli_prepare($conn, "SELECT name FROM projects WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_notify, "i", $project_id);
                    mysqli_stmt_execute($stmt_notify);
                    $result = mysqli_stmt_get_result($stmt_notify);
                    $project_name = mysqli_fetch_assoc($result)['name'];
                    mysqli_stmt_close($stmt_notify);
                    
                    $message = "You have been assigned to project: $project_name under admin ID: $admin_id";
                    $stmt_notify = mysqli_prepare($conn, "INSERT INTO notifications (recipient_role, project_id, message, uploaded_at) VALUES ('user', ?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt_notify, "is", $project_id, $message);
                    mysqli_stmt_execute($stmt_notify);
                    mysqli_stmt_close($stmt_notify);
                }
            }
            mysqli_stmt_close($stmt);
        }
        
        if ($project_assigned || $success_count > 0) {
            $team_success = "Project assigned to admin" . ($success_count > 0 ? " and $success_count user(s) assigned successfully." : ".");
        } else {
            $team_error = "No new assignments made. Admin or users may already be assigned.";
        }
    }
}

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $project_id = intval($_POST['project_id']);
    $category = $_POST['category'];
    
    if (empty($title) || empty($project_id) || empty($category)) {
        $task_error = "Title, project, and category are required.";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO tasks (project_id, title, description, due_date, category, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        mysqli_stmt_bind_param($stmt, "issss", $project_id, $title, $description, $due_date, $category);
        if ($stmt->execute()) {
            $task_success = "Task created successfully.";
        } else {
            $task_error = "Failed to create task.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $task_id = intval($_POST['task_id']);
    $employee_id = $_POST['employee_id'];
    $stmt = mysqli_prepare($conn, "UPDATE tasks SET employee_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $employee_id, $task_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $message = "Task assigned successfully.";
}

// Fetch notifications
$notifications_query = "SELECT n.id, n.project_id, n.task_id, n.message, n.uploaded_at, 
                       p.name AS project_name, t.title AS task_title, fu.file_path, fu.employee_id
                       FROM notifications n
                       JOIN projects p ON n.project_id = p.id
                       JOIN tasks t ON n.task_id = t.id
                       LEFT JOIN file_uploads fu ON n.task_id = fu.task_id
                       WHERE n.recipient_role = 'superadmin' AND n.is_read = FALSE
                       ORDER BY n.uploaded_at DESC";
$notifications_result = mysqli_query($conn, $notifications_query);

// Fetch admins
$admins_query = "SELECT employee_id, email, is_active FROM users WHERE role = 'admin'";
$admins_result = mysqli_query($conn, $admins_query);

// Fetch users
$users_query = "SELECT employee_id, email FROM users WHERE role = 'user'";
$users_result = mysqli_query($conn, $users_query);

// Fetch all users and admins for task assignment
$all_users_query = "SELECT employee_id, email, role FROM users WHERE role IN ('user', 'admin') AND is_active = 1";
$all_users_result = mysqli_query($conn, $all_users_query);

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

// Fetch all projects for assignment
$projects_query = "SELECT id, name FROM projects";
$projects_result = mysqli_query($conn, $projects_query);

// Fetch all tasks for assignment
$tasks_query = "SELECT id, title FROM tasks";
$tasks_result = mysqli_query($conn, $tasks_query);

// Fetch all projects for team assignment
$team_projects_query = "SELECT id, name FROM projects";
$team_projects_result = mysqli_query($conn, $team_projects_query);

// Fetch admins for team assignment
$team_admins_query = "SELECT employee_id, email FROM users WHERE role = 'admin' AND is_active = 1";
$team_admins_result = mysqli_query($conn, $team_admins_query);

// Fetch users for team assignment
$team_users_query = "SELECT employee_id, email FROM users WHERE role = 'user' AND is_active = 1";
$team_users_result = mysqli_query($conn, $team_users_query);

// Count projects, admins, and users for dashboard stats
$project_count_query = "SELECT COUNT(*) as count FROM projects";
$project_count_result = mysqli_query($conn, $project_count_query);
$project_count = mysqli_fetch_assoc($project_count_result)['count'];

$admin_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'admin'";
$admin_count_result = mysqli_query($conn, $admin_count_query);
$admin_count = mysqli_fetch_assoc($admin_count_result)['count'];

$user_count_query = "SELECT COUNT(*) as count FROM users WHERE role = 'user'";
$user_count_result = mysqli_query($conn, $user_count_query);
$user_count = mysqli_fetch_assoc($user_count_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Dashboard - Project Management</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<<<<<<< HEAD
=======
    <link href="https://fonts.cdnfonts.com/css/samarkan?styles=6066" rel="stylesheet">
>>>>>>> origin/rel-code
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
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #22d6df 100%);
            --gradient-4: linear-gradient(135deg, #43e97b 0%,rgb(3, 190, 155) 100%);
            --shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
<<<<<<< HEAD
=======
            overflow: auto;
        }
        .wrapper {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
>>>>>>> origin/rel-code
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #1f2937;
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100%;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 0 2rem;
            margin-bottom: 2rem;
        }

        .sidebar h2 {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 0 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 2rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            border-radius: 12px;
            margin: 0 1rem 0.5rem;
            transition: all 0.3s ease;
        }

        .sidebar a i {
            font-size: 1.2rem;
            width: 20px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

<<<<<<< HEAD
        /* Dashboard Container */
        .dashboard-container {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            min-height: calc(100vh - 200px);
        }
=======
/* Dashboard Main */
.dashboard-main {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* Dashboard Container */
.dashboard-container {
    margin-left: 280px;
    flex: 1 0 auto; /* Grow to fill available space, don't shrink */
    padding: 2rem;
    min-height: calc(100vh - 60px); /* Ensure it takes up remaining space minus footer height */
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}
>>>>>>> origin/rel-code

        /* Header */
        .dashboard-header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-radius: 16px;
        }

        .dashboard-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .welcome-text {
            font-size: 1rem;
            color: var(--gray);
            font-weight: 500;
        }

        /* Buttons */
<<<<<<< HEAD
        .btn, .btn-primary, .btn-logout, .btn-approve, .btn-reject, .btn-delete, .btn-block, .btn-revive, .btn-view, .btn-convert {
=======
        .btn, .btn-primary, .btn-logout, .btn-approve, .btn-reject, .btn-delete, .btn-block, .btn-revive, .btn-view, .btn-convert, .btn-reset {
>>>>>>> origin/rel-code
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
        }

        .btn-logout {
            background: var(--gradient-2);
            color: white;
        }

        .btn-approve, .btn-convert {
            background: var(--gradient-4);
            color: white;
        }

        .btn-reject, .btn-delete, .btn-block {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }

<<<<<<< HEAD
        .btn-revive, .btn-view {
=======
        .btn-revive, .btn-view, .btn-reset {
>>>>>>> origin/rel-code
            background: var(--gradient-3);
            color: white;
        }

        .btn-primary:hover, .btn-logout:hover, .btn-approve:hover, .btn-reject:hover,
<<<<<<< HEAD
        .btn-delete:hover, .btn-block:hover, .btn-revive:hover, .btn-view:hover, .btn-convert:hover {
=======
        .btn-delete:hover, .btn-block:hover, .btn-revive:hover, .btn-view:hover, .btn-convert:hover, .btn-reset:hover {
>>>>>>> origin/rel-code
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.3s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .dashboard-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
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

        /* Notification Icon */
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
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 50%;
            border: 2px solid #ef4444;
        }

        /* Notification Dropdown */
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            width: 350px;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--secondary);
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: var(--secondary);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--gray);
        }

        .notification-message {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-file {
            display: inline-block;
            color: var(--primary);
            font-size: 0.8rem;
            text-decoration: none;
            margin-bottom: 0.5rem;
        }

        .notification-file:hover {
            text-decoration: underline;
        }

        .notification-empty {
            padding: 1.5rem;
            text-align: center;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Tab Container */
        .tab-container {
            margin-bottom: 2rem;
        }

        .tab-content {
            display: none;
<<<<<<< HEAD
        }

=======
            padding: 2rem;
        }


>>>>>>> origin/rel-code
        .tab-content.active {
            display: block;
        }

<<<<<<< HEAD
=======
        /* Footer Styles */
        .footer {
            text-align: center;
            padding: 1em;
            color: #a0aec0; /* Light grey */
            font-size: 0.875rem;
            cursor: pointer;
            margin-top: ; /* Pushes footer to bottom of main-content */
        }

        .footer:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Credits Modal */
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
            max-width: 400px;
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

        .credits-modal-body {
            padding: 2rem;
            text-align: center;
        }

        .credits-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .credits-list li {
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                max-height: 95vh;
            }
        }

>>>>>>> origin/rel-code
        /* Form Container */
        .form-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .form-container h3 {
            margin-bottom: 1.5rem;
            color: var(--dark);
            font-weight: 600;
            text-align: center;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            align-items: start;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-container label {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }

        .form-container input,
        .form-container select,
        .form-container textarea {
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            width: 100%;
            font-size: 1rem;
            color: var(--dark);
            transition: all 0.3s ease;
        }

        .form-container input:focus,
        .form-container select:focus,
        .form-container textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-container button {
            background: var(--gradient-1);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            grid-column: span 2;
            margin-top: 1rem;
            align-self: center;
            min-width: 200px;
        }

        .form-container button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .message-success {
            color: var(--success);
            text-align: center;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .message-error {
            color: var(--danger);
            text-align: center;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        /* Checkbox Container */
        .checkbox-container {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: var(--white);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .checkbox-container label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            color: var(--dark);
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .checkbox-container label:hover {
            background: var(--secondary);
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        /* List Container */
        .list-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--secondary);
            transition: all 0.3s ease;
        }

        .list-item:hover {
            background: var(--secondary);
        }

        /* Project Card */
        .project-card {
            background: white;
            border: 2px solid var(--secondary);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-1);
            transform: scaleY(0);
            transition: transform 0.3s ease;
            transform-origin: bottom;
        }

        .project-card:hover::before {
            transform: scaleY(1);
        }

        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .project-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            line-height: 1.3;
            flex: 1;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-completed {
            background: rgba(6, 182, 212, 0.1);
            color: var(--accent);
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-delayed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-danger {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .project-description {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .project-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .meta-item i {
            color: var(--primary);
            font-size: 0.9rem;
        }

<<<<<<< HEAD
        ./* Project Card */
        .project-card {
            background: white;
            border: 2px solid var(--secondary);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-1);
            transform: scaleY(0);
            transition: transform 0.3s ease;
            transform-origin: bottom;
        }

        .project-card:hover::before {
            transform: scaleY(1);
        }

        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .project-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            line-height: 1.3;
            flex: 1;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-completed {
            background: rgba(6, 182, 212, 0.1);
            color: var(--accent);
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-delayed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-danger {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .project-description {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .project-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray);
            font-size: 0.8rem;
            font-weight: 500;
        }

        .meta-item i {
            color: var(--primary);
            font-size: 0.9rem;
        }

=======
>>>>>>> origin/rel-code
        .project-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .btn-view {
            background: var(--gradient-1);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
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
            background: var(--white);
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
            background: var(--gradient-1);
        }

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
            background: var(--gradient-4);
            border-radius: 50px;
            transition: width 1s ease;
        }

        .dashboard-main {
            padding: 1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Animations */
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

        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        .slide-up {
            animation: slideUp 0.6s ease-out;
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
<<<<<<< HEAD
                    <div style="font-size: 1.25rem;">TiHAN-NIDHI</div>
                    <div style="font-size: 1.25rem;">Superadmin</div>
=======
                    <div style="font-size: 1.65rem; font-family: 'Samarkan', sans-serif; ">NIDHI</div>
                    <div style="font-size: 1.15rem;">Superadmin</div>
>>>>>>> origin/rel-code
                    <div style="font-size: 0.75rem; opacity: 0.8;">Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        <a href="#dashboard" class="tab-link active" onclick="showTab('dashboard')">Dashboard</a>
        <a href="#projects" class="tab-link" onclick="showTab('projects')">Projects</a>
        <a href="#admin-management" class="tab-link" onclick="showTab('admin-management')">Admin Management</a>
        <a href="#user-management" class="tab-link" onclick="showTab('user-management')">User Management</a>
        <a href="#add-user-admin" class="tab-link" onclick="showTab('add-user-admin')">Add User/Admin</a>
        <a href="#assignments" class="tab-link" onclick="showTab('assignments')">Assignments</a>
        <a href="#task-assignment" class="tab-link" onclick="showTab('task-assignment')">Task Management</a>
        <a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <h1>NIDHI Superadmin Dashboard</h1>
            <div class="header-actions">
                <span class="welcome-text">Welcome, <?= htmlspecialchars($_SESSION['email']) ?></span>
                <a href="superadmin_add_project.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Project
                </a>
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if (mysqli_num_rows($notifications_result) > 0): ?>
                        <span class="notification-count"><?= mysqli_num_rows($notifications_result) ?></span>
                    <?php endif; ?>
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
                                    <?= date('M d, Y H:i:s', strtotime($notification['uploaded_at'])) ?>
                                </span>
                            </div>
                            <div class="notification-message">
                                Employee ID: <?= htmlspecialchars($notification['employee_id']) ?><br>
                                <?= htmlspecialchars($notification['message']) ?>
                            </div>
                            <?php if ($notification['file_path']): ?>
                                <a href="<?= htmlspecialchars($notification['file_path']) ?>" class="notification-file" target="_blank">View Uploaded File</a>
                            <?php endif; ?>
                            <div class="notification-actions">
                                <button class="btn-approve" onclick="handleNotificationAction(<?= $notification['id'] ?>, 'approve', <?= $notification['task_id'] ?>, '<?= htmlspecialchars($notification['employee_id']) ?>')">Approve</button>
                                <button class="btn-reject" onclick="handleNotificationAction(<?= $notification['id'] ?>, 'reject', <?= $notification['task_id'] ?>, '<?= htmlspecialchars($notification['employee_id']) ?>')">Reject</button>
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
        </header>

        <!-- Tabbed Content -->
        <div class="tab-container">
            <!-- Dashboard Tab -->
            <div class="tab-content active" id="dashboard">
                <div class="stats-grid">
                    <div class="stat-card" onclick="showTab('projects')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $project_count ?></div>
                        <div class="stat-label">Total Projects</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="showTab('admin-management')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $admin_count ?></div>
                        <div class="stat-label">Total Admins</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="showTab('user-management')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?= $user_count ?></div>
                        <div class="stat-label">Total Users</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card" onclick="showTab('add-user-admin')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                        </div>
                        <div class="stat-number">Add</div>
                        <div class="stat-label">New User/Admin</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects Tab -->
            <div class="tab-content" id="projects">
                <main class="dashboard-main">
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
                                                <button class="btn-delete" onclick="event.stopPropagation(); if(confirm('Are you sure you want to delete this project?')) window.location.href='superadmin_dashboard.php?delete_project=<?= $project['id'] ?>'">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No projects found</p>
                                        <a href="superadmin_add_project.php?type=UGV" class="btn btn-primary">Add Project</a>
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
                                                <button class="btn-delete" onclick="event.stopPropagation(); if(confirm('Are you sure you want to delete this project?')) window.location.href='superadmin_dashboard.php?delete_project=<?= $project['id'] ?>'">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No projects found</p>
                                        <a href="superadmin_add_project.php?type=UAV" class="btn btn-primary">Add Project</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
            </div>

            <!-- Admin Management Tab -->
            <div class="tab-content" id="admin-management">
                <div class="list-container">
                    <h3>Manage Admins</h3>
                    <?php if (mysqli_num_rows($admins_result) > 0): ?>
                        <?php while ($admin = mysqli_fetch_assoc($admins_result)): ?>
                            <div class="list-item">
                                <span>
                                    <?= htmlspecialchars($admin['email']) ?>
                                    <?php if (!$admin['is_active']): ?>
                                        <span style="color: red;">(Suspended)</span>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <?php if ($admin['is_active']): ?>
                                        <button class="btn-block" onclick="if(confirm('Are you sure you want to block this admin?')) window.location.href='superadmin_dashboard.php?block_admin=<?= urlencode($admin['employee_id']) ?>'">
                                            <i class="fas fa-ban"></i> Block
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-revive" onclick="if(confirm('Are you sure you want to revive this admin?')) window.location.href='superadmin_dashboard.php?revive_admin=<?= urlencode($admin['employee_id']) ?>'">
                                            <i class="fas fa-undo"></i> Revive
                                        </button>
                                    <?php endif; ?>
<<<<<<< HEAD
=======
                                    <button class="btn-reset" onclick="event.stopPropagation(); resetPassword('<?= urlencode($admin['employee_id']) ?>', 'admin')">
                                        <i class="fas fa-key"></i> Reset Password
                                    </button>
>>>>>>> origin/rel-code
                                    <button class="btn-delete" onclick="if(confirm('Are you sure you want to delete this admin?')) window.location.href='superadmin_dashboard.php?delete_admin=<?= urlencode($admin['employee_id']) ?>'">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No admins found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Management Tab -->
            <div class="tab-content" id="user-management">
                <div class="list-container">
                    <h3>Manage Users</h3>
                    <?php if (mysqli_num_rows($users_result) > 0): ?>
                        <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                            <div class="list-item">
                                <span><?= htmlspecialchars($user['email']) ?></span>
                                <div>
                                    <button class="btn-convert" onclick="if(confirm('Are you sure you want to convert this user to admin?')) window.location.href='superadmin_dashboard.php?convert_to_admin=<?= urlencode($user['employee_id']) ?>'">
                                        <i class="fas fa-user-shield"></i> Admin
                                    </button>
<<<<<<< HEAD
  <button class="btn-delete" onclick="if(confirm('Are you sure you want to delete this user?')) window.location.href='superadmin_dashboard.php?delete_user=<?= urlencode($user['employee_id']) ?>'">
=======
                                    <button class="btn-reset" onclick="event.stopPropagation(); resetPassword('<?= urlencode($user['employee_id']) ?>', 'user')">
                                        <i class="fas fa-key"></i> Reset Password
                                    </button>
                                    <button class="btn-delete" onclick="if(confirm('Are you sure you want to delete this user?')) window.location.href='superadmin_dashboard.php?delete_user=<?= urlencode($user['employee_id']) ?>'">
>>>>>>> origin/rel-code
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No users found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add User/Admin Tab -->
            <div class="tab-content" id="add-user-admin">
                <div class="form-container">
                    <h3>Add New User/Admin</h3>
                    <?php if (isset($message) && strpos($message, 'added successfully') !== false): ?>
                        <p class="message-success"><?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <p class="message-error"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="add_user_admin" value="1">
                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select name="role" id="role" required>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" required>
                        </div>
                        <div class="form-group">
                            <label for="employee_id">Employee ID:</label>
                            <input type="text" name="employee_id" id="employee_id" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Temporary Password:</label>
                            <input type="text" name="password" id="password" required>
                        </div>
                        <button type="submit">Add User/Admin</button>
                    </form>
                </div>
            </div>

<<<<<<< HEAD
=======
    <!-- Add User/Admin Tab -->
            <div class="tab-content" id="add-user-admin">
                <div class="form-container">
                    <h3>Add New User/Admin</h3>
                    <?php if (isset($message) && strpos($message, 'added successfully') !== false): ?>
                        <p class="message-success"><?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <p class="message-error"><?= htmlspecialchars($error) ?></p>
                    <?php endif; ?>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="add_user_admin" value="1">
                        <div class="form-group">
                            <label for="role">Role:</label>
                            <select name="role" id="role" required>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" required>
                        </div>
                        <div class="form-group">
                            <label for="employee_id">Employee ID:</label>
                            <input type="text" name="employee_id" id="employee_id" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Temporary Password:</label>
                            <input type="text" name="password" id="password" required>
                        </div>
                        <button type="submit">Add User/Admin</button>
                    </form>
                </div>
            </div>

>>>>>>> origin/rel-code
            <!-- Assignments Tab -->
            <div class="tab-content" id="assignments">
                <div class="form-container">
                    <h3>Assign Project and Team Members</h3>
                    <?php if (isset($team_success)): ?>
                        <p class="message-success"><?= htmlspecialchars($team_success) ?></p>
                    <?php endif; ?>
                    <?php if (isset($team_error)): ?>
                        <p class="message-error"><?= htmlspecialchars($team_error) ?></p>
                    <?php endif; ?>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="assign_project_team" value="1">
                        <div class="form-group">
                            <label for="project_id">Select Project:</label>
                            <select name="project_id" id="project_id" required>
                                <?php mysqli_data_seek($team_projects_result, 0); ?>
                                <?php while ($project = mysqli_fetch_assoc($team_projects_result)): ?>
                                    <option value="<?= htmlspecialchars($project['id']) ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="admin_id">Select Admin:</label>
                            <select name="admin_id" id="admin_id" required>
                                <?php mysqli_data_seek($team_admins_result, 0); ?>
                                <?php while ($admin = mysqli_fetch_assoc($team_admins_result)): ?>
                                    <option value="<?= htmlspecialchars($admin['employee_id']) ?>"><?= htmlspecialchars($admin['email']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="user_ids">Select Users (Optional):</label>
                            <div class="checkbox-container">
                                <?php mysqli_data_seek($team_users_result, 0); ?>
                                <?php while ($user = mysqli_fetch_assoc($team_users_result)): ?>
                                    <label>
                                        <input type="checkbox" name="user_ids[]" value="<?= htmlspecialchars($user['employee_id']) ?>">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <button type="submit">Assign Project and Team</button>
                    </form>
                </div>
            </div>
<<<<<<< HEAD

=======
            
>>>>>>> origin/rel-code
            <!-- Task Management Tab -->
            <div class="tab-content" id="task-assignment">
                <div class="form-container">
                    <h3>Create New Task</h3>
                    <?php if (isset($task_success)): ?>
                        <p class="message-success"><?= htmlspecialchars($task_success) ?></p>
                    <?php endif; ?>
                    <?php if (isset($task_error)): ?>
                        <p class="message-error"><?= htmlspecialchars($task_error) ?></p>
                    <?php endif; ?>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="create_task" value="1">
                        <div class="form-group">
                            <label for="title">Task Title:</label>
                            <input type="text" name="title" id="title" required>
                        </div>
                        <div class="form-group">
                            <label for="due_date">Due Date:</label>
                            <input type="date" name="due_date" id="due_date">
                        </div>
                        <div class="form-group full-width">
                            <label for="description">Description:</label>
                            <textarea name="description" id="description" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="project_id">Select Project:</label>
                            <select name="project_id" id="project_id" required>
                                <?php mysqli_data_seek($projects_result, 0); ?>
                                <?php while ($project = mysqli_fetch_assoc($projects_result)): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <select name="category" id="category" required>
                                <option value="UGV">UGV</option>
                                <option value="UAV">UAV</option>
                            </select>
                        </div>
                        <button type="submit">Create Task</button>
                    </form>
                </div>
                <div class="form-container">
                    <h3>Assign Task</h3>
                    <?php if (isset($message) && strpos($message, 'Task assigned') !== false): ?>
                        <p class="message-success"><?= htmlspecialchars($message) ?></p>
                    <?php endif; ?>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="assign_task" value="1">
                        <div class="form-group">
                            <label for="task_id">Select Task:</label>
                            <select name="task_id" id="task_id" required>
                                <?php mysqli_data_seek($tasks_result, 0); ?>
                                <?php while ($task = mysqli_fetch_assoc($tasks_result)): ?>
                                    <option value="<?= $task['id'] ?>"><?= htmlspecialchars($task['title']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="employee_id">Select Employee:</label>
                            <select name="employee_id" id="employee_id" required>
                                <?php mysqli_data_seek($all_users_result, 0); ?>
                                <?php while ($employee = mysqli_fetch_assoc($all_users_result)): ?>
                                    <option value="<?= htmlspecialchars($employee['employee_id']) ?>">
                                        <?= htmlspecialchars($employee['email']) ?> (<?= ucfirst($employee['role']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit">Assign Task</button>
                    </form>
                </div>
<<<<<<< HEAD
            </div>
        </div>
    </div>

=======
                <div class="form-container">
                    <h3>Reset Password</h3>
                    <?php if (isset($reset_message)): ?>
                        <p class="message-success"><?= htmlspecialchars($reset_message) ?></p>
                    <?php endif; ?>
                    <?php if (isset($reset_error)): ?>
                        <p class="message-error"><?= htmlspecialchars($reset_error) ?></p>
                    <?php endif; ?>
                    <form method="POST" class="form-grid" id="resetPasswordForm">
                        <input type="hidden" name="reset_password" value="1">
                        <input type="hidden" name="employee_id" id="reset_employee_id">
                        <div class="form-group full-width">
                            <label for="new_password">New Password:</label>
                            <input type="text" name="new_password" id="new_password" required>
                        </div>
                        <button type="submit">Reset Password</button>
                    </form>
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
                    <h2>Project Credits</h2>
                    <button class="modal-close" onclick="closeCreditsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="credits-modal-body">
                    <h3>Team Members</h3>
                    <ul class="credits-list">
                        <li>Dr. P. Rajalakshmi</li>
                        <li>Dr. S. Syam Narayanan</li>
                        <li>Muhammed Nazim</li>
                        <li>Sharon Zipporah Sebastain</li>
                    </ul>
                    <p>Thank you to our dedicated team for their contributions to this project!</p>
                </div>
            </div>
        </div>
    </div>
>>>>>>> origin/rel-code
    <script>
        function viewProject(project_id) {
            console.log('Navigating to project details for ID:', project_id);
            window.location.href = `superadmin_project_details.php?id=${project_id}&sid=${encodeURIComponent('<?php echo session_id(); ?>')}`;
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            if (dropdown.classList.contains('active')) {
                fetch('superadmin_dashboard.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_viewed'
                });
            }
        }

        function handleNotificationAction(notificationId, action, taskId, employeeId) {
            if (!confirm(`Are you sure you want to ${action} this task completion?`)) return;

            const formData = new FormData();
            formData.append('action', action);
            formData.append('notification_id', notificationId);
            formData.append('task_id', taskId);
            formData.append('employee_id', employeeId);

            fetch('superadmin_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationItem = document.querySelector(`.notification-item:has(button[onclick*='notificationId=${notificationId}'])`);
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
                            </div>
                        `;
                    }
                } else {
                    alert('Error processing action. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing action. Please try again.');
            });
        }

<<<<<<< HEAD
=======
        function resetPassword(employeeId, role) {
            if (!confirm(`Are you sure you want to reset the password for this ${role}?`)) return;
            document.getElementById('reset_employee_id').value = employeeId;
            showTab('task-assignment');
            document.getElementById('new_password').focus();
        }

>>>>>>> origin/rel-code
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-link').forEach(link => link.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`a[href="#${tabId}"]`).classList.add('active');
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const icon = document.querySelector('.notification-icon');
            if (!dropdown.contains(e.target) && !icon.contains(e.target)) {
                dropdown.classList.remove('active');
            }
<<<<<<< HEAD
=======

                        // Sidebar toggle for mobile
            window.toggleSidebar = function() {
                const sidebar = document.getElementById('sidebar');
                sidebar.style.transform = sidebar.style.transform === 'translateX(-100%)' ? 'translateX(0)' : 'translateX(-100%)';
            };

            // Credits modal functionality
            window.showCreditsModal = function() {
                document.getElementById('creditsModal').style.display = 'flex';
                document.body.style.overflow = 'auto';
            };

            window.closeCreditsModal = function() {
                document.getElementById('creditsModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            };

>>>>>>> origin/rel-code
        });

        // Initialize first tab
        showTab('dashboard');
    </script>
</body>
</html>