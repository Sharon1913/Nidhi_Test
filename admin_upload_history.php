<?php
// Session and access control
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);
session_start();
require_once 'db.php';

// Only allow admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Fetch all uploads with user, project, task, and status info
$query = "
    SELECT fu.*, t.title AS task_title, t.status AS task_status, t.remarks AS task_remarks, p.name AS project_name,
           u.first_name, u.last_name, u.email, u.employee_id
    FROM file_uploads fu
    JOIN tasks t ON fu.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON fu.employee_id = u.employee_id
    ORDER BY fu.uploaded_at DESC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Admin Upload History</title>
    <link rel='stylesheet' href='admin_style.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
</head>
<body>
    <div class='sidebar' id='sidebar'>
        <div class='sidebar-header'>
            <div class='logo'>
                <div class='logo-icon'>
                    <img src='assets/images/tihan_logo.webp' alt='TiHAN Logo'>
                </div>
                <div>
                    <div style='font-size: 1.65rem; font-family: "Samarkan", sans-serif;'>NIDHI</div>
                    <div style='font-size: 1.15rem;'>Admin</div>
                    <div style='font-size: 0.75rem; opacity: 0.8;'>Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        <ul class='nav-menu'>
            <li class='nav-item'><a href='admin_dashboard.php' class='nav-link'><i class='fas fa-home'></i><span>Team Dashboard</span></a></li>
            <li class='nav-item'><a href='admin_dashboard.php#projects' class='nav-link'><i class='fas fa-project-diagram'></i><span>Projects</span></a></li>
            <li class='nav-item'><a href='admin_manage_users.php' class='nav-link'><i class='fas fa-users'></i><span>Assign Project</span></a></li>
            <li class='nav-item'><a href='admin_assign_task.php' class='nav-link'><i class='fas fa-tasks'></i><span>Assign Task</span></a></li>
            <li class='nav-item'><a href='admin_upload_history.php' class='nav-link active'><i class='fas fa-history'></i><span>Upload History</span></a></li>
            <li class='nav-item'><a href='admin_logout.php' class='nav-link'><i class='fas fa-sign-out-alt'></i><span>Logout</span></a></li>
        </ul>
    </div>
    <div class='main-content'>
        <div class='header'>
            <div class='header-left'>
                <h1>Upload History</h1>
                <p>All files uploaded by users</p>
            </div>
        </div>
        <div class='content'>
            <div class='content-section fade-in'>
                <div class='card'>
                    <div class='card-header'>
                        <div class='card-title'><i class='fas fa-history'></i> Upload History</div>
                    </div>
                    <div class='card-body'>
                        <?php if ($result && $result->num_rows > 0) { ?>
                        <div class='table-container'>
                            <table class='modern-table'>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>File Name/Link</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Upload Date</th>
                                        <th>Claimed to be</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo htmlspecialchars($row['project_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['task_title']); ?></td>
                                        <td>
                                            <?php if ($row['drive_link']) { ?>
                                                <a href='<?php echo htmlspecialchars($row['drive_link']); ?>' target='_blank'>View Drive Link</a>
                                            <?php } elseif ($row['file_path']) { ?>
                                                <a href='<?php echo htmlspecialchars($row['file_path']); ?>' target='_blank'><?php echo htmlspecialchars(basename($row['file_path'])); ?></a>
                                            <?php } else { ?>
                                                -
                                            <?php } ?>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $row['upload_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['file_description'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($row['uploaded_at'])); ?></td>
                                        <td>
                                            <?php
                                            // Find claimed status from the latest notification for this upload
                                            $claimed = '-';
                                            $notif_q = $conn->query("SELECT message FROM notifications WHERE task_id = " . intval($row['task_id']) . " AND recipient_role = 'admin' ORDER BY uploaded_at DESC LIMIT 1");
                                            if ($notif_q && $notif_q->num_rows > 0) {
                                                $notif = $notif_q->fetch_assoc();
                                                if (preg_match('/\\(Status: (.*?)\\)\\. Please review\\./', $notif['message'], $matches)) {
                                                    $claimed = $matches[1];
                                                }
                                            }
                                            echo htmlspecialchars($claimed);
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status = strtolower($row['task_status']);
                                            if ($status === 'completed') {
                                                echo '<span class="status-badge status-completed">Accepted</span>';
                                            } elseif ($status === 'pending') {
                                                echo '<span class="status-badge status-pending">Pending</span>';
                                            } elseif ($status === 'delayed') {
                                                echo '<span class="status-badge status-delayed">Rejected</span>';
                                            } else {
                                                echo '<span class="status-badge">' . ucfirst($status) . '</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } else { ?>
                        <div style='text-align: center; padding: 3rem; color: var(--gray);'>
                            <i class='fas fa-history' style='font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;'></i>
                            <p>No files uploaded yet.</p>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class='footer' onclick='window.location.href="admin_dashboard.php";'>
            © Copyright 2025 NMICPS TiHAN Foundation | All Rights Reserved
        </div>
    </div>
</body>
</html> 