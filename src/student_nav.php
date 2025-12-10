<?php
// --- NEW LOGIC: Handle Badge Clearing Dynamically ---
// This allows this single file to handle the DB update for any page it's included in.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
    // 1. If the parent page started output buffering, clean it so we return only JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 2. Ensure Session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 3. Connect to DB (Check if $db exists from parent, if not, require it)
    if (!isset($db)) {
        $db_path = __DIR__ . "/db.php";
        if (file_exists($db_path)) {
            require_once $db_path;
            $db = get_db();
        } else {
            // Fallback error if db.php isn't found
            echo json_encode(['status' => 'error', 'message' => 'Database connection file not found']);
            exit;
        }
    }

    // 4. Update the database
    if (isset($_SESSION['user']['id'])) {
        $user_id = $_SESSION['user']['id'];
        // Update all unread notifications to read
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        $stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    }
    
    // 5. Stop execution so the rest of the HTML doesn't load
    exit;
}
// --- END NEW LOGIC ---

$current_page = basename($_SERVER['PHP_SELF']);
?>

<link rel="stylesheet" href="../styles/student_nav.css">

<nav class="navbar navbar-expand-md sticky-top">
    <div class="container-fluid px-4">
        
        <div class="d-flex align-items-center">

            <a class="navbar-brand me-0" href="student_dashboard.php">
                <img src="../img/logo.png" width="45" height="45" alt="Logo" onerror="this.style.display='none'">
            </a>

            <div class="collapse navbar-collapse ms-4 d-none d-md-block" id="desktopNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'student_dashboard.php') ? 'active fw-bold text-primary' : ''; ?>" href="student_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'student_schedule.php') ? 'active fw-bold text-primary' : ''; ?>" href="student_schedule.php">Class Schedule</a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">              
            
            <div class="dropdown notification-container position-relative">
                <!-- Removed 'dropdown-toggle' class to remove the arrow spacing issue -->
                <i class="fa-solid fa-bell text-secondary" 
                   id="notificationDropdown" 
                   data-bs-toggle="dropdown" 
                   aria-expanded="false" 
                   style="font-size: 1.3rem; cursor: pointer;">
                </i>
                
                <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <!-- Changed to rounded-circle with fixed width/height for a perfect circle -->
                    <span class="notification-badge position-absolute top-0 translate-middle badge rounded-circle bg-danger d-flex align-items-center justify-content-center" style="width: 18px; height: 18px; font-size: 0.6rem; padding: 0; left: 80%;">
                        <?php echo ($unread_count > 9) ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>

                <ul class="dropdown-menu dropdown-menu-end notification-list shadow border-0" aria-labelledby="notificationDropdown">
                    <li class="dropdown-header d-flex justify-content-between align-items-center pb-2 mb-2 px-3 pt-3">
                        <span class="fw-bold text-dark">Notifications</span>
                        <?php if (isset($highlight_count) && $highlight_count > 0): ?> 
                            <!-- Direct to notif_view.php for the action -->
                            <a href="student_schedule.php?action=clear_notifications" class="text-decoration-none small" style="color: <?php echo $activeColor; ?>">Mark all read</a>
                        <?php endif; ?>
                    </li>

                    <?php if (isset($notifications) && count($notifications) > 0): ?>
                        <div class="notification-items-container">
                        <?php 
                            // UPDATED: Logic to show only Top 5 recent notifications
                            $limit_notifications = array_slice($notifications, 0, 5);
                            $has_more = count($notifications) > 5;

                            foreach ($limit_notifications as $notif): 
                                $status_class = ($notif['is_read'] == 0) ? 'bg-light border-start border-3 border-primary' : '';
                                $text_class = ($notif['is_read'] == 0) ? 'fw-bold text-dark' : 'text-muted';
                        ?>
                            <li>
                                <!-- Links now point to notif_view.php to handle the 'read' action -->
                                <a class="dropdown-item notification-item p-3 <?php echo $status_class; ?>" href="student_schedule.php?action=read_notif&id=<?php echo $notif['id']; ?>">
                                    <div class="notif-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="small <?php echo $text_class; ?>">
                                                <?php echo htmlspecialchars(!empty($notif['first_name']) ? $notif['first_name'] : 'System Alert'); ?>
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
            </div>
            
            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <button class="btn user-dropdown-btn rounded-pill d-flex align-items-center gap-2 border-0 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="fw-bold" style="letter-spacing: 0.5px;"><?php echo htmlspecialchars($initials ?? 'ST'); ?></span>
                    <i class="fa-solid fa-caret-down" style="font-size: 0.8rem;"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end custom-dropdown-menu shadow-lg mt-2">
                    <li class="dropdown-header-custom">
                        <small class="text-muted d-block" style="font-size: 11px;">Signed in as</small>
                        <span class="fw-bold text-dark" style="font-size: 15px;"><?php echo htmlspecialchars($fullName ?? 'Student Name'); ?></span>
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
            </div>
        </div>
    </div>
</nav>

<div class="bottom-nav">
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
</div>
<script src="../js/notification.js"></script>