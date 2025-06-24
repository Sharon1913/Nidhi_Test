<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($project_id === 0) {
    header("Location: superadmin_dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id_post = intval($_POST['project_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $assigned_users = isset($_POST['users']) ? $_POST['users'] : [];

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Update project name
        $query_update = "UPDATE projects SET name = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $query_update);
        mysqli_stmt_bind_param($stmt_update, "si", $name, $project_id_post);
        mysqli_stmt_execute($stmt_update);
        mysqli_stmt_close($stmt_update);

        // Delete existing assignments
        $query_delete = "DELETE FROM project_assignments WHERE project_id = ?";
        $stmt_delete = mysqli_prepare($conn, $query_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $project_id_post);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);

        // Insert new assignments
        $query_insert = "INSERT INTO project_assignments (project_id, employee_id) VALUES (?, ?)";
        $stmt_insert = mysqli_prepare($conn, $query_insert);
        foreach ($assigned_users as $user_id) {
            $user_id_int = intval($user_id);
            mysqli_stmt_bind_param($stmt_insert, "ii", $project_id_post, $user_id_int);
            mysqli_stmt_execute($stmt_insert);
        }
        mysqli_stmt_close($stmt_insert);

        mysqli_commit($conn);
        header("Location: superadmin_dashboard.php?success=Project updated successfully");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Error updating project: " . $e->getMessage();
    }
}

// Fetch project details
$query = "SELECT * FROM projects WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$project) {
    header("Location: superadmin_dashboard.php");
    exit();
}

// Fetch all users
$users_query = "SELECT id, employee_id, name FROM users WHERE role = 'user'";
$users_result = mysqli_query($conn, $users_query);
$users = [];
while ($row = mysqli_fetch_assoc($users_result)) {
    $users[] = $row;
}

// Fetch assigned users
$assigned_users_query = "SELECT employee_id FROM project_assignments WHERE project_id = ?";
$stmt_assigned = mysqli_prepare($conn, $assigned_users_query);
mysqli_stmt_bind_param($stmt_assigned, "i", $project_id);
mysqli_stmt_execute($stmt_assigned);
$assigned_users_result = mysqli_stmt_get_result($stmt_assigned);
$assigned_user_ids = [];
while ($row = mysqli_fetch_assoc($assigned_users_result)) {
    $assigned_user_ids[] = $row['employee_id'];
}
mysqli_stmt_close($stmt_assigned);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - Superadmin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .user-checkbox-group { max-height: 200px; overflow-y: auto; border: 2px solid #e2e8f0; border-radius: 8px; padding: 1rem; }
        .user-checkbox-item { display: block; margin-bottom: 0.75rem; }
        .user-checkbox-item label { display: inline-flex; align-items: center; cursor: pointer; font-weight: 500; }
        .user-checkbox-item input { width: auto; margin-right: 0.75rem; }
        .form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.3s ease; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-secondary { background: #e2e8f0; color: #4a5568; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
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
                <h1><i class="fas fa-edit"></i> Edit Project</h1>
                <div class="header-actions">
                    <a href="superadmin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="superadmin_logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>

        <div class="form-container">
            <a href="superadmin_dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="project_id" value="<?= $project['id'] ?>">

                <div class="form-group">
                    <label for="name">Project Name *</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($project['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="users">Assign Users</label>
                    <div class="user-checkbox-group">
                        <?php foreach($users as $user): ?>
                            <div class="user-checkbox-item">
                                <label>
                                    <input type="checkbox" name="users[]" value="<?= $user['id'] ?>" <?= in_array($user['id'], $assigned_user_ids) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($user['name'] ?? '') ?> (<?= htmlspecialchars($user['employee_id'] ?? '') ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="superadmin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 