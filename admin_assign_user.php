<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get project ID from URL
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id == 0) {
    header("Location: admin_dashboard.php");
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

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_users']) && isset($_POST['selected_users'])) {
        $selected_users = $_POST['selected_users'];
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($selected_users as $employee_id) {
            // Check if user is already assigned
            $check_query = "SELECT id FROM project_assignments WHERE project_id = ? AND employee_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($check_stmt, "is", $project_id, $employee_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) == 0) {
                // Assign user to project
                $assign_query = "INSERT INTO project_assignments (project_id, employee_id, assigned_at) VALUES (?, ?, NOW())";
                $assign_stmt = mysqli_prepare($conn, $assign_query);
                mysqli_stmt_bind_param($assign_stmt, "is", $project_id, $employee_id);
                
                if (mysqli_stmt_execute($assign_stmt)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            $success_message = "Successfully assigned $success_count user(s) to the project.";
        }
        if ($error_count > 0) {
            $error_message = "Failed to assign $error_count user(s) to the project.";
        }
    }
    
    // Handle removing users
    if (isset($_POST['remove_user'])) {
        $employee_id = $_POST['employee_id'];
        
        $remove_query = "DELETE FROM project_assignments WHERE project_id = ? AND employee_id = ?";
        $remove_stmt = mysqli_prepare($conn, $remove_query);
        mysqli_stmt_bind_param($remove_stmt, "is", $project_id, $employee_id);
        
        if (mysqli_stmt_execute($remove_stmt)) {
            $success_message = "User removed from project successfully.";
        } else {
            $error_message = "Failed to remove user from project.";
        }
    }
}

// Fetch all users not assigned to this project
$available_users_query = "SELECT u.employee_id, u.email, u.role 
                         FROM users u 
                         WHERE u.employee_id NOT IN (
                             SELECT pa.employee_id 
                             FROM project_assignments pa 
                             WHERE pa.project_id = ?
                         ) AND u.role NOT IN ('admin', 'superadmin')
                         ORDER BY u.email";

$stmt = mysqli_prepare($conn, $available_users_query);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$available_users_result = mysqli_stmt_get_result($stmt);

// Fetch users already assigned to this project
$assigned_users_query = "SELECT u.employee_id, u.email, u.role, pa.assigned_at,
                        COUNT(t.id) as task_count
                        FROM users u
                        INNER JOIN project_assignments pa ON u.employee_id = pa.employee_id
                        LEFT JOIN tasks t ON u.employee_id = t.employee_id AND t.project_id = ?
                        WHERE pa.project_id = ?
                        GROUP BY u.employee_id, u.email, u.role, pa.assigned_at
                        ORDER BY u.email";

$stmt = mysqli_prepare($conn, $assigned_users_query);
mysqli_stmt_bind_param($stmt, "ii", $project_id, $project_id);
mysqli_stmt_execute($stmt);
$assigned_users_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Users - <?= htmlspecialchars($project['name']) ?></title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .assign-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        body{
            overflow: scroll;
            
        }
        
        .project-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .project-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
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
        
        .assign-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-content {
            padding: 1.5rem;
        }
        
        .user-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .user-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-email {
            font-weight: 600;
            color: #2d3748;
        }
        
        .user-role {
            font-size: 0.85rem;
            color: #718096;
            text-transform: capitalize;
        }
        
        .user-meta {
            font-size: 0.8rem;
            color: #a0aec0;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
        }
        
        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }


        
        .btn-remove {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-remove:hover {
            background: #c53030;
            transform: translateY(-1px);
        }
        
        .form-actions {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-assign {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-assign:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(72, 187, 120, 0.4);
        }
        
        .btn-assign:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .select-all-container {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8f9fa;
        }
        
        .select-all-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #4a5568;
            cursor: pointer;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #718096;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e0;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }
        
        @media (max-width: 768px) {
            .assign-sections {
                grid-template-columns: 1fr;
            }
            
            .user-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .user-info {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-user-plus"></i> Assign Team Members</h1>
                <div class="header-actions">
                    <a href="admin_project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Project
                    </a>
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="admin_logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="assign-container">
            <a href="admin_project_details.php?id=<?= $project_id ?>" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Project Details
            </a>

            <!-- Project Header -->
            <div class="project-header">
                <div class="project-title">
                    <h1><?= htmlspecialchars($project['name']) ?></h1>
                    <span class="project-type-badge type-<?= strtolower($project['category']) ?>">
                        <i class="fas fa-<?= $project['category'] == 'UGV' ? 'car' : 'helicopter' ?>"></i>
                        <?= $project['category'] ?>
                    </span>
                </div>
                <p style="color: #718096; margin: 0;">
                    <?= nl2br(htmlspecialchars($project['description'])) ?>
                </p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Main Sections -->
            <div class="assign-sections">
                <!-- Available Users Section -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-users"></i> Available Users</h2>
                        <span class="project-count"><?= mysqli_num_rows($available_users_result) ?> Available</span>
                    </div>

                    <?php if (mysqli_num_rows($available_users_result) > 0): ?>
                        <form method="POST" id="assignForm">
                            <div class="select-all-container">
                                <label class="select-all-label">
                                    <input type="checkbox" id="selectAll">
                                    Select All Users
                                </label>
                            </div>

                            <div class="section-content">
                                <div class="user-list">
                                    <?php while($user = mysqli_fetch_assoc($available_users_result)): ?>
                                        <div class="user-item">
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= strtoupper(substr($user['email'], 0, 2)) ?>
                                                </div>
                                                <div class="user-details">
                                                    <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
                                                    <span class="user-role"><?= htmlspecialchars($user['role']) ?></span>
                                                </div>
                                            </div>
                                            <div class="checkbox-container">
                                                <input type="checkbox" name="selected_users[]" value="<?= htmlspecialchars($user['employee_id']) ?>" class="user-checkbox">
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="assign_users" class="btn-assign" id="assignBtn" disabled>
                                        <i class="fas fa-user-plus"></i> Assign Selected Users
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="section-content">
                            <div class="empty-state">
                                <i class="fas fa-user-check"></i>
                                <p>All users are already assigned to this project</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assigned Users Section -->
                <div class="section">
                    <div class="section-header">
                        <h2><i class="fas fa-user-check"></i> Assigned Users</h2>
                        <span class="project-count"><?= mysqli_num_rows($assigned_users_result) ?> Assigned</span>
                    </div>

                    <div class="section-content">
                        <?php if (mysqli_num_rows($assigned_users_result) > 0): ?>
                            <div class="user-list">
                                <?php while($user = mysqli_fetch_assoc($assigned_users_result)): ?>
                                    <div class="user-item">
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($user['email'], 0, 2)) ?>
                                            </div>
                                            <div class="user-details">
                                                <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
                                                <span class="user-role"><?= htmlspecialchars($user['role']) ?></span>
                                                <span class="user-meta">
                                                    Assigned: <?= date('M d, Y', strtotime($user['assigned_at'])) ?> | 
                                                    Tasks: <?= $user['task_count'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this user from the project?')">
                                            <input type="hidden" name="employee_id" value="<?= htmlspecialchars($user['employee_id']) ?>">
                                            <button type="submit" name="remove_user" class="btn-remove">
                                                <i class="fas fa-user"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-plus"></i>
                                <p>No users assigned to this project yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle select all functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const assignBtn = document.getElementById('assignBtn');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                userCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateAssignButton();
            });
        }

        // Handle individual checkbox changes
        userCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectAllState();
                updateAssignButton();
            });
        });

        function updateSelectAllState() {
            if (selectAllCheckbox) {
                const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
                const totalCount = userCheckboxes.length;
                
                selectAllCheckbox.checked = checkedCount === totalCount;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            }
        }

        function updateAssignButton() {
            if (assignBtn) {
                const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
                assignBtn.disabled = checkedCount === 0;
                
                if (checkedCount === 0) {
                    assignBtn.innerHTML = '<i class="fas fa-user-plus"></i> Assign Selected Users';
                } else {
                    assignBtn.innerHTML = `<i class="fas fa-user-plus"></i> Assign ${checkedCount} User${checkedCount > 1 ? 's' : ''}`;
                }
            }
        }

        // Form submission confirmation
        document.getElementById('assignForm')?.addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert('Please select at least one user to assign.');
                return false;
            }
            
            if (!confirm(`Are you sure you want to assign ${checkedCount} user${checkedCount > 1 ? 's' : ''} to this project?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Initial state update
        updateSelectAllState();
        updateAssignButton();
    </script>
</body>
</html>