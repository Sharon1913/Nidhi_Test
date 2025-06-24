<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get project ID from URL
$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id == 0) {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle member removal
if (isset($_GET['remove_user']) && isset($_GET['project_id'])) {
    $employee_id = intval($_GET['remove_user']);
    $project_id = intval($_GET['project_id']);
    
    // Delete tasks Warning: Undefined variable $conn in /home/admin_project_details.php on line 27 assigned to this user for this project
    $delete_tasks = "DELETE FROM tasks WHERE employee_id = ? AND project_id = ?";
    $stmt = mysqli_prepare($conn, $delete_tasks);
    mysqli_stmt_bind_param($stmt, "si", $employee_id, $project_id);
    mysqli_stmt_execute($stmt);
    
    // Remove the user from project assignments
    $delete_assignment = "DELETE FROM project_assignments WHERE employee_id = ? AND project_id = ?";
    $stmt = mysqli_prepare($conn, $delete_assignment);
    mysqli_stmt_bind_param($stmt, "si", $employee_id, $project_id);
    mysqli_stmt_execute($stmt);
    
    header("Location: admin_project_details.php?id=$project_id");
    exit();
}

// Fetch project details
$project_query = "SELECT * FROM projects WHERE id = ?";
$stmt = mysqli_prepare($conn, $project_query);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$project_result = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($project_result);

if (!$project) {
    header("Location: admin_dashboard.php");
    exit();
}

// Determine status for badge
$status = strtolower($project['status']);
$due_date = new DateTime($project['due_date']);
$today = new DateTime();
$today->setTime(0, 0, 0);
if ($status === 'completed') {
    $status_class = 'completed';
    $status_text = 'Completed';
} elseif ($due_date < $today) {
    $status_class = 'delayed';
    $status_text = 'Delayed';
} else {
    $status_class = 'on-going';
    $status_text = 'On-going';
}

// Fetch users assigned to this project with their task counts and status, excluding admins and superadmins
$users_query = "SELECT u.id, u.email,
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) as in_progress_tasks,
                COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending_tasks,
                COUNT(CASE WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 END) as overdue_tasks,
                MAX(t.created_at) as last_task_assigned
                FROM users u
                INNER JOIN project_assignments pa ON u.employee_id = pa.employee_id
                LEFT JOIN tasks t ON u.employee_id = t.employee_id AND t.project_id = ?
                WHERE pa.project_id = ? AND u.role NOT IN ('admin', 'superadmin')
                GROUP BY u.id, u.email
                ORDER BY u.email";

$stmt = mysqli_prepare($conn, $users_query);
mysqli_stmt_bind_param($stmt, "ii", $project_id, $project_id);
mysqli_stmt_execute($stmt);
$users_result = mysqli_stmt_get_result($stmt);

// Calculate project progress
$progress_query = "SELECT 
                   COUNT(*) as total_tasks,
                   COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks
                   FROM tasks WHERE project_id = ?";
$stmt = mysqli_prepare($conn, $progress_query);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$progress_result = mysqli_stmt_get_result($stmt);
$progress = mysqli_fetch_assoc($progress_result);

$progress_percentage = $progress['total_tasks'] > 0 ? 
    round(($progress['completed_tasks'] / $progress['total_tasks']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['name']) ?> - Project Details</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body{
            overflow: scroll;
        }
        
        .project-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .project-header-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .project-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .project-title h1 {
            color: #2d3748;
            font-size: 2rem;
            margin: 0;
        }
        
        .project-type-badge {
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .type-ugv {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }
        
        .type-uav {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }
        
        .project-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .project-description {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        
        .project-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .progress-section {
            margin-top: 1.5rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
            transition: width 0.3s ease;
        }
        
        .users-section {
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
        
        .users-grid {
            padding: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .user-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 1rem;
        }
        
        .user-info h3 {
            margin: 0;
            color: #2d3748;
            font-size: 1.1rem;
        }
        
        .user-info p {
            margin: 0.25rem 0 0 0;
            color: #718096;
            font-size: 0.9rem;
        }
        
        .task-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .task-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }
        
        .stat-label-small {
            color: #718096;
        }
        
        .stat-value {
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .stat-total { background: #e2e8f0; color: #4a5568; }
        .stat-completed { background: #c6f6d5; color: #22543d; }
        .stat-progress { background: #feebc8; color: #c05621; }
        .stat-pending { background: #bee3f8; color: #2c5282; }
        .stat-overdue { background: #fed7d7; color: #c53030; }
        
        .user-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }
         
        .last-activity {
            font-size: 0.8rem;
            color: #a0aec0;
        }
        
        .btn-view-tasks {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-view-tasks:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-remove {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-remove:hover {
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
            .project-info-grid {
                grid-template-columns: 1fr;
            }
            
            .project-stats {
                grid-template-columns: 1fr;
            }
            
            .users-grid {
                grid-template-columns: 1fr;
            }
            
            .task-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-project-diagram"></i> Project Details</h1>
                <div class="header-actions">
                    <a href="admin_assign_task.php?project_id=<?= $project_id ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Assign Task
                    </a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="admin_logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="project-details-container">
            <a href="admin_dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <!-- Project Header Section -->
            <div class="project-header-section">
                <div class="project-title">
                    <h1><?= htmlspecialchars($project['name']) ?></h1>
                    <span class="project-type-badge type-<?= strtolower($project['category']) ?>">
                        <i class="fas fa-<?= $project['category'] == 'UGV' ? 'car' : 'helicopter' ?>"></i>
                        <?= $project['category'] ?>
                    </span>
                </div>

                <div class="project-info-grid">
                    <div class="project-description">
                        <h3><i class="fas fa-info-circle"></i> Project Description</h3>
                        <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                        
                        <div class="project-meta" style="margin-top: 1.5rem; display: flex; gap: 2rem;">
                            <span>
                                <i class="fas fa-calendar-plus"></i>
                                <strong>Created:</strong> <?= date('M d, Y', strtotime($project['created_at'])) ?>
                            </span>
                            <span>
                                <i class="fas fa-calendar-check"></i>
                                <strong>Due Date:</strong> <?= date('M d, Y', strtotime($project['due_date'])) ?>
                            </span>
                            <span class="status-badge status-<?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </div>
                    </div>

                    <div class="project-stats">
                        <div class="stat-card">
                            <div class="stat-number"><?= $progress['total_tasks'] ?></div>
                            <div class="stat-label">Total Tasks</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= mysqli_num_rows($users_result) ?></div>
                            <div class="stat-label">Team Members</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $progress['completed_tasks'] ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $progress_percentage ?>%</div>
                            <div class="stat-label">Progress</div>
                        </div>
                    </div>
                </div>

                <div class="progress-section">
                    <h4>Project Progress</h4>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progress_percentage ?>%;"></div>
                    </div>
                    <small><?= $progress['completed_tasks'] ?> of <?= $progress['total_tasks'] ?> tasks completed</small>
                </div>
            </div>

            <!-- Users Section -->
            <div class="users-section">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Team Members</h2>
                    <span class="project-count"><?= mysqli_num_rows($users_result) ?> Members</span>
                </div>

                <div class="users-grid">
                    <?php if (mysqli_num_rows($users_result) > 0): ?>
                        <?php mysqli_data_seek($users_result, 0); // Reset result pointer ?>
                        <?php while($user = mysqli_fetch_assoc($users_result)): ?>
                            <div class="user-card" onclick="viewUserTasks(<?= $user['id'] ?>, <?= $project_id ?>)">
                                <div class="user-header">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['email'], 0, 2)) ?>
                                    </div>
                                    <div class="user-info">
                                        <h3><?= htmlspecialchars($user['email']) ?></h3>
                                        <p><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>

                                <div class="task-stats">
                                    <div class="task-stat">
                                        <span class="stat-label-small">Total Tasks:</span>
                                        <span class="stat-value stat-total"><?= $user['total_tasks'] ?></span>
                                    </div>
                                    <div class="task-stat">
                                        <span class="stat-label-small">Completed:</span>
                                        <span class="stat-value stat-completed"><?= $user['completed_tasks'] ?></span>
                                    </div>
                                    <div class="task-stat">
                                        <span class="stat-label-small">In Progress:</span>
                                        <span class="stat-value stat-progress"><?= $user['in_progress_tasks'] ?></span>
                                    </div>
                                    <div class="task-stat">
                                        <span class="stat-label-small">Pending:</span>
                                        <span class="stat-value stat-pending"><?= $user['pending_tasks'] ?></span>
                                    </div>
                                    <?php if ($user['overdue_tasks'] > 0): ?>
                                    <div class="task-stat">
                                        <span class="stat-label-small">Overdue:</span>
                                        <span class="stat-value stat-overdue"><?= $user['overdue_tasks'] ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="user-actions">
                                    <div class="last-activity">
                                        <?php if ($user['last_task_assigned']): ?>
                                            Last task: <?= date('M d, Y', strtotime($user['last_task_assigned'])) ?>
                                        <?php else: ?>
                                            No tasks assigned
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button class="btn-view-tasks" onclick="event.stopPropagation(); viewUserTasks(<?= $user['id'] ?>, <?= $project_id ?>)">
                                            <i class="fas fa-eye"></i> View Tasks
                                        </button>
                                        <button class="btn-remove" onclick="event.stopPropagation(); if(confirm('Are you sure you want to remove this member from the project?')) window.location.href='admin_project_details.php?id=<?= $project_id ?>&remove_user=<?= $user['id'] ?>&project_id=<?= $project_id ?>'">
                                            <i class="fas fa-user"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-plus"></i>
                            <p>No team members assigned to this project</p>
                            <a href="admin_assign_user.php?project_id=<?= $project_id ?>" class="btn btn-primary">
                                Assign Team Members
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function viewUserTasks(employee_id, project_id) {
            window.location.href = `admin_user_tasks.php?user_id=${employee_id}&project_id=${project_id}`;
        }

        // Add loading animation for user cards
        document.addEventListener('DOMContentLoaded', function() {
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                card.addEventListener('click', function() {
                    const btn = this.querySelector('.btn-view-tasks');
                    if (btn) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                        btn.disabled = true;
                        
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }, 3000);
                    }
                });
            });
        });
    </script>
</body>
</html>