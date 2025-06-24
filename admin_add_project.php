<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $status = 'pending'; // Default status
    
    $query = "INSERT INTO projects (name, description, category, due_date, status, created_at) 
              VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssss", $name, $description, $category, $due_date, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: admin_dashboard.php?success=Project added successfully");
        exit();
    } else {
        $error = "Error adding project: " . mysqli_error($conn);
    }
}

// Get type from URL parameter if set
$type = isset($_GET['type']) ? $_GET['type'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Project - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body{
            overflow: scroll;
        }

        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
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

        .autonomous-hero {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            gap: 4rem;
            padding: 2rem 0;
        }

        .autonomous-title h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin: 0;
            color: #181820;
            letter-spacing: -2px;
        }

        .autonomous-description {
            max-width: 600px;
            color: #555;
            font-size: 1.15rem;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <h1><i class="fas fa-plus"></i> Add New Project</h1>
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
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>


            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Project Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="category">Project Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="UGV" <?= $type == 'UGV' ? 'selected' : '' ?>>UGV (Unmanned Ground Vehicle)</option>
                        <option value="UAV" <?= $type == 'UAV' ? 'selected' : '' ?>>UAV (Unmanned Aerial Vehicle)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Project Description *</label>
                    <textarea id="description" name="description" required placeholder="Enter detailed description of the project..."></textarea>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date *</label>
                    <input type="date" id="due_date" name="due_date" required min="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>