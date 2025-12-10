<?php
// --- 1. Handle AJAX requests (Keep this at the very top) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($db)) {
        $db_path = __DIR__ . "/db.php";
        if (file_exists($db_path)) {
            require_once $db_path;
            $db = get_db();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database connection file not found']);
            exit;
        }
    }

    if (isset($_SESSION['user']['id'])) {
        $user_id = $_SESSION['user']['id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    }
    
    exit;
}

// --- 2. Database Connection ---
if (!isset($db)) {
    $db_path = __DIR__ . "/db.php";
    if (file_exists($db_path)) {
        require_once $db_path;
        $db = get_db();
    }
}

// --- 3. Determine User Role & Name ---
$current_page = basename($_SERVER['PHP_SELF']);

// Default: Check filename first (fallback)
$is_admin = (strpos($current_page, 'admin_') === 0);

$fullName = null;
$initials = null;

if (isset($_SESSION['user']['id']) && isset($db)) {
    $userId = $_SESSION['user']['id'];

    // CHECK ACTUAL ROLE FROM DATABASE
    // This fixes the issue on pages like notif_view.php
    $stmtRole = $db->prepare("SELECT r.name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :uid");
    $stmtRole->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $resRole = $stmtRole->execute();
    $roleRow = $resRole->fetchArray(SQLITE3_ASSOC);
    
    if ($roleRow && strtolower($roleRow['name']) === 'admin') {
        $is_admin = true;
    }

    // Get Student Name
    $stmt = $db->prepare("SELECT first_name, last_name FROM students WHERE user_id = :uid");
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $student = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($student) {
        $fullName = $student['first_name'] . ' ' . $student['last_name'];
    } else {
        // Fallback to Users table (likely Admin)
        $stmt = $db->prepare("SELECT username FROM users WHERE id = :uid");
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user) {
            $fullName = $user['username'];
            if (strtolower($fullName) === 'admin') {
                $fullName = 'Admin';
            }
        }
    }
}

// Fallback if name is empty
if (empty($fullName) && isset($_SESSION['user'])) {
    if (isset($_SESSION['user']['full_name'])) {
        $fullName = $_SESSION['user']['full_name'];
    } elseif (isset($_SESSION['user']['first_name'])) {
        $fullName = $_SESSION['user']['first_name'] . ' ' . ($_SESSION['user']['last_name'] ?? '');
    }
}

// Generate Initials
if ($fullName) {
    $parts = explode(' ', trim($fullName));
    if (count($parts) >= 2) {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    } else {
        $initials = strtoupper(substr($fullName, 0, 2));
    }
}

// --- 4. Set Navigation Links ---
// This is now based on the database role check, not just the filename
$home_link = $is_admin ? 'admin_dashboard.php' : 'student_dashboard.php';
?>

<link rel="stylesheet" href="../styles/student_nav.css">

<nav class="navbar navbar-expand-md sticky-top">
    <div class="container-fluid px-4">
        
        <div class="d-flex align-items-center">

            <a class="navbar-brand me-0" href="<?php echo $home_link; ?>">
                <img src="../img/logo.png" width="45" height="45" alt="Logo" onerror="this.style.display='none'">
            </a>

            <div class="collapse navbar-collapse ms-4 d-none d-md-block" id="desktopNav">
                <ul class="navbar-nav">
                    <?php if ($is_admin): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active fw-bold text-primary' : ''; ?>" href="admin_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'admin_student_manage.php') ? 'active fw-bold text-primary' : ''; ?>" href="admin_student_manage.php">Students</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'admin_schedule.php') ? 'active fw-bold text-primary' : ''; ?>" href="admin_schedule.php">Schedules</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'student_dashboard.php') ? 'active fw-bold text-primary' : ''; ?>" href="student_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'student_schedule.php') ? 'active fw-bold text-primary' : ''; ?>" href="student_schedule.php">Class Schedule</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">              
            
            <div class="dropdown notification-container position-relative">
                <?php if ($current_page == 'notif_view.php'): ?>
                    <i class="fa-solid fa-bell" 
                       style="font-size: 1.3rem; color: #0d6efd; cursor: default;">
                    </i>
                <?php else: ?>
                    <i class="fa-solid fa-bell text-secondary" 
                       id="notificationDropdown" 
                       data-bs-toggle="dropdown" 
                       aria-expanded="false" 
                       style="font-size: 1.3rem; cursor: pointer;">
                    </i>
                
                <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="notification-badge position-absolute top-0 translate-middle badge rounded-circle bg-danger d-flex align-items-center justify-content-center" style="width: 18px; height: 18px; font-size: 0.6rem; padding: 0; left: 80%;">
                        <?php echo ($unread_count > 9) ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>

                <ul class="dropdown-menu dropdown-menu-end notification-list shadow border-0" aria-labelledby="notificationDropdown">
                    <li class="dropdown-header d-flex justify-content-between align-items-center pb-2 mb-2 px-3 pt-3">
                        <span class="fw-bold text-dark">Notifications</span>
                        <?php if (isset($highlight_count) && $highlight_count > 0): ?> 
                            <a href="<?php echo $current_page; ?>?action=clear_notifications" class="text-decoration-none small" style="color: <?php echo $activeColor ?? '#0d6efd'; ?>">Mark all read</a>
                        <?php endif; ?>
                    </li>

                    <?php if (isset($notifications) && count($notifications) > 0): ?>
                        <div class="notification-items-container">
                        <?php 
                            $limit_notifications = array_slice($notifications, 0, 5);
                            $has_more = count($notifications) > 5;

                            foreach ($limit_notifications as $notif): 
                                $status_class = ($notif['is_read'] == 0) ? 'bg-light border-start border-3 border-primary' : '';
                                $text_class = ($notif['is_read'] == 0) ? 'fw-bold text-dark' : 'text-muted';
                                if ($is_admin) {
                                    $redirect_link = "admin_student_manage.php?action=read_notif&id=" . $notif['id'];
                                } else {
                                    $redirect_link = $current_page . "?action=read_notif&id=" . $notif['id'];
                                }
                        ?>
                            <li>
                                <a class="dropdown-item notification-item p-3 <?php echo $status_class; ?>" href="<?php echo $redirect_link; ?>">
                                    <div class="notif-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="small <?php echo $text_class; ?>">
                                                <?php echo htmlspecialchars(!empty($notif['first_name']) ? $notif['first_name'] : 'ClassSched Alert'); ?>
                                            </strong>
                                            <?php if ($notif['is_read'] == 0): ?>
                                                <span class="badge bg-danger rounded-circle p-1" style="width: 8px; height: 8px;"> </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small mt-1 text-secondary text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                        <div class="notif-time x-small mt-1 text-muted" style="font-size: 10px;">
                                            <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        
                        <?php if ($has_more): ?>
                            <li class="dropdown-footer">
                                <a href="notif_view.php" class="dropdown-item text-center small text-primary py-3 fw-bold bg-light">
                                    View All Notifications
                                </a>
                            </li>
                        <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <li class="text-center py-4 text-muted small">No notifications yet</li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?> <!-- THIS WAS MISSING -->
            </div>
            
            <div class="dropdown">
                <?php if ($is_admin): ?>
                    <button class="btn user-dropdown-btn rounded-pill d-flex align-items-center gap-2 border-0 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="fw-bold" style="letter-spacing: 0.5px;"><?php echo htmlspecialchars($initials ?? 'AD'); ?></span>
                        <i class="fa-solid fa-caret-down" style="font-size: 0.8rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end custom-dropdown-menu shadow-lg mt-2">
                        <li class="dropdown-header-custom">
                            <small class="text-muted d-block" style="font-size: 11px;">Signed in as</small>
                            <span class="fw-bold text-dark" style="font-size: 15px;"><?php echo htmlspecialchars($fullName ?? 'Administrator'); ?></span>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                Logout
                            </a>
                        </li>
                    </ul>
                <?php else: ?>
                    <button class="btn user-dropdown-btn rounded-pill d-flex align-items-center gap-2 border-0 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="fw-bold" style="letter-spacing: 0.5px;"><?php echo htmlspecialchars($initials ?? 'US'); ?></span>
                        <i class="fa-solid fa-caret-down" style="font-size: 0.8rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end custom-dropdown-menu shadow-lg mt-2">
                        <li class="dropdown-header-custom">
                            <small class="text-muted d-block" style="font-size: 11px;">Signed in as</small>
                            <span class="fw-bold text-dark" style="font-size: 15px;"><?php echo htmlspecialchars($fullName ?? 'User'); ?></span>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item" href="student_profile.php">
                                Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                Logout
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="bottom-nav">
    <?php if ($is_admin): ?>
        <a href="admin_dashboard.php" class="bottom-nav-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>

        <a href="admin_student_manage.php" class="bottom-nav-item <?php echo ($current_page == 'admin_student_manage.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>
            <span>Students</span>
        </a>

        <a href="admin_schedule.php" class="bottom-nav-item <?php echo ($current_page == 'admin_schedule.php') ? 'active' : ''; ?>">
            <i class="fa-regular fa-calendar-days"></i>
            <span>Schedule</span>
        </a>
    <?php else: ?>
        <a href="student_dashboard.php" class="bottom-nav-item <?php echo ($current_page == 'student_dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>

        <a href="student_schedule.php" class="bottom-nav-item <?php echo ($current_page == 'student_schedule.php') ? 'active' : ''; ?>">
            <i class="fa-regular fa-calendar-days"></i> 
            <span>Schedule</span>
        </a>

        <a href="student_profile.php" class="bottom-nav-item <?php echo ($current_page == 'student_profile.php') ? 'active' : ''; ?>">
            <i class="fa-regular fa-user"></i>
            <span>Profile</span>
        </a>
    <?php endif; ?>
</div>

<script src="../js/notification.js"></script>