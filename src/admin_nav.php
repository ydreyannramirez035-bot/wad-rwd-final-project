<?php
$current_page = basename($_SERVER['PHP_SELF']);

?>
<nav class="navbar navbar-expand-sm sticky-top">
    <div class="container-fluid px-4">

        <div class="d-flex align-items-center">
            <button class="navbar-toggler me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <a class="navbar-brand me-0" href="admin_dashboard.php">
                <img src="../img/logo.png" width="60" height="60">
            </a>
        </div>
        <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="offcanvasNavbarLabel"></h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <img src="../img/logo.png" width="60" height="60" class="me-2">
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav justify-content-start flex-grow-1 pe-3">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>" href="admin_dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'admin_student_manage.php') ? 'active' : ''; ?>" href="admin_student_manage.php">Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'admin_schedule.php') ? 'active' : ''; ?>" href="admin_schedule.php">Schedule</a>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <!-- Notification Dropdown -->
            <div class="dropdown notification-container me-4 position-relative">
                <i class="fa-solid fa-bell dropdown-toggle" 
                   id="notificationDropdown" 
                   data-bs-toggle="dropdown" 
                   aria-expanded="false" 
                   style="font-size: 1.2rem;">
                </i>
                
                <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="notification-badge">
                        <?php echo ($unread_count > 9) ? '9+' : $unread_count; ?>
                    </span>
                <?php endif; ?>

                <ul class="dropdown-menu dropdown-menu-end notification-list shadow" aria-labelledby="notificationDropdown">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Notifications</span>
                        <?php if (isset($highlight_count) && $highlight_count > 0): ?> 
                            <a href="?action=clear_notifications" class="text-decoration-none small text-primary">Mark all read</a>
                        <?php endif; ?>
                    </li>

                    <?php if (isset($notifications) && count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notif): ?>
                            <?php 
                                $status_class = ($notif['is_read'] == 0) ? 'fw-bold bg-light border-start border-3 border-primary' : 'text-muted';
                            ?>
                            <li>
                                <a class="dropdown-item notification-item p-3 <?php echo $status_class; ?>" href="?action=read_notif&id=<?php echo $notif['id']; ?>">
                                    <div class="notif-content">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="<?php echo ($notif['is_read'] == 0) ? 'text-dark' : ''; ?>">
                                                <?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?>
                                            </strong>
                                            <?php if ($notif['is_read'] == 0): ?>
                                                <span class="badge bg-primary rounded-pill" style="font-size: 0.5rem;">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small mt-1 <?php echo ($notif['is_read'] == 0) ? 'text-dark' : ''; ?>">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                        <div class="notif-time small mt-1 text-secondary">
                                            <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="text-center py-4 text-muted small">No notifications yet</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Admin Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-admin dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <span class="admin-text">Admin • </span><?php echo htmlspecialchars(substr($user["username"], 0, 2)); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-1"><small>Signed in as<br><b><?php echo htmlspecialchars($user["username"]); ?></b></small></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>