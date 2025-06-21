<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is superadmin
if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

// Get project ID and employee ID from URL parameters
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';

// Initialize variables
$success_message = '';
$error_message = '';
$projects = [];
$employees = [];

// Fetch all projects for dropdown
$projects_query = "SELECT id, name, category FROM projects ORDER BY name";
$projects_result = mysqli_query($conn, $projects_query);
while ($project = mysqli_fetch_assoc($projects_result)) {
    $projects[] = $project;
}

// Fetch all employees for dropdown, excluding superadmins
$employees_query = "SELECT employee_id, email FROM users WHERE role != 'superadmin' ORDER BY email";
$employees_result = mysqli_query($conn, $employees_query);
while ($employee = mysqli_fetch_assoc($employees_result)) {
    $employees[] = $employee;
}

// Get project details if project_id is provided
$selected_project = null;
if ($project_id > 0) {
    $project_query = "SELECT * FROM projects WHERE id = ?";
    $stmt = mysqli_prepare($conn, $project_query);
    mysqli_stmt_bind_param($stmt, "i", $project_id);
    mysqli_stmt_execute($stmt);
    $project_result = mysqli_stmt_get_result($stmt);
    $selected_project = mysqli_fetch_assoc($project_result);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_title = trim($_POST['task_title']);
    $task_description = trim($_POST['task_description']);
    $task_project_id = intval($_POST['project_id']);
    $task_employee_id = trim($_POST['employee_id']);
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $remarks = trim($_POST['remarks']);
    
    // Validation
    if (empty($task_title) || empty($task_description) || $task_project_id == 0 || empty($task_employee_id) || empty($due_date)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Verify employee exists and is assigned to project
        $verify_assignment = "SELECT COUNT(*) as count FROM project_assignments WHERE project_id = ? AND employee_id = ?";
        $stmt = mysqli_prepare($conn, $verify_assignment);
        mysqli_stmt_bind_param($stmt, "is", $task_project_id, $task_employee_id);
        mysqli_stmt_execute($stmt);
        $verify_result = mysqli_stmt_get_result($stmt);
        $assignment_check = mysqli_fetch_assoc($verify_result);
        
        if ($assignment_check['count'] == 0) {
            // Auto-assign employee to project if not already assigned
            $assign_query = "INSERT INTO project_assignments (project_id, employee_id, assigned_at VALUES (?, ?, NOW())";
            $stmt = mysqli_prepare($conn, $assign_query);
            mysqli_stmt_bind_param($stmt, "is", $task_project_id, $task_employee_id);
            mysqli_stmt_execute($stmt);
        }
        
        // Insert task
        $insert_query = "INSERT INTO tasks (title, description, project_id, employee_id, due_date, priority, status, assigned_date, remarks, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), ?, NOW())";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "ssissss", $task_title, $task_description, $task_project_id, $task_employee_id, $due_date, $priority, $remarks);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Task assigned successfully!";
            // Clear form data
            $task_title = $task_description = $due_date = $priority = $remarks = '';
            if (!$project_id) $task_project_id = 0;
            if (!$employee_id) $task_employee_id = '';
        } else {
            $error_message = "Error assigning task: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Task - Super Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .assign-task-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h1 {
            color: #2d3748;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #718096;
            font-size: 1.1rem;
        }
        
        .project-context {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            margin-bottom: 2rem;
        }
        
        .project-context h4 {
            color: #2d3748;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .project-context p {
            color: #4a5568;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2d3748;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .required {
            color: #e53e3e;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .priority-options {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .priority-option {
            flex: 1;
        }
        
        .priority-option input[type="radio"] {
            display: none;
        }
        
        .priority-option label {
            display: block;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .priority-option input[type="radio"]:checked + label {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        .priority-high label { border-color: #fed7d7; color: #c53030; }
        .priority-high input[type="radio"]:checked + label { background: #e53e3e; border-color: #e53e3e; }
        
        .priority-medium label { border-color: #feebc8; color: #c05621; }
        .priority-medium input[type="radio"]:checked + label { background: #ed8936; border-color: #ed8936; }
        
        .priority-low label { border-color: #c6f6d5; color: #22543d; }
        .priority-low input[type="radio"]:checked + label { background: #38a169; border-color: #38a169; }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
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
        
        .employee-info {
            background: #f0f4f8;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #4a5568;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .priority-options {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-tasks"></i> Assign Task</h1>
                <div class="header-actions">
                    <a href="superadmin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="admin_logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="assign-task-container">
            <!-- Back Button -->
            <?php if ($project_id > 0): ?>
                <a href="superadmin_project_details.php?id=<?= $project_id ?>" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Project
                </a>
            <?php else: ?>
                <a href="superadmin_dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            <?php endif; ?>

            <div class="form-container">
                <div class="form-header">
                    <h1><i class="fas fa-plus-circle"></i> Assign New Task</h1>
                    <p>Create and assign a task to a team member</p>
                </div>

                <?php if ($selected_project): ?>
                    <div class="project-context">
                        <h4>
                            <i class="fas fa-<?= $selected_project['category'] == 'UGV' ? 'car' : 'helicopter' ?>"></i>
                            Project Context
                        </h4>
                        <p><?= htmlspecialchars($selected_project['name']) ?> (<?= $selected_project['category'] ?>)</p>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success_message ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="task_title">Task Title <span class="required">*</span></label>
                        <input type="text" id="task_title" name="task_title" class="form-control" 
                               value="<?= isset($task_title) ? htmlspecialchars($task_title) : '' ?>" 
                               placeholder="Enter task title" required>
                    </div>

                    <div class="form-group">
                        <label for="task_description">Task Description <span class="required">*</span></label>
                        <textarea id="task_description" name="task_description" class="form-control" 
                                  placeholder="Describe the task in detail" required><?= isset($task_description) ? htmlspecialchars($task_description) : '' ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="project_id">Project <span class="required">*</span></label>
                            <select id="project_id" name="project_id" class="form-control" required>
                                <option value="">Select Project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>" 
                                            <?= ($project_id == $project['id'] || (isset($task_project_id) && $task_project_id == $project['id'])) ? 'selected' : '' ?>>
                                        [<?= $project['category'] ?>] <?= htmlspecialchars($project['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="employee_id">Assign To <span class="required">*</span></label>
                            <select id="employee_id" name="employee_id" class="form-control" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>" 
                                            <?= ($employee_id == $emp['employee_id'] || (isset($task_employee_id) && $task_employee_id == $emp['employee_id'])) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['email']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="due_date">Due Date <span class="required">*</span></label>
                            <input type="date" id="due_date" name="due_date" class="form-control" 
                                   value="<?= isset($due_date) ? $due_date : '' ?>" 
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Priority <span class="required">*</span></label>
                            <div class="priority-options">
                                <div class="priority-option priority-high">
                                    <input type="radio" id="priority_high" name="priority" value="high" 
                                           <?= (isset($priority) && $priority == 'high') ? 'checked' : '' ?> required>
                                    <label for="priority_high">
                                        <i class="fas fa-exclamation-triangle"></i> High
                                    </label>
                                </div>
                                <div class="priority-option priority-medium">
                                    <input type="radio" id="priority_medium" name="priority" value="medium" 
                                           <?= (isset($priority) && $priority == 'medium') || !isset($priority) ? 'checked' : '' ?> required>
                                    <label for="priority_medium">
                                        <i class="fas fa-minus-circle"></i> Medium
                                    </label>
                                </div>
                                <div class="priority-option priority-low">
                                    <input type="radio" id="priority_low" name="priority" value="low" 
                                           <?= (isset($priority) && $priority == 'low') ? 'checked' : '' ?> required>
                                    <label for="priority_low">
                                        <i class="fas fa-arrow-down"></i> Low
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="remarks">Additional Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" 
                                  placeholder="Any additional notes or instructions (optional)"><?= isset($remarks) ? htmlspecialchars($remarks) : '' ?></textarea>
                    </div>

                    <div class="form-actions">
                        <?php if ($project_id > 0): ?>
                            <a href="superadmin_project_details.php?id=<?= $project_id ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php else: ?>
                            <a href="superadmin_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Assign Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-populate employee info when selected
        document.getElementById('employee_id').addEventListener('change', function() {
            const selectedEmail = this.options[this.selectedIndex].text;
            const existingInfo = document.querySelector('.employee-info');
            
            if (existingInfo) {
                existingInfo.remove();
            }
            
            if (this.value) {
                const infoDiv = document.createElement('div');
                infoDiv.className = 'employee-info';
                infoDiv.innerHTML = `<i class="fas fa-user"></i> Selected: ${selectedEmail}`;
                this.parentNode.appendChild(infoDiv);
            }
        });

        // Set minimum date to today
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const dueDate = new Date(document.getElementById('due_date').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (dueDate < today) {
                e.preventDefault();
                alert('Due date cannot be in the past.');
                return false;
            }
        });

        // Auto-resize textarea
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>
</body>
</html>