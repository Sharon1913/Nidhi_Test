<?php
// Ensure session persistence
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

session_start();

// Prevent caching
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'db.php'; // Your database connection file

// Fetch all uploads with project, task, user info
$query = "
    SELECT fu.*, t.title AS task_title, p.name AS project_name, u.first_name, u.last_name, u.employee_id, fu.status AS admin_status
    FROM file_uploads fu
    JOIN tasks t ON fu.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON fu.employee_id = u.employee_id
    ORDER BY fu.uploaded_at DESC
";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload History - Admin Dashboard</title>
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
            background: linear-gradient(135deg, rgb(126, 93, 44), rgb(233, 172, 91));
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
            gap: 1remEl;
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

        .notification-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.2rem;
            color: #f1be02;
            padding: 0.5rem;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .notification-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 50%;
            border: 2px solid #ef4444;
        }

        .notification-dropdown {
            display: none;
            position: absolute;
            top: 60px;
            right: 20px;
            width: 350px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: #f9fafb;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .notification-message {
            font-size: 0.85rem;
            color: #4b5563;
            margin-bottom: 0.5rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-approve, .btn-reject {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-approve:hover, .btn-reject:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-view {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .notification-file {
            display: inline-block;
            color: #3b82f6;
            font-size: 0.8rem;
            text-decoration: none;
            margin-bottom: 0.5rem;
        }

        .notification-file:hover {
            text-decoration: underline;
        }

        .notification-empty {
            padding: 1.5rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .sidebar-toggle {
            display: none;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.25rem;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: var(--shadow);
            border-radius: 12px;
            overflow: hidden;
        }

        .modern-table th,
        .modern-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .modern-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            font-size: 0.875rem;
        }

        .modern-table td {
            color: #4b5563;
            font-size: 0.9rem;
        }

        .modern-table tr:hover {
            background: #f3f4f6;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            background: var(--gradient-1);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 2rem;
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

        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                margin-left: 240px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
        }

        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-bar { margin-bottom: 1rem; padding: 0.5rem; width: 100%; max-width: 400px; border-radius: 8px; border: 1px solid #ccc; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; border: 1px solid #e2e8f0; text-align: left; }
        th { background: #f3f4f6; }
        tr:nth-child(even) { background: #f9fafb; }
        .view-link { color: #3b82f6; text-decoration: underline; }
        .status-accepted { color: #10b981; font-weight: bold; }
        .status-rejected { color: #ef4444; font-weight: bold; }
    </style>
    <script>
    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('uploadTable');
        const trs = table.getElementsByTagName('tr');
        for (let i = 1; i < trs.length; i++) {
            let show = false;
            const tds = trs[i].getElementsByTagName('td');
            for (let j = 0; j < tds.length; j++) {
                if (tds[j].textContent.toLowerCase().indexOf(filter) > -1) {
                    show = true;
                    break;
                }
            }
            trs[i].style.display = show ? '' : 'none';
        }
    }
    </script>
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
                    <div style="font-size: 1.15rem;">Admin</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Networked Innovation for Development and Holistic Implementation</div>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="admin_dashboard.php#dashboard" class="nav-link" data-section="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Team Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_dashboard.php#projects" class="nav-link" data-section="projects">
                    <i class="fas fa-project-diagram"></i>
                    <span>Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_manage_users.php" class="nav-link" data-section="assign-project">
                    <i class="fas fa-users"></i>
                    <span>Assign Project</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_assign_task.php" class="nav-link" data-section="assign-task">
                    <i class="fas fa-tasks"></i>
                    <span>Assign Task</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_upload_history.php" class="nav-link active" data-section="upload-history">
                    <i class="fas fa-history"></i>
                    <span>Upload History</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="admin_logout.php" class="nav-link" onclick="window.location.href='index.php'; return false;">
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
            <div class="header-right">
                <a href="admin_add_project.php" class="btn btn-primary">Add Project</a>
                <a href="admin_manage_users.php" class="btn btn-secondary">Manage Users</a>
                <div class="notification-icon" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <!-- Notification count will be populated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Notification Dropdown -->
        <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i><br>
                No new notifications
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
                    <input type="text" id="searchInput" class="search-bar" onkeyup="filterTable()" placeholder="Search uploads...">
                    <div class="table-container">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Project and Task</th>
                                    <th>Name</th>
                                    <th>Employee ID</th>
                                    <th>Status (Claimed to be)</th>
                                    <th>Date and Time</th>
                                    <th>View Link</th>
                                    <th>Status (Admin)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php
                                        $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                        $display_name = $full_name ?: $row['employee_id'];
                                        $status_claimed = $row['status_claimed'] ?? '';
                                        // Try to extract status from remarks/message if needed
                                        if (empty($status_claimed) && isset($row['remarks'])) {
                                            if (preg_match('/\\(Status: (.*?)\\)\\. Please review\\./', $row['remarks'], $matches)) {
                                                $status_claimed = $matches[1] . '. Please review.';
                                            } else {
                                                $status_claimed = $row['remarks'];
                                            }
                                        }
                                        $admin_status = $row['admin_status'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['project_name'] . ' - ' . $row['task_title']) ?></td>
                                        <td><?= htmlspecialchars($display_name) ?></td>
                                        <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                        <td><?= htmlspecialchars($status_claimed) ?></td>
                                        <td><?= date('M d, Y H:i', strtotime($row['uploaded_at'])) ?></td>
                                        <td>
                                            <?php if (!empty($row['drive_link'])): ?>
                                                <a href="<?= htmlspecialchars($row['drive_link']) ?>" class="view-link" target="_blank">View Link</a>
                                            <?php elseif (!empty($row['file_path'])): ?>
                                                <a href="<?= htmlspecialchars($row['file_path']) ?>" class="view-link" target="_blank">View Link</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $admin_status === 'accepted' ? 'status-accepted' : ($admin_status === 'rejected' ? 'status-rejected' : '') ?>">
                                            <?= htmlspecialchars(ucfirst($admin_status)) ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
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

        // Fetch notifications
        function fetchNotifications() {
            fetch('admin_dashboard.php?action=fetch_notifications', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                const dropdown = document.getElementById('notificationDropdown');
                const countElement = document.querySelector('.notification-count');
                
                if (data.notifications.length > 0) {
                    dropdown.innerHTML = data.notifications.map(notification => `
                        <div class="notification-item" data-notification-id="${notification.id}">
                            <div class="notification-header">
                                <span class="notification-title">
                                    ${notification.project_name} - ${notification.task_title}
                                </span>
                                <span class="notification-time">
                                    ${notification.uploaded_at}
                                </span>
                            </div>
                            <div class="notification-message">
                                Employee ID: ${notification.employee_id}<br>
                                ${notification.message}
                            </div>
                            ${notification.file_path ? `<a href="${notification.file_path}" class="notification-file" target="_blank">View Uploaded File</a>` : ''}
                            <div class="notification-actions">
                                <button class="btn-approve" onclick="handleNotificationAction(${notification.id}, 'approve', ${notification.task_id})">Approve</button>
                                <button class="btn-reject" onclick="handleNotificationAction(${notification.id}, 'reject', ${notification.task_id})">Reject</button>
                            </div>
                        </div>
                    `).join('');
                    if (countElement) {
                        countElement.textContent = data.notifications.length;
                    } else if (data.notifications.length > 0) {
                        const icon = document.querySelector('.notification-icon');
                        icon.insertAdjacentHTML('beforeend', `<span class="notification-count">${data.notifications.length}</span>`);
                    }
                } else {
                    dropdown.innerHTML = `
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i><br>
                            No new notifications
                        </div>`;
                    countElement?.remove();
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
            });
        }

        // Poll for notifications every 30 seconds
        setInterval(fetchNotifications, 30000);

        // Initial fetch on page load
        document.addEventListener('DOMContentLoaded', fetchNotifications);

        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
            if (dropdown.classList.contains('active')) {
                fetchNotifications();
            }
        }

        function handleNotificationAction(notificationId, action, taskId) {
            if (!confirm(`Are you sure you want to ${action} this task completion?`)) return;
            const formData = new FormData();
            formData.append('action', action);
            formData.append('notification_id', notificationId);
            formData.append('task_id', taskId);

            fetch('admin_dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const notificationItem = document.querySelector(`.notification-item[data-notification-id="${notificationId}"]`);
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
                            </div>`;
                    }
                } else {
                    console.error('Server error:', data.error);
                    alert('Error processing action: ' + (data.error || 'Unknown error occurred. Check console for details.'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error.message);
                alert('Error processing action: ' + error.message);
            });
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

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const icon = document.querySelector('.notification-icon');
            if (!dropdown.contains(e.target) && !icon.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php mysqli_close($conn); ?>