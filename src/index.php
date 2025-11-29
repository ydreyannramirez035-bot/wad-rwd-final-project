<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
$user_id = $user['id'];

require_once __DIR__ ."/db.php";
$db = get_db();

$student_row = $db->querySingle("SELECT * FROM students WHERE user_id = $user_id", true);
$is_student = (bool)$student_row;
$student_id = $is_student ? $student_row['id'] : 0;

$dashboard_link = $is_student ? "student_dashboard.php" : "admin_dashboard.php";

$cols = $db->query("PRAGMA table_info(users)");
$hasCol = false;
while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
    if ($col['name'] === 'last_notification_check') {
        $hasCol = true;
        break;
    }
}
if (!$hasCol) {
    $db->exec("ALTER TABLE users ADD COLUMN last_notification_check DATETIME DEFAULT '1970-01-01 00:00:00'");
}

if ($is_student) {
    $s_cols = $db->query("PRAGMA table_info(students)");
    $s_hasCol = false;
    while ($col = $s_cols->fetchArray(SQLITE3_ASSOC)) {
        if ($col['name'] === 'last_notification_check') {
            $s_hasCol = true;
            break;
        }
    }
    if (!$s_hasCol) {
        $db->exec("ALTER TABLE students ADD COLUMN last_notification_check DATETIME DEFAULT '1970-01-01 00:00:00'");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
    if ($is_student) {
        $db->exec("UPDATE students SET last_notification_check = datetime('now', 'localtime') WHERE id = $student_id");
    } else {
        $db->exec("UPDATE users SET last_notification_check = datetime('now', 'localtime') WHERE id = $user_id");
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

if (!$is_student) {
    $current_db_val = $db->querySingle("SELECT last_activity FROM admin_system_log WHERE id = 1");
    $current_time = time();
    if (!is_numeric($current_db_val) || ($current_db_val > $current_time + 60)) {
        $db->exec("UPDATE admin_system_log SET last_activity = strftime('%s', 'now') WHERE id = 1");
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'clear_notifications') {
    if ($is_student) {
        $db->exec("UPDATE notifications SET is_read = 1 WHERE student_id = $student_id");
    } else {
        $db->exec("UPDATE notifications SET is_read = 1 
                   WHERE is_read = 0 
                   AND (message LIKE '%bio%' OR message LIKE '%phone%')");
    }
    header("Location: index.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'read_notif' && isset($_GET['id'])) {
    $notif_id = (int)$_GET['id'];
    $db->exec("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
    header("Location: " . ($is_student ? "student_schedule.php" : "admin_student_manage.php")); 
    exit;
}

if ($is_student) {
    $last_check_row = $db->querySingle("SELECT last_notification_check FROM students WHERE id = $student_id", true);
} else {
    $last_check_row = $db->querySingle("SELECT last_notification_check FROM users WHERE id = $user_id", true);
}

$last_click = ($last_check_row && $last_check_row['last_notification_check']) 
              ? $last_check_row['last_notification_check'] 
              : '1970-01-01 00:00:00';

if ($is_student) {
    $stmt_count = $db->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE student_id = :sid 
        AND is_read = 0 
        AND created_at > :last_click
        AND (message LIKE 'New Class:%' OR message LIKE 'Schedule Update:%')
    ");
    $stmt_count->bindValue(':sid', $student_id, SQLITE3_INTEGER);
} else {
    $stmt_count = $db->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE is_read = 0 
        AND created_at > :last_click
        AND (message LIKE '%bio%' OR message LIKE '%phone%')
    ");
}
$stmt_count->bindValue(':last_click', $last_click, SQLITE3_TEXT);
$unread_count = $stmt_count->execute()->fetchArray()[0];

if ($is_student) {
    $notif_sql = "
        SELECT * FROM notifications 
        WHERE student_id = $student_id
        AND (message LIKE 'New Class:%' OR message LIKE 'Schedule Update:%')
        ORDER BY created_at DESC LIMIT 10
    ";
} else {
    $notif_sql = "
        SELECT n.*, s.first_name, s.last_name 
        FROM notifications n
        LEFT JOIN students s ON n.student_id = s.id
        WHERE (n.message LIKE '%bio%' OR n.message LIKE '%phone%')
        ORDER BY n.created_at DESC
        LIMIT 10
    ";
}

$notif_result = $db->query($notif_sql);
$notifications = [];
while ($row = $notif_result->fetchArray(SQLITE3_ASSOC)) {
    if ($is_student) {
        $row['first_name'] = 'System';
        $row['last_name'] = 'Alert';
    }
    $notifications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ClassSched — School Scheduling</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../styles/landing_page.css" rel="stylesheet">
    <link href="../styles/notification.css" rel="stylesheet">
  </head>

  <body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo $dashboard_link; ?>">
                <img src="../img/logo.jpg" width="50" height="50" class="me-2">
                <span class="fw-bold text-primary">Class</span><span class="text-primary">Sched</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <?php if ($is_student): ?>
                      <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                      <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Dashboard</a></li>
                      <li class="nav-item"><a class="nav-link" href="student_schedule.php">Class Schedule</a></li>
                    <?php else: ?>
                      <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                      <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
                      <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                      <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center ms-lg-4">
                    <div class="dropdown notification-container me-3 position-relative">
                        <i class="fa-solid fa-bell dropdown-toggle" 
                          id="notificationDropdown" 
                          data-bs-toggle="dropdown" 
                          aria-expanded="false" 
                          style="font-size: 1.2rem; cursor: pointer;">
                        </i>
                        
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge">
                                <?php echo ($unread_count > 9) ? '9+' : $unread_count; ?>
                            </span>
                        <?php endif; ?>

                        <ul class="dropdown-menu dropdown-menu-end notification-list shadow" aria-labelledby="notificationDropdown">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Notifications</span>
                                <?php if ($unread_count > 0): ?>
                                    <a href="?action=clear_notifications" class="text-decoration-none small text-primary">Mark all read</a>
                                <?php endif; ?>
                            </li>

                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <?php 
                                        $status_class = ($notif['is_read'] == 0) ? 'fw-bold bg-light border-start border-3 border-primary' : 'text-muted';
                                        
                                        $sender_name = $is_student 
                                            ? 'ClassSched Alert' 
                                            : htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']);
                                    ?>
                                    <li>
                                        <a class="dropdown-item notification-item p-3 <?php echo $status_class; ?>" href="?action=read_notif&id=<?php echo $notif['id']; ?>">
                                            
                                            <div class="notif-content">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong class="<?php echo ($notif['is_read'] == 0) ? 'text-dark' : ''; ?>">
                                                        <?php echo $sender_name; ?>
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

                    <div class="dropdown">
                        <button class="btn btn-admin dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                            <span class="d-none d-md-block"><?php echo $is_student ? 'Student' : 'Admin'; ?></span>
                            <span class="badge bg-secondary rounded-circle p-2"><?php echo htmlspecialchars(substr($user["username"], 0, 2)); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="px-3 py-1"><small>Signed in as<br><b><?php echo htmlspecialchars($user["username"]); ?></b></small></li>
                            <?php if($is_student): ?>
                                <li><a class="dropdown-item" href="student_profile.php">Profile</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <section class="hero">
      <div class="hero-text">
        <h1>Simplify Your<br>School Scheduling</h1>
        <p>
          Easily manage classes, teachers, and<br>
          classrooms—all in one place.
        </p>
        <a href="<?php echo $dashboard_link; ?>" class="btn btn-primary btn-lg mt-3 rounded-pill px-5 shadow">
            Go to Dashboard
        </a>
      </div>

      <div class="visual-box"></div>
    </section>

    <footer class="footer">
      ClassSched © 2025 — All rights of Humans XD.
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>

  </body>
</html>