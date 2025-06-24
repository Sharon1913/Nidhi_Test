<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if ($task_id === 0) {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_id_post = intval($_POST['task_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $query_update = "UPDATE tasks SET title = ?, description = ?, due_date = ?, status = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $query_update);
    mysqli_stmt_bind_param($stmt_update, "ssssi", $title, $description, $due_date, $status, $task_id_post);
    if (mysqli_stmt_execute($stmt_update)) {
        mysqli_stmt_close($stmt_update);
        header("Location: admin_dashboard.php?success=Task updated successfully");
        exit();
    } else {
        $error = "Error updating task.";
    }
}

// Fetch task details
$query = "SELECT * FROM tasks WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $task_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$task = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$task) {
    header("Location: admin_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container { max-width: 600px; margin: 2rem auto; padding: 2rem; background: rgba(255,255,255,0.95); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-error { background: #fed7d7; color: #c53030; border: 1px solid #feb2b2; }
        .back-button { display: inline-flex; align-items: center; gap: 0.5rem; color: #667eea; text-decoration: none; font-weight: 500; margin-bottom: 2rem; transition: all 0.3s ease; }
        .back-button:hover { color: #764ba2; transform: translateX(-2px); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-edit"></i> Edit Task</h1>
                <div class="header-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="admin_logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>
        <div class="form-container">
            <a href="admin_dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                <div class="form-group">
                    <label for="title">Task Title *</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($task['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?= htmlspecialchars($task['description']) ?></textarea>
                </div>
                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" value="<?= htmlspecialchars($task['due_date']) ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="in_progress" <?= $task['status'] == 'in_progress' || $task['status'] == 'pending' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $task['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="form-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 