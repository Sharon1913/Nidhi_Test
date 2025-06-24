<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get user ID and project ID from URL
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($user_id == 0) {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle task deletion
if (isset($_GET['delete_task']) && isset($_GET['user_id'])) {
    $task_id = intval($_GET['delete_task']);
    $user_id = intval($_GET['user_id']);
    
    // Delete the task
    $delete_task_query = "DELETE FROM tasks WHERE id = ? AND employee_id = (SELECT employee_id FROM users WHERE id = ?)";
    $stmt = mysqli_prepare($conn, $delete_task_query);
    mysqli_stmt_bind_param($stmt, "ii", $task_id, $user_id);
    mysqli_stmt_execute($stmt);
    
    // Redirect back to the same page after deletion
    header("Location: admin_user_tasks.php?user_id=$user_id" . ($project_id > 0 ? "&project_id=$project_id" : ""));
    exit();
}

// Fetch user details
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id); 
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header("Location: admin_dashboard.php");
    exit();
}

$employee_id = $user['employee_id'];
error_log("Fetching tasks for employee_id: " . $employee_id);

// Fetch project details if project_id is provided
$project = null;
if ($project_id > 0) {
    $project_query = "SELECT * FROM projects WHERE id = ?";
    $stmt = mysqli_prepare($conn, $project_query);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    $project_result = mysqli_stmt_get_result($stmt);
    $project = mysqli_fetch_assoc($project_result);
}

// Build the tasks query - filter by project if project_id is provided
if ($project_id > 0) {
    // Show tasks for specific user in specific project
    $tasks_query = "SELECT t.*, p.name as project_name, p.category as project_category 
                    FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.id 
                    WHERE t.employee_id = ? AND t.project_id = ? 
                    ORDER BY t.due_date ASC, t.created_at DESC";
    $stmt = mysqli_prepare($conn, $tasks_query);
    mysqli_stmt_bind_param($stmt, "si", $user['employee_id'], $project_id);
} else {
    // Show all tasks for user (original behavior)
    $tasks_query = "SELECT t.*, p.name as project_name, p.category as project_category 
                    FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.id 
                    WHERE t.employee_id = ? 
                    ORDER BY t.due_date ASC, t.created_at DESC";
    $stmt = mysqli_prepare($conn, $tasks_query);
    mysqli_stmt_bind_param($stmt, "s", $user['employee_id']);
}

mysqli_stmt_execute($stmt);
$tasks_result = mysqli_stmt_get_result($stmt);

// Calculate task statistics for this specific context
// Re-fetch employee_id to ensure correctness
$employee_id_query = "SELECT employee_id FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $employee_id_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$employee_result = mysqli_stmt_get_result($stmt);
$employee_data = mysqli_fetch_assoc($employee_result);
$employee_id = $employee_data['employee_id'];

// Log for debugging
error_log("Stats query using employee_id: " . $employee_id);

// Now calculate stats using the correct employee_id, treating it as a string
if ($project_id > 0) {
    $stats_query = "SELECT 
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks,
                    COUNT(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 END) as overdue_tasks
                    FROM tasks 
                    WHERE employee_id = ? AND project_id = ?";
    $stmt = mysqli_prepare($conn, $stats_query);
    mysqli_stmt_bind_param($stmt, "si", $employee_id, $project_id); // Treat employee_id as string
} else {
    $stats_query = "SELECT 
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks,
                    COUNT(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 END) as overdue_tasks
                    FROM tasks 
                    WHERE employee_id = ?";
    $stmt = mysqli_prepare($conn, $stats_query);
    mysqli_stmt_bind_param($stmt, "s", $employee_id); // Treat employee_id as string
}

mysqli_stmt_execute($stmt);
$stats_result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($stats_result);

// Log the stats for debugging
error_log("Stats for employee_id $employee_id: Total=" . $stats['total_tasks'] . ", Completed=" . $stats['completed_tasks'] . ", In Progress=" . $stats['in_progress_tasks'] . ", Pending=" . $stats['pending_tasks']);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['email']) ?> - Tasks<?= $project ? ' in ' . htmlspecialchars($project['name']) : '' ?></title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body{
            overflow: scroll;
        }
        
        .user-tasks-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .user-header-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .user-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .user-info h1 {
            color: #2d3748;
            font-size: 1.8rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar-large {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .project-context {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            margin-bottom: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-total .stat-number { color: #4a5568; }
        .stat-completed .stat-number { color: #22543d; }
        .stat-progress .stat-number { color: #c05621; }
        .stat-pending .stat-number { color: #2c5282; }
        .stat-overdue .stat-number { color: #c53030; }
        
        .tasks-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .tasks-grid {
            padding: 2rem;
            display: grid;
            gap: 1.5rem;
        }
        
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .task-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }
        
        .task-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .task-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 32px;
            min-width: 90px;
        }

        
        .status-pending { background: #bee3f8; color: #2c5282; }
        .status-in_progress { background: #feebc8; color: #c05621; }
        .status-completed { background: #c6f6d5; color: #22543d; }
        
        .task-description {
            color: #4a5568;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .task-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.9rem;
            color: #718096;
        }
        
        .task-due-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .overdue {
            color: #c53030;
            font-weight: 600;
        }
        
        .btn-delete-task {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }
        
        .btn-delete-task:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            color: #764ba2;
            transform: translateX(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e0;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .user-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-tasks"></i> User Tasks</h1>
                <div class="header-actions">
                    <?php if ($project_id > 0): ?>
                        <a href="admin_project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Project
                        </a>
                    <?php else: ?>
                        <a href="admin_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    <?php endif; ?>
                    <a href="admin_logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="user-tasks-container">
            <?php if ($project_id > 0): ?>
                <a href="admin_project_details.php?id=<?= $project_id ?>" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to <?= htmlspecialchars($project['name']) ?>
                </a>
            <?php else: ?>
                <a href="admin_dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            <?php endif; ?>

            <!-- User Header Section -->
            <div class="user-header-section">
                <div class="user-title">
                    <div class="user-info">
                        <h1>
                            <div class="user-avatar-large">
                                <?= strtoupper(substr($user['email'], 0, 2)) ?>
                            </div>
                            <?= htmlspecialchars($user['email']) ?>
                        </h1>
                    </div>
                </div>

                <?php if ($project): ?>
                    <div class="project-context">
                        <h4><i class="fas fa-project-diagram"></i> Project Context</h4>
                        <p>Showing tasks for <strong><?= htmlspecialchars($project['name']) ?></strong> 
                           <span class="project-type-badge type-<?= strtolower($project['category']) ?>">
                               <?= $project['category'] ?>
                           </span>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card stat-total">
                        <div class="stat-number"><?= $stats['total_tasks'] ?></div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                    <div class="stat-card stat-completed">
                        <div class="stat-number"><?= $stats['completed_tasks'] ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card stat-progress">
                        <div class="stat-number"><?= $stats['in_progress_tasks'] + $stats['pending_tasks'] ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <?php if ($stats['overdue_tasks'] > 0): ?>
                    <div class="stat-card stat-overdue">
                        <div class="stat-number"><?= $stats['overdue_tasks'] ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tasks Section -->
            <div class="tasks-section">
                <div class="section-header">
                    <h2><i class="fas fa-list-check"></i> 
                        <?= $project ? 'Tasks in ' . htmlspecialchars($project['name']) : 'All Tasks' ?>
                    </h2>
                    <span class="task-count"><?= mysqli_num_rows($tasks_result) ?> Tasks</span>
                </div>

                <div class="tasks-grid">
                    <?php if (mysqli_num_rows($tasks_result) > 0): ?>
                        <?php while($task = mysqli_fetch_assoc($tasks_result)): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <h3 class="task-title"><?= htmlspecialchars($task['title']) ?></h3>
                                    <div class="task-actions">
                                        <span class="task-status status-in_progress">
                                            In Progress
                                        </span>
                                        <a href="admin_edit_tasks.php?task_id=<?= $task['id'] ?>" class="btn btn-secondary" style="margin-left: 0.5rem;">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button class="btn-delete-task" onclick="if(confirm('Are you sure you want to delete this task?')) window.location.href='admin_user_tasks.php?user_id=<?= $user_id ?>&delete_task=<?= $task['id'] ?><?= $project_id > 0 ? "&project_id=$project_id" : "" ?>'">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>

                                <div class="task-description">
                                    <?= nl2br(htmlspecialchars($task['description'])) ?>
                                </div>

                                <div class="task-meta">
                                    <div class="task-project">
                                        <i class="fas fa-project-diagram"></i>
                                        <?= htmlspecialchars($task['project_name']) ?>
                                        <span class="project-type-badge type-<?= strtolower($task['project_category']) ?>">
                                            <?= $task['project_category'] ?>
                                        </span>
                                    </div>
                                    <div class="task-due-date <?= (strtotime($task['due_date']) < time() && $task['status'] != 'completed') ? 'overdue' : '' ?>">
                                        <i class="fas fa-calendar"></i>
                                        Due: <?= date('M d, Y', strtotime($task['due_date'])) ?>
                                        <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'completed'): ?>
                                            <i class="fas fa-exclamation-triangle"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-tasks"></i>
                            <h3>No Tasks Found</h3>
                            <p>
                                <?php if ($project): ?>
                                    This user has no tasks assigned in <?= htmlspecialchars($project['name']) ?>.
                                <?php else: ?>
                                    This user has no tasks assigned yet.
                                <?php endif; ?>
                            </p>
                            <?php if ($project_id > 0): ?>
                                <a href="admin_assign_task.php?project_id=<?= htmlspecialchars($project_id) ?>&employee_id=<?= htmlspecialchars($user['employee_id']) ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Assign Task
                                </a>
                                <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>