<?php
// Ensure session persistence
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

session_start();

// Prevent caching
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

require_once 'db.php'; // Your database connection file

// Fetch uploaded files history
$upload_query = "
    SELECT fu.*, t.title AS task_title, p.name AS project_name, u.first_name, u.last_name, u.employee_id
    FROM file_uploads fu
    JOIN tasks t ON fu.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON fu.employee_id = u.employee_id
    ORDER BY fu.uploaded_at DESC";
$upload_result = mysqli_query($conn, $upload_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload History - Superadmin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.cdnfonts.com/css/samarkan?styles=6066" rel="stylesheet">
    <style>
        /* Reuse styles from admin_dashboard.php */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow: auto;
        }

        .main-content {
            margin-left: 280px;
            flex: 1 0 auto;
            background: var(--light);
            display: flex;
            flex-direction: column;
        }

        .content {
            flex: 1 0 auto;
            padding: 2rem;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: #1f2937;
            padding: 2rem 0;
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 2rem;
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .logo img {
            width: 100px;
            height: 50px;
            object-fit: contain;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px;
        }

        .nav-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            font-size: 1.2rem;
            width: 20px;
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .header-left p {
            color: var(--gray);
            font-size: 0.875rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: var(--light);
        }

        .footer {
            flex-shrink: 0;
            text-align: center;
            padding: 1rem;
            color: #a0aec0;
            font-size: 0.875rem;
            cursor: pointer;
        }

        .footer:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .credits-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .credits-modal-content {
            background: var(--white);
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.4s ease-out;
        }

        .credits-modal-header {
            padding: 1.5rem 2rem;
            background: var(--gradient-1);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 16px 16px 0 0;
        }

        .credits-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .credits-modal-body {
            padding: 2rem;
            text-align: center;
        }

        .credits-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .credits-list li {
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <img src="assets/images/tihan_logo.webp" alt="TiHAN Logo">
                </div>
                <div>
                    <div style="font-size: 1.65rem; font-family: 'Samarkan', sans-serif; ">NIDHI</div>
                    <div style="font-size: 1.15rem;">Superadmin</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="superadmin_dashboard.php#dashboard" class="nav-link" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Superadmin Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="superadmin_dashboard.php#projects" class="nav-link" data-section="projects">
                    <i class="fas fa-project-diagram"></i>
                    <span>Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="superadmin_manage_users.php" class="nav-link" data-section="manage-users">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="superadmin_upload_history.php" class="nav-link active" data-section="upload-history">
                    <i class="fas fa-history"></i>
                    <span>Upload History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="superadmin_logout.php" class="nav-link" onclick="window.location.href='index.php'; return false;">
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
                <h1>Upload History</h1>
                <p>View all files and links uploaded by users.</p>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-history"></i>
                        Upload History
                    </div>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($upload_result) > 0): ?>
                        <div class="table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Task</th>
                                        <th>Employee</th>
                                        <th>File Name/Link</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Upload Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($upload = mysqli_fetch_assoc($upload_result)): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($upload['project_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($upload['task_title']); ?></td>
                                            <td><?php echo htmlspecialchars($upload['employee_id'] . ' - ' . $upload['first_name'] . ' ' . $upload['last_name']); ?></td>
                                            <td>
                                                <?php if ($upload['drive_link']): ?>
                                                    <a href="<?php echo htmlspecialchars($upload['drive_link']); ?>" target="_blank">View Drive Link</a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(basename($upload['file_path'])); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $upload['upload_type'])); ?></td>
                                            <td><?php echo htmlspecialchars($upload['file_description'] ?? '-'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($upload['uploaded_at'])); ?></td>
                                            <td>
                                                <?php if ($upload['drive_link']): ?>
                                                    <a href="<?php echo htmlspecialchars($upload['drive_link']); ?>" class="btn-view" style="padding: 0.5rem 1rem;" target="_blank">View</a>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" class="btn-view" style="padding: 0.5rem 1rem;" download>Download</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: var(--gray);">
                            <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p>No files uploaded yet.</p>
                        </div>
                    <?php endif; ?>
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
                    <h2>Project Credits</h2>
                    <button class="modal-close" onclick="closeCreditsModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="credits-modal-body">
                    <h3>Team Members</h3>
                    <ul class="credits-list">
                        <li>Dr. P. Rajalakshmi</li>
                        <li>Dr. S. Syam Narayanan</li>
                        <li>Muhammed Nazim</li>
                        <li>Sharon Zipporah Sebastain</li>
                    </ul>
                    <p>Thank you to our dedicated team for their contributions to this project!</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.style.transform = sidebar.style.transform === 'translateX(-100%)' ? 'translateX(0)' : 'translateX(-100%)';
        }

        // Credits modal functionality
        window.showCreditsModal = function() {
            document.getElementById('creditsModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        };

        window.closeCreditsModal = function() {
            document.getElementById('creditsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        };
    </script>
</body>
</html>
<?php mysqli_close($conn); ?> 