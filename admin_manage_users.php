<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle user assignment to project
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_user'])) {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    
    // Check if assignment already exists
    $check_query = "SELECT * FROM project_assignments WHERE employee_id = ? AND project_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
<<<<<<< HEAD
    mysqli_stmt_bind_param($check_stmt, "si", $user_id, $project_id);
=======
    mysqli_stmt_bind_param($check_stmt, "si", $employee_id, $project_id);
>>>>>>> origin/rel-code
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) == 0) {
        $assign_query = "INSERT INTO project_assignments (employee_id, project_id, assigned_at) VALUES (?, ?, NOW())";
        $assign_stmt = mysqli_prepare($conn, $assign_query);
        mysqli_stmt_bind_param($assign_stmt, "si", $employee_id, $project_id);
        
        if (mysqli_stmt_execute($assign_stmt)) {
            $success = "User assigned to project successfully!";
        } else {
            $error = "Error assigning user to project.";
        }
    } else {
        $error = "User is already assigned to this project.";
    }
}

<<<<<<< HEAD
// Fetch all users
$users_query = "SELECT u.*, COUNT(pa.project_id) as project_count 
                FROM users u 
                LEFT JOIN project_assignments pa ON u.employee_id = pa.employee_id 
                WHERE u.role = 'user' 
                GROUP BY u.id 
                ORDER BY u.email";
=======
// Handle search for users
$search_term = '';
if (isset($_GET['search_user'])) {
    $search_term = mysqli_real_escape_string($conn, $_GET['search_user']);
    $users_query = "SELECT u.*, COUNT(pa.project_id) as project_count 
                    FROM users u 
                    LEFT JOIN project_assignments pa ON u.employee_id = pa.employee_id 
                    WHERE u.role = 'user' 
                    AND u.email LIKE '%$search_term%'
                    GROUP BY u.id 
                    ORDER BY u.email";
} else {
    $users_query = "SELECT u.*, COUNT(pa.project_id) as project_count 
                    FROM users u 
                    LEFT JOIN project_assignments pa ON u.employee_id = pa.employee_id 
                    WHERE u.role = 'user' 
                    GROUP BY u.id 
                    ORDER BY u.email";
}
>>>>>>> origin/rel-code
$users_result = mysqli_query($conn, $users_query);

// Fetch all projects for assignment dropdown
$projects_query = "SELECT * FROM projects ORDER BY name";
$projects_result = mysqli_query($conn, $projects_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .users-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
<<<<<<< HEAD
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .user-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .user-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.25rem;
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
        
        .user-stats {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .stat-item:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            color: #4a5568;
            font-size: 0.9rem;
        }
        
        .stat-value {
            font-weight: 600;
=======
        body {
            overflow: auto;
        }

        .user-list {
            margin-top: 2rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .user-row {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .user-row:last-child {
            border-bottom: none;
        }
        
        .user-email {
            flex: 1;
>>>>>>> origin/rel-code
            color: #2d3748;
        }
        
        .user-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-assign {
            background: #48bb78;
            color: white;
        }
        
        .btn-small:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .assign-form {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
<<<<<<< HEAD
=======
            position: relative;
>>>>>>> origin/rel-code
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
<<<<<<< HEAD
=======
            position: relative;
>>>>>>> origin/rel-code
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
<<<<<<< HEAD
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
=======
            box-shadow: 0 0 0ग3px rgba(102, 126, 234, 0.1);
        }
        
        .searchable-select {
            position: relative;
            display: block; /* Ensure it takes the full width and behaves in the flow */
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .select-options {
            /* Removed absolute positioning to keep it in the document flow */
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            display: none; /* Hidden by default */
            margin-top: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .select-options.show {
            display: block; /* Show in the normal flow when active */
        }
        
        .select-option {
            padding: 0.75rem;
            cursor: pointer;
        }
        
        .select-option:hover {
            background: #f8f9fa;
>>>>>>> origin/rel-code
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
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
<<<<<<< HEAD
=======
            margin-top: 1rem; /* Adjusted to avoid excessive spacing */
>>>>>>> origin/rel-code
        }
        
        .section-header h2 {
            color: #2d3748;
            margin: 0;
        }
        
        .user-count {
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
<<<<<<< HEAD
=======
        
        .search-form {
            margin-bottom: 1rem;
        }
        
        .search-form input {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            width: 300px;
        }
>>>>>>> origin/rel-code
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-users"></i> Manage Users</h1>
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

        <div class="users-container">
            <a href="admin_dashboard.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Assign User to Project Form -->
            <div class="assign-form">
                <h3><i class="fas fa-user-plus"></i> Assign User to Project</h3>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employee_id">Select User</label>
<<<<<<< HEAD
                            <select id="employee_id" name="employee_id" required>
                                <option value="">Choose User</option>
                                <?php 
                                mysqli_data_seek($users_result, 0);
                                while($user = mysqli_fetch_assoc($users_result)): 
                                ?>
                                    <option value="<?= $user['employee_id'] ?>"><?= htmlspecialchars($user['email']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="project_id">Select Project</label>
                            <select id="project_id" name="project_id" required>
                                <option value="">Choose Project</option>
                                <?php while($project = mysqli_fetch_assoc($projects_result)): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?> (<?= $project['category'] ?>)</option>
                                <?php endwhile; ?>
                            </select>
=======
                            <div class="searchable-select">
                                <input type="text" class="search-input" placeholder="Search users..." id="user-search">
                                <input type="hidden" name="employee_id" id="employee_id">
                                <div class="select-options" id="user-options">
                                    <?php 
                                    mysqli_data_seek($users_result, 0);
                                    while($user = mysqli_fetch_assoc($users_result)): 
                                    ?>
                                        <div class="select-option" data-value="<?= $user['employee_id'] ?>">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="project_id">Select Project</label>
                            <div class="searchable-select">
                                <input type="text" class="search-input" placeholder="Search projects..." id="project-search">
                                <input type="hidden" name="project_id" id="project_id">
                                <div class="select-options" id="project-options">
                                    <?php while($project = mysqli_fetch_assoc($projects_result)): ?>
                                        <div class="select-option" data-value="<?= $project['id'] ?>">
                                            <?= htmlspecialchars($project['name']) ?> (<?= $project['category'] ?>)
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
>>>>>>> origin/rel-code
                        </div>
                        <button type="submit" name="assign_user" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Assign
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users List -->
            <div class="section-header">
                <h2>All Users</h2>
                <span class="user-count"><?= mysqli_num_rows($users_result) ?> Users</span>
            </div>

<<<<<<< HEAD
            <div class="users-grid">
=======
            <div class="search-form">
                <form method="GET" action="">
                    <input type="text" name="search_user" placeholder="Search users by email..." value="<?= htmlspecialchars($search_term) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <div class="user-list">
>>>>>>> origin/rel-code
                <?php 
                mysqli_data_seek($users_result, 0);
                while($user = mysqli_fetch_assoc($users_result)): 
                ?>
<<<<<<< HEAD
                    <div class="user-card">
                        <div class="user-header">
                            <div class="user-avatar">
                                <?= strtoupper(substr($user['email'], 0, 2)) ?>
                            </div>
                            <div class="user-info">
                                <h3><?= htmlspecialchars($user['email']) ?></h3>
                                <p>Employee ID: <?= htmlspecialchars($user['employee_id']) ?></p>
                            </div>
                        </div>

                        <div class="user-stats">
                            <div class="stat-item">
                                <span class="stat-label">Projects Assigned:</span>
                                <span class="stat-value"><?= $user['project_count'] ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Account Status:</span>
                                <span class="stat-value" style="color: #38a169;">Active</span>
                            </div>
                        </div>

=======
                    <div class="user-row">
                        <div class="user-email"><?= htmlspecialchars($user['email']) ?></div>
>>>>>>> origin/rel-code
                        <div class="user-actions">
                            <a href="admin_user_tasks.php?user_id=<?= $user['id'] ?>" class="btn-small btn-view">
                                <i class="fas fa-eye"></i> View Tasks
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
<<<<<<< HEAD
=======

    <script>
        // Searchable dropdown for users
        const userSearch = document.getElementById('user-search');
        const userOptions = document.getElementById('user-options');
        const userInput = document.getElementById('employee_id');
        const userOptionItems = userOptions.getElementsByClassName('select-option');

        userSearch.addEventListener('focus', () => {
            userOptions.classList.add('show');
        });

        userSearch.addEventListener('input', () => {
            const filter = userSearch.value.toLowerCase();
            for (let option of userOptionItems) {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(filter) ? '' : 'none';
            }
            userOptions.classList.add('show');
        });

        for (let option of userOptionItems) {
            option.addEventListener('click', () => {
                userSearch.value = option.textContent;
                userInput.value = option.dataset.value;
                userOptions.classList.remove('show');
            });
        }

        // Searchable dropdown for projects
        const projectSearch = document.getElementById('project-search');
        const projectOptions = document.getElementById('project-options');
        const projectInput = document.getElementById('project_id');
        const projectOptionItems = projectOptions.getElementsByClassName('select-option');

        projectSearch.addEventListener('focus', () => {
            projectOptions.classList.add('show');
        });

        projectSearch.addEventListener('input', () => {
            const filter = projectSearch.value.toLowerCase();
            for (let option of projectOptionItems) {
                const text = option.textContent.toColorCase();
                option.style.display = text.includes(filter) ? '' : 'none';
            }
            projectOptions.classList.add('show');
        });

        for (let option of projectOptionItems) {
            option.addEventListener('click', () => {
                projectSearch.value = option.textContent;
                projectInput.value = option.dataset.value;
                projectOptions.classList.remove('show');
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!userSearch.contains(e.target) && !userOptions.contains(e.target)) {
                userOptions.classList.remove('show');
            }
            if (!projectSearch.contains(e.target) && !projectOptions.contains(e.target)) {
                projectOptions.classList.remove('show');
            }
        });
    </script>
>>>>>>> origin/rel-code
</body>
</html>