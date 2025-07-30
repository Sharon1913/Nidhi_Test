<?php
// Ensure session persistence
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'user' || !isset($_SESSION['employee_id'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("127.0.0.1", "root", "my-secret-pw", "tihan_project_management", 3307);

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error, 0);
    die("Connection failed: " . $conn->connect_error);
}  

$employee_id = $_SESSION['employee_id'];

// Handle marking a single notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_single_notification_read' && isset($_POST['notification_id'])) {
    ob_start(); // Start output buffering
    $notification_id = intval($_POST['notification_id']);
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND recipient_role = 'user'");
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read: ' . $stmt->error]);
    }
    
    $stmt->close();
    exit();
}


$conn = new mysqli("127.0.0.1", "root", "my-secret-pw", "tihan_project_management", 3307);

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error, 0);
    die("Connection failed: " . $conn->connect_error);
}

$email = $_SESSION['email'];
$employee_id = $_SESSION['employee_id'];

// Fetch user profile
$stmt = $conn->prepare("SELECT first_name, last_name, profile_picture, is_profile_updated FROM users WHERE employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$user_profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Initialize profile variables and username
$first_name = $user_profile['first_name'] ?? '';
$last_name = $user_profile['last_name'] ?? '';
$profile_picture = $user_profile['profile_picture'] ?? '';
$is_profile_updated = $user_profile['is_profile_updated'] ?? 0;
$username = str_replace(' ', '_', strtolower($first_name . '_' . $last_name));

// Handle profile update
$profile_error = $profile_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    // Handle profile picture upload
    $new_profile_picture = $profile_picture;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['size'] > 0) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['jpg', 'jpeg', 'png'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_types)) {
            $profile_error = "Only JPG, JPEG, and PNG files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $profile_error = "Profile picture size exceeds 2MB limit.";
        } else {
            $target_dir = "Uploads/profile_pictures/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $new_profile_picture = $target_dir . time() . "_" . $username . "_" . basename($file["name"]);
            if (!move_uploaded_file($file["tmp_name"], $new_profile_picture)) {
                $profile_error = "Error uploading profile picture.";
                $new_profile_picture = $profile_picture;
            }
        }
    }
    
    if (!$profile_error) {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, profile_picture = ?, is_profile_updated = 1 WHERE employee_id = ? AND email = ?");
        $stmt->bind_param("sssss", $first_name, $last_name, $new_profile_picture, $employee_id, $email);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $profile_success = "Profile updated successfully.";
                $profile_picture = $new_profile_picture;
                $is_profile_updated = 1;
                $username = str_replace(' ', '_', strtolower($first_name . '_' . $last_name));
            } else {
                $profile_error = "No profile updated. Please check if your employee ID and email are correct.";
            }
        } else {
            $profile_error = "Failed to update profile: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle password change
$password_error = $password_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $conn->prepare("SELECT password FROM users WHERE employee_id = ?");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stored_password = $result['password'];
    $stmt->close();
    
    if (!password_verify($current_password, $stored_password)) {
        $password_error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $password_error = "New password must be at least 8 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE employee_id = ?");
        $stmt->bind_param("ss", $hashed_password, $employee_id);
        if ($stmt->execute()) {
            $password_success = "Password changed successfully.";
        } else {
            $password_error = "Failed to change password.";
        }
        $stmt->close();
    }
}

$task_filter = isset($_GET['task_filter']) ? $_GET['task_filter'] : 'all';
$task_query = "SELECT t.*, p.name as project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.employee_id = ?";
if ($task_filter !== 'all') {
    $task_query .= " AND t.status = ?";
}
$stmt = $conn->prepare($task_query);
if ($task_filter === 'all') {
    $stmt->bind_param("s", $employee_id);
} else {
    $stmt->bind_param("ss", $employee_id, $task_filter);
}
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

// Handle file upload or drive link with status update
$upload_error = $upload_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_type'])) {
    $task_id = $_POST['task_id'];
    $upload_type = $_POST['upload_type'];
    $task_status = $_POST['task_status'];
    $file_description = $upload_type === 'other' ? trim($_POST['file_description']) : null;
    $drive_link = isset($_POST['drive_link']) ? trim($_POST['drive_link']) : null;
    $file_path = null;

    $valid_upload_types = ['weekly_report', 'monthly_report', 'other', 'final_project'];
    if (!in_array($upload_type, $valid_upload_types)) {
        $upload_error = "Invalid upload type.";
    } else {
        if ($upload_type === 'final_project' && isset($_FILES['file']) && $_FILES['file']['size'] > 0) {
            $file = $_FILES['file'];
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'mp4', 'mov', 'avi'];
            $max_size = in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['mp4', 'mov', 'avi']) ? 50 * 1024 * 1024 : 10 * 1024 * 1024;

            if (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowed_extensions)) {
                $upload_error = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
            } elseif ($file['size'] > $max_size) {
                $upload_error = "File size exceeds limit (" . ($max_size / (1024 * 1024)) . "MB).";
            } else {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }
                $file_path = $target_dir . time() . "_" . $username . "_" . basename($file["name"]);
                if (!move_uploaded_file($file["tmp_name"], $file_path)) {
                    $upload_error = "Error moving uploaded file to server.";
                    $file_path = null;
                }
            }
        } elseif (in_array($upload_type, ['weekly_report', 'monthly_report', 'other']) && !empty($drive_link)) {
            // Drive link is provided, no file_path needed
        } else {
            $upload_error = $upload_type === 'final_project' ? "Please upload a file for Final Project." : "Please provide a valid drive link.";
        }

        if (!$upload_error) {
            $stmt = $conn->prepare("INSERT INTO file_uploads (task_id, employee_id, file_path, drive_link, upload_type, file_description, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssss", $task_id, $employee_id, $file_path, $drive_link, $upload_type, $file_description);
            if ($stmt->execute()) {
                $upload_success = $upload_type === 'final_project' ? "File uploaded successfully." : "Drive link submitted successfully.";
                error_log("Upload saved: task_id=$task_id, employee_id=$employee_id, file_path=$file_path, drive_link=$drive_link, upload_type=$upload_type, file_description=$file_description");
            } else {
                $upload_error = "Failed to save to database: " . $stmt->error;
                error_log("Upload failed: " . $stmt->error);
            }
            
            $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $task_status, $task_id);
            $stmt->execute();

            $stmt = $conn->prepare("SELECT project_id FROM tasks WHERE id = ?");
            $stmt->bind_param("i", $task_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $project_id = $result['project_id'];

            $message = "New upload for Task ID {$task_id} by {$employee_id} (Status: " . ucfirst($task_status) . "). Please review.";
            $stmt = $conn->prepare("INSERT INTO notifications (recipient_role, project_id, task_id, message, uploaded_at) VALUES ('admin', ?, ?, ?, NOW())");
            $stmt->bind_param("iis", $project_id, $task_id, $message);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Fetch projects and tasks
$stmt = $conn->prepare("SELECT p.* FROM projects p JOIN project_assignments pa ON p.id = pa.project_id WHERE pa.employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$projects = $stmt->get_result();
$stmt->close();

$stmt = $conn->prepare("SELECT t.*, p.name as project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.employee_id = ?");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$tasks = $stmt->get_result();
$stmt->close();

// Fetch notifications for the user
$stmt = $conn->prepare("
    SELECT n.id, n.project_id, n.task_id, n.message, n.uploaded_at, 
           p.name AS project_name, t.title AS task_title
    FROM notifications n
    JOIN projects p ON n.project_id = p.id
    JOIN tasks t ON n.task_id = t.id
    WHERE n.recipient_role = 'user' AND t.employee_id = ? AND n.is_read = FALSE
    ORDER BY n.uploaded_at DESC");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$notifications_result = $stmt->get_result();
$stmt->close();

$total_tasks = 0;
$completed_tasks = 0;
$pending_tasks = 0;
$delayed_tasks = 0;
$today = date('Y-m-d');

$tasks->data_seek(0);
while ($task = $tasks->fetch_assoc()) {
    $total_tasks++;
    if ($task['status'] == 'completed') {
        $completed_tasks++;
    } elseif ($task['due_date'] < $today && $task['status'] != 'completed') {
        $delayed_tasks++;
        $stmt = $conn->prepare("UPDATE tasks SET status = 'delayed' WHERE id = ?");
        $stmt->bind_param("i", $task['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $pending_tasks++;
    }
}

$remarks_error = $remarks_success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remarks'])) {
    $task_id = $_POST['task_id'];
    $remarks = trim($_POST['remarks']);
    $stmt = $conn->prepare("UPDATE tasks SET remarks = ?, status = 'delayed' WHERE id = ? AND employee_id = ?");
    $stmt->bind_param("sis", $remarks, $task_id, $employee_id);
    if ($stmt->execute()) {
        $remarks_success = "Remarks submitted successfully.";
    } else {
        $remarks_error = "Failed to submit remarks.";
    }
    $stmt->close();
}

// Fetch uploaded files history (removed status filter to show all uploads)
$stmt = $conn->prepare("
    SELECT fu.*, t.title as task_title, p.name as project_name 
    FROM file_uploads fu 
    JOIN tasks t ON fu.task_id = t.id 
    JOIN projects p ON t.project_id = p.id 
    WHERE fu.employee_id = ? 
    ORDER BY fu.uploaded_at DESC");
$stmt->bind_param("s", $employee_id);
$stmt->execute();
$upload_history = $stmt->get_result();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TiHAN Project Management</title>
    <link rel="stylesheet" href="user_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/samarkan?styles=6066" rel="stylesheet">
</head>
<body <?php if (!$is_profile_updated) echo 'onload="showProfileModal()"'; ?>>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="assets/images/tihan_logo.webp" alt="TiHAN Logo">
                </div>
                <div>
                    <div style="font-size: 1.65rem; font-family: 'Samarkan', sans-serif; ">NIDHI</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="#dashboard" class="nav-link active" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#projects" class="nav-link" data-section="projects">
                    <i class="fas fa-project-diagram"></i>
                    <span>My Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#tasks" class="nav-link" data-section="tasks">
                    <i class="fas fa-tasks"></i>
                    <span>My Tasks</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#uploads" class="nav-link" data-section="uploads">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Upload Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#remarks" class="nav-link" data-section="remarks">
                    <i class="fas fa-comment-alt"></i>
                    <span>Add Remarks</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#history" class="nav-link" data-section="history">
                    <i class="fas fa-history"></i>
                    <span>Upload History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="user_logout.php" class="nav-link" onclick="window.location.href='index.php'; return false;">
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
                <h1>Welcome<?php echo $first_name ? ', ' . htmlspecialchars($first_name) : ''; ?>!</h1>
                <p>Here's what's happening with your projects today.</p>
            </div>
            <div class="header-right">
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <span class="notification-count"><?php echo $notifications_result->num_rows; ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-avatar" onclick="showProfileModal()">
                    <?php if ($profile_picture): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($email, 0, 2)); ?>
                    <?php endif; ?>
                </div>
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
                                <?= date('M d, Y H:i', strtotime($notification['uploaded_at'])) ?>
                            </span>
                        </div>
                        <div class="notification-message">
                            <?= htmlspecialchars($notification['message']) ?>
                        </div>
                        <button class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="markNotificationAsRead(<?php echo $notification['id']; ?>, this)">
                            OK
                        </button>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i><br>
                    No new notifications
                </div>
            <?php endif; ?>
        </div>

        <!-- Profile Modal -->
        <div class="modal" id="profileModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php echo $is_profile_updated ? 'Your Profile' : 'Complete Your Profile'; ?></h2>
                    <button class="modal-close" onclick="closeProfileModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if ($profile_success) { ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $profile_success; ?>
                        </div>
                    <?php } ?>
                    <?php if ($profile_error) { ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $profile_error; ?>
                        </div>
                    <?php } ?>
                    <?php if ($password_success) { ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $password_success; ?>
                        </div>
                    <?php } ?>
                    <?php if ($password_error) { ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $password_error; ?>
                        </div>
                    <?php } ?>
                    
                    <div class="profile-picture-container">
                        <?php if ($profile_picture): ?>
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" class="profile-picture">
                        <?php else: ?>
                            <div class="profile-picture" style="background: var(--gradient-2); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                <?php echo strtoupper(substr($email, 0, 2)); ?>
                            </div>
                        <?php endif; ?>
                        <button class="btn-primary" onclick="document.getElementById('profilePictureInput').click()">Change Picture</button>
                    </div>
                    
                    <div class="tabs">
                        <div class="tab active" data-tab="profile">Profile</div>
                        <div class="tab" data-tab="password">Change Password</div>
                    </div>
                    
                    <div class="tab-content active" id="profile-tab">
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="file" name="profile_picture" id="profilePictureInput" style="display: none;" accept=".jpg,.jpeg,.png">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($first_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($last_name); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Employee ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee_id); ?>" disabled>
                            </div>
                            <button type="submit" class="btn-primary" id="profileBtn">
                                <span class="btn-text">
                                    <i class="fas fa-save"></i>
                                    Save Profile
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Saving...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                    
                    <div class="tab-content" id="password-tab">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn-primary" id="passwordBtn">
                                <span class="btn-text">
                                    <i class="fas fa-lock"></i>
                                    Change Password
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Changing...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Dashboard Section -->
            <div id="dashboard-section" class="content-section fade-in">
                <div class="stats-grid">
                    <div class="stat-card slide-up" onclick="showTasks('all')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $total_tasks; ?></div>
                        <div class="stat-label">Total Tasks</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 100%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card success slide-up" style="animation-delay: 0.1s;" onclick="showCompletedUploads()">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $completed_tasks; ?></div>
                        <div class="stat-label">Completed</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0; ?>%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card warning slide-up" style="animation-delay: 0.2s;" onclick="showTasks('pending')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $pending_tasks; ?></div>
                        <div class="stat-label">Pending</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($pending_tasks / $total_tasks) * 100 : 0; ?>%;"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card danger slide-up" style="animation-delay: 0.3s;" onclick="showTasks('delayed')">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="stat-number"><?php echo $delayed_tasks; ?></div>
                        <div class="stat-label">Delayed</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $total_tasks > 0 ? ($delayed_tasks / $total_tasks) * 100 : 0; ?>%; background: linear-gradient(135deg, #ff6b6b, #ee5a52);"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Projects Section -->
            <div id="projects-section" class="content-section" style="display: none;">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-project-diagram"></i>
                            My Projects
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($projects->num_rows > 0) { ?>
                            <div class="table-container">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Project Name</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $projects->data_seek(0);
                                        while ($project = $projects->fetch_assoc()) {
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($project['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($project['category']); ?></td>
                                                <td><span class="status-badge status-pending">Active</span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-project-diagram" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <p>No projects assigned yet.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Tasks Section -->
            <div id="tasks-section" class="content-section" style="display: none;">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-tasks"></i>
                            My Tasks<?php if ($task_filter !== 'all') { echo ' - ' . ucfirst($task_filter); } ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($tasks->num_rows > 0) { ?>
                            <div class="table-container">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Task Title</th>
                                            <th>Assigned Date</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $tasks->data_seek(0);
                                        while ($task = $tasks->fetch_assoc()) {
                                            $status_class = 'status-pending';
                                            if ($task['status'] == 'completed') {
                                                $status_class = 'status-completed';
                                            } elseif ($task['status'] == 'delayed') {
                                                $status_class = 'status-delayed';
                                            }
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($task['project_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($task['assigned_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($task['due_date'])); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($task['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($task['remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-tasks" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <p>No <?php echo $task_filter === 'all' ? 'tasks' : $task_filter . ' tasks'; ?> assigned yet.</p>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Upload Section -->
            <div id="uploads-section" class="content-section" style="display: none;">
                <div class="form-section fade-in">
                    <div class="form-header">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Upload Report</h3>
                    </div>
                    <div class="form-body">
                        <?php if ($upload_success) { ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $upload_success; ?>
                            </div>
                        <?php } ?>
                        <?php if ($upload_error) { ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $upload_error; ?>
                            </div>
                        <?php } ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Select Task</label>
                                    <select name="task_id" class="form-control" required>
                                        <option value="">Choose a task...</option>
                                        <?php
                                        $tasks->data_seek(0);
                                        while ($task = $tasks->fetch_assoc()) {
                                            echo "<option value='{$task['id']}'>" . htmlspecialchars($task['title']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Task Status</label>
                                    <select name="task_status" class="form-control" required>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Report Type</label>
                                    <select name="upload_type" id="upload_type" class="form-control" required>
                                        <option value="weekly_report">Weekly Report</option>
                                        <option value="monthly_report">Monthly Report</option>
                                        <option value="other">Other</option>
                                        <option value="final_project">Final Project</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="file_description_group" style="display: none;">
                                    <label class="form-label">File Description</label>
                                    <input type="text" name="file_description" id="file_description" class="form-control" placeholder="Specify file type/purpose">
                                </div>
                                
                                <div class="form-group" id="drive_link_group" style="display: none;">
                                    <label class="form-label">Drive Link</label>
                                    <input type="url" name="drive_link" id="drive_link" class="form-control" placeholder="Paste your editable One Drive link here">
                                    <div class="form-subtext">Upload the file to your One Drive and share an editable link.</div>
                                </div>
                            </div>
                            
                            <div class="file-upload-area" id="file_upload_area" style="display: none;" onclick="document.getElementById('fileInput').click()">
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">Click to upload or drag and drop</div>
                                <div class="file-upload-subtext">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, PNG, JPG, JPEG, MP4, MOV, AVI (Max 10MB, 50MB for videos)</div>
                                <input type="file" name="file" id="fileInput" style="display: none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.mp4,.mov,.avi">
                            </div>
                            
                            <button type="submit" class="btn-primary" id="uploadBtn">
                                <span class="btn-text">
                                    <i class="fas fa-upload"></i>
                                    Submit
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Submitting...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Remarks Section -->
            <div id="remarks-section" class="content-section" style="display: none;">
                <div class="form-section fade-in">
                    <div class="form-header">
                        <i class="fas fa-comment-alt"></i>
                        <h3>Add Remarks for Delay</h3>
                    </div>
                    <div class="form-body">
                        <?php if ($remarks_success) { ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $remarks_success; ?>
                            </div>
                        <?php } ?>
                        <?php if ($remarks_error) { ?>
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $remarks_error; ?>
                            </div>
                        <?php } ?>
                        
                        <form method="POST" id="remarksForm">
                            <div class="form-group">
                                <label class="form-label">Select Task</label>
                                <select name="task_id" class="form-control" required>
                                    <option value="">Choose a task...</option>
                                    <?php
                                    $tasks->data_seek(0);
                                    while ($task = $tasks->fetch_assoc()) {
                                        echo "<option value='{$task['id']}'>" . htmlspecialchars($task['title']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Reason for Delay</label>
                                <textarea name="remarks" class="form-control" rows="5" placeholder="Please explain the reason for the delay..." required style="resize: vertical; min-height: 120px;"></textarea>
                            </div>
                            
                            <button type="submit" class="btn-primary" id="remarksBtn">
                                <span class="btn-text">
                                    <i class="fas fa-paper-plane"></i>
                                    Submit Remarks
                                </span>
                                <div class="loading" style="display: none;">
                                    <div class="spinner"></div>
                                    <span>Submitting...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Upload History Section -->
            <div id="history-section" class="content-section" style="display: none;">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            Upload History
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($upload_history->num_rows > 0) { ?>
                            <div class="table-container">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Task</th>
                                            <th>File Name/Link</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Upload Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($upload = $upload_history->fetch_assoc()) { ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($upload['project_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($upload['task_title']); ?></td>
                                                <td>
                                                    <?php if ($upload['drive_link']) { ?>
                                                        <a href="<?php echo htmlspecialchars($upload['drive_link']); ?>" target="_blank">View Drive Link</a>
                                                    <?php } elseif ($upload['file_path']) { ?>
                                                        <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                                    <?php } else { ?>
                                                        -
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $upload['upload_type'])); ?></td>
                                                <td><?php echo htmlspecialchars($upload['file_description'] ?? '-'); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($upload['uploaded_at'])); ?></td>
                                                <td>
                                                    <?php if ($upload['drive_link']) { ?>
                                                        <a href="<?php echo htmlspecialchars($upload['drive_link']); ?>" class="btn-view" style="padding: 0.5rem 1rem;" target="_blank">View</a>
                                                    <?php } elseif ($upload['file_path']) { ?>
                                                        <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" class="btn-view" style="padding: 0.5rem 1rem;" download>Download</a>
                                                    <?php } else { ?>
                                                        -
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div style="text-align: center; padding: 3rem; color: var(--gray);">
                                <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                <p>No files uploaded yet.</p>
                            </div>
                        <?php } ?>
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
                <!-- </div> -->
            </div>
        </div>
    </div>
<script>
// Notification functionality
window.toggleNotifications = function() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('active');
    
    if (dropdown.classList.contains('active')) {
        fetch('user_dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_viewed'
        });
    }
};

window.markNotificationAsRead = function(notificationId, button) {
    fetch('user_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=mark_single_notification_read¬ification_id=${notificationId}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const notificationItem = document.querySelector(`.notification-item`);
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
            dropdown.classList.remove('active');
        } else {
            alert('Error processing action. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error processing action. Please try again.');
    });
};

// Navigation functionality
document.addEventListener('DOMContentLoaded', function() {
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.content-section');

    function showSection(sectionId) {
        sections.forEach(section => section.style.display = 'none');
        navLinks.forEach(link => link.classList.remove('active'));
        
        const targetSection = document.getElementById(sectionId + '-section');
        if (targetSection) {
            targetSection.style.display = 'block';
            targetSection.classList.add('fade-in');
        }
        
        const targetLink = document.querySelector(`.nav-link[data-section="${sectionId}"]`);
        if (targetLink) {
            targetLink.classList.add('active');
        }
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const sectionId = this.getAttribute('data-section');
            showSection(sectionId);
            if (sectionId === 'tasks') {
                renderTasks('all');
            }
        });
    });

    window.renderTasks = function(filter) {
        const tbody = document.getElementById('tasks-table-body');
        const filterLabel = document.getElementById('task-filter-label');
        if (!tbody || !window.allTasks) return;

        filterLabel.textContent = filter === 'all' ? '' : ` - ${filter.charAt(0).toUpperCase() + filter.slice(1)}`;

        const filteredTasks = filter === 'all' ? window.allTasks : window.allTasks.filter(task => task.status === filter);
        
        tbody.innerHTML = '';

        if (filteredTasks.length > 0) {
            filteredTasks.forEach(task => {
                const statusClass = task.status === 'completed' ? 'status-completed' :
                                 task.status === 'delayed' ? 'status-delayed' : 'status-pending';
                const row = `
                    <tr>
                        <td><strong>${task.project_name}</strong></td>
                        <td>${task.title}</td>
                        <td>${task.assigned_date}</td>
                        <td>${task.due_date}</td>
                        <td><span class="status-badge ${statusClass}">${task.status.charAt(0).toUpperCase() + task.status.slice(1)}</span></td>
                        <td>${task.remarks}</td>
                    </tr>
                `;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        } else {
            tbody.parentNode.parentNode.innerHTML = `
                <div style="text-align: center; padding: 3rem; color: var(--gray);">
                    <i class="fas fa-tasks" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>No ${filter === 'all' ? 'tasks' : filter + ' tasks'} assigned yet.</p>
                </div>
            `;
        }
    };

    window.showTasks = function(filter) {
        showSection('tasks');
        renderTasks(filter);
    };

    window.showCompletedUploads = function() {
        showSection('history');
    };

    if (document.getElementById('tasks-section').style.display === 'block') {
        renderTasks('all');
    }
            
    const uploadType = document.getElementById('upload_type');
    const fileDescriptionGroup = document.getElementById('file_description_group');
    const fileDescription = document.getElementById('file_description');
    const driveLinkGroup = document.getElementById('drive_link_group');
    const driveLink = document.getElementById('drive_link');
    const fileUploadArea = document.getElementById('file_upload_area');
    const fileInput = document.getElementById('fileInput');
    
    uploadType.addEventListener('change', function() {
        if (this.value === 'other') {
            fileDescriptionGroup.style.display = 'block';
            fileDescription.required = true;
        } else {
            fileDescriptionGroup.style.display = 'none';
            fileDescription.required = false;
        }
        
        if (this.value === 'final_project') {
            fileUploadArea.style.display = 'block';
            driveLinkGroup.style.display = 'none';
            driveLink.required = false;
            fileInput.required = true;
        } else {
            fileUploadArea.style.display = 'none';
            driveLinkGroup.style.display = 'block';
            driveLink.required = true;
            fileInput.required = false;
        }
    });
    
    fileInput.addEventListener('change', function() {
        const fileName = this.files[0]?.name;
        if (fileName) {
            document.querySelector('.file-upload-text').textContent = fileName;
            document.querySelector('.file-upload-icon').innerHTML = '<i class="fas fa-file"></i>';
        }
    });
    
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('uploadBtn');
        const btnText = btn.querySelector('.btn-text');
        const loading = btn.querySelector('.loading');
        
        btnText.style.display = 'none';
        loading.style.display = 'flex';
        btn.disabled = true;

        setTimeout(() => {
            this.reset();
            document.querySelector('.file-upload-text').textContent = 'Click to upload or drag and drop';
            document.querySelector('.file-upload-icon').innerHTML = '<i class="fas fa-cloud-upload-alt"></i>';
            btnText.style.display = 'inline-flex';
            loading.style.display = 'none';
            btn.disabled = false;
        }, 1000);
    });
    
    document.getElementById('remarksForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('remarksBtn');
        const btnText = btn.querySelector('.btn-text');
        const loading = btn.querySelector('.loading');
        
        btnText.style.display = 'none';
        loading.style.display = 'flex';
        btn.disabled = true;
    });
    
    const uploadArea = document.querySelector('.file-upload-area');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('dragover');
        });
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('dragover');
        });
    });
    
    uploadArea.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            const fileName = files[0].name;
            document.querySelector('.file-upload-text').textContent = fileName;
            document.querySelector('.file-upload-icon').innerHTML = '<i class="fas fa-file"></i>';
        }
    });

    window.showProfileModal = function() {
        const modal = document.getElementById('profileModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeProfileModal = function() {
        const modal = document.getElementById('profileModal');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };

    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(this.getAttribute('data-tab') + '-tab').classList.add('active');
        });
    });

    const profilePictureInput = document.getElementById('profilePictureInput');
    profilePictureInput.addEventListener('change', function() {
        if (this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.querySelector('.profile-picture');
                if (img.tagName === 'IMG') {
                    img.src = e.target.result;
                } else {
                    img.style.display = 'none';
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.className = 'profile-picture';
                    img.parentNode.insertBefore(newImg, img);
                }
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('profileBtn');
        const btnText = btn.querySelector('.btn-text');
        const loading = btn.querySelector('.loading');
        btnText.style.display = 'none';
        loading.style.display = 'flex';
        btn.disabled = true;
    });

    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const btn = document.getElementById('passwordBtn');
        const btnText = btn.querySelector('.btn-text');
        const loading = btn.querySelector('.loading');
        btnText.style.display = 'none';
        loading.style.display = 'flex';
        btn.disabled = true;
    });
    
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        sidebar.style.transform = sidebar.style.transform === 'translateX(-100%)' ? 'translateX(0)' : 'translateX(-100%)';
    };

    window.showCreditsModal = function() {
        document.getElementById('creditsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeCreditsModal = function() {
        document.getElementById('creditsModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    };

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationDropdown');
        const icon = document.querySelector('.notification-icon');
        if (!dropdown.contains(e.target) && !icon.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
