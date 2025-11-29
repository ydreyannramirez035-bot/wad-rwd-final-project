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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
    $db->exec("UPDATE users SET last_notification_check = datetime('now', 'localtime') WHERE id = $user_id");
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

$current_db_val = $db->querySingle("SELECT last_activity FROM admin_system_log WHERE id = 1");
$current_time = time();

if (!is_numeric($current_db_val) || ($current_db_val > $current_time + 60)) {
    $db->exec("UPDATE admin_system_log SET last_activity = strftime('%s', 'now') WHERE id = 1");
}

if (isset($_GET['action']) && $_GET['action'] === 'clear_notifications') {
    $db->exec("UPDATE notifications SET is_read = 1 
               WHERE is_read = 0 
               AND (message LIKE '%bio%' OR message LIKE '%phone%')");
    header("Location: admin_dashboard.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'read_notif' && isset($_GET['id'])) {
    $notif_id = (int)$_GET['id'];
    $db->exec("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
    header("Location: admin_student_manage.php"); 
    exit;
}

$last_check_row = $db->querySingle("SELECT last_notification_check FROM users WHERE id = $user_id", true);
$last_click = ($last_check_row && $last_check_row['last_notification_check']) 
              ? $last_check_row['last_notification_check'] 
              : '1970-01-01 00:00:00';

$stmt_count = $db->prepare("
    SELECT COUNT(*) FROM notifications 
    WHERE is_read = 0 
    AND created_at > :last_click
    AND (message LIKE '%bio%' OR message LIKE '%phone%')
");
$stmt_count->bindValue(':last_click', $last_click, SQLITE3_TEXT);
$unread_count = $stmt_count->execute()->fetchArray()[0];

$notif_sql = "
    SELECT n.*, s.first_name, s.last_name 
    FROM notifications n
    LEFT JOIN students s ON n.student_id = s.id
    WHERE (n.message LIKE '%bio%' OR n.message LIKE '%phone%')
    ORDER BY n.created_at DESC
    LIMIT 10
";
$notif_result = $db->query($notif_sql);
$notifications = [];
while ($row = $notif_result->fetchArray(SQLITE3_ASSOC)) {
    $notifications[] = $row;
}

if (!defined('COURSE_ALL')) define('COURSE_ALL', 0);
if (!defined('COURSE_BSIS')) define('COURSE_BSIS', 1);
if (!defined('COURSE_ACT')) define('COURSE_ACT', 2);

$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : COURSE_ALL;

switch ($selected_course) {
    case COURSE_BSIS:
        $course_name = "Bachelor of Science in Information Systems";
        $student_title = "BSIS Students";
        break;
    case COURSE_ACT:
        $course_name = "Associate in Computer Technology";
        $student_title = "ACT Students";
        break;
    default:
        $course_name = "All Courses";
        $student_title = "All Students";
        break;
}

if ($selected_course === COURSE_ALL) {
    $student_enrolled = $db->querySingle("SELECT COUNT(id) FROM students");
    $classes_count = $db->querySingle("SELECT COUNT(id) FROM schedules");
    $teachers_count = $db->querySingle("SELECT COUNT(id) FROM teachers");
    $rooms_count = $db->querySingle("SELECT COUNT(DISTINCT room) FROM schedules WHERE room IS NOT NULL AND room != ''");
} else {
    $student_enrolled = $db->querySingle("SELECT COUNT(id) FROM students WHERE course_id = $selected_course");
    $classes_count = $db->querySingle("SELECT COUNT(id) FROM schedules WHERE course_id = $selected_course");
    $teachers_count = $db->querySingle("SELECT COUNT(DISTINCT teacher_id) FROM schedules WHERE course_id = $selected_course");
    $rooms_count = $db->querySingle("SELECT COUNT(DISTINCT room) FROM schedules WHERE room IS NOT NULL AND room != '' AND course_id = $selected_course");
}

$last_update_text = "Just now";

$last_activity_row = $db->querySingle("SELECT last_activity FROM admin_system_log WHERE id = 1", true);

if ($last_activity_row) {
    $last_update_ts = (int)$last_activity_row['last_activity'];
    $time_diff = time() - $last_update_ts;
    
    if ($time_diff < 60) {
        $last_update_text = "Just now";
    } else {
        $tokens = [
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
            1 => 'second'
        ];

        foreach ($tokens as $unit => $text) {
            if ($time_diff < $unit) continue;
            $numberOfUnits = floor($time_diff / $unit);
            $last_update_text = $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
            break;
        }
    }
}

$sql_sched = "
    SELECT 
        sch.*, 
        sub.subject_name AS subject_name_display, 
        t.name AS teacher_name_display 
    FROM schedules sch
    LEFT JOIN subjects sub ON sch.subject_id = sub.id
    LEFT JOIN teachers t ON sch.teacher_id = t.id
";

if ($selected_course !== COURSE_ALL) {
    $sql_sched .= " WHERE sch.course_id = :course_id";
} else {
    $sql_sched .= " GROUP BY sch.subject_id, sch.teacher_id, sch.room, sch.time_start, sch.time_end, sch.day";
}

$sql_sched .= " ORDER BY sch.time_start ASC";

$stmt = $db->prepare($sql_sched);
if ($selected_course !== COURSE_ALL) {
    $stmt->bindValue(':course_id', $selected_course, SQLITE3_INTEGER);
}
$sched_result = $stmt->execute();


$sql_students = "SELECT * FROM students";

if ($selected_course !== COURSE_ALL) {
    $sql_students .= " WHERE course_id = :course_id";
}

$sql_students .= " ORDER BY last_name ASC";

$stmt_students = $db->prepare($sql_students);

if ($selected_course !== COURSE_ALL) {
    $stmt_students->bindValue(':course_id', $selected_course, SQLITE3_INTEGER);
}

$students_result = $stmt_students->execute();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSched Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="../img/logo.jpg" width="50" height="50" class="me-2">
                <span class="fw-bold text-primary">Class</span><span class="text-primary">Sched</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
                </ul>
            </div>

            <div class="d-flex align-items-center">
                
                <div class="dropdown notification-container me-4 position-relative">
                    <i class="fa-solid fa-bell dropdown-toggle" 
                       id="notificationDropdown" 
                       data-bs-toggle="dropdown" 
                       aria-expanded="false" 
                       style="font-size: 1.2rem;">
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

                <div class="dropdown">
                    <button class="btn btn-admin dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Admin • <?php echo htmlspecialchars(substr($user["username"], 0, 2)); ?>
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

    <div class="container mb-5">
        <div class="row hero-section align-items-end">
            <div class="col-md-6">
                <h1 class="fw-bold">Hi, <?php echo htmlspecialchars($user["username"]); ?>!</h1>
                <p class="text-secondary mb-0">Here's what's happening today.</p>
            </div>
            <div class="col-md-6 last-update">
                Last update: <strong><?php echo $last_update_text; ?></strong>
            </div>
            <div class="col-12 mt-3">
                <hr>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-3">
                <form action="" method="GET">
                    <select name="course_id" class="form-select form-select-lg" onchange="this.form.submit()">
                        <option value="<?php echo COURSE_ALL; ?>" <?php if($selected_course == COURSE_ALL) echo 'selected'; ?>>All Courses</option>
                        <option value="<?php echo COURSE_BSIS; ?>" <?php if($selected_course == COURSE_BSIS) echo 'selected'; ?>>BSIS</option>
                        <option value="<?php echo COURSE_ACT; ?>" <?php if($selected_course == COURSE_ACT) echo 'selected'; ?>>ACT</option>
                    </select>
                </form>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-label">Students</div>
                    <div class="stats-number"><?php echo $student_enrolled; ?></div>
                    <div class="stats-sub">Active / Enrolled</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-label">Classes</div>
                    <div class="stats-number"><?php echo $classes_count; ?></div>
                    <div class="stats-sub">Total schedule entries</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-label">Teachers</div>
                    <div class="stats-number"><?php echo $teachers_count; ?></div>
                    <div class="stats-sub">Active / Assigned</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-label">Rooms</div>
                    <div class="stats-number"><?php echo $rooms_count; ?></div>
                    <div class="stats-sub">Used rooms</div>
                </div>
            </div>
        </div>

        <div class="schedule-section mt-5">
            <h5 class="mb-4"><?php echo $course_name; ?> Class Schedule</h5>
            
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Room</th>
                            <th>Time start</th>
                            <th>Time end</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $sched_result->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['day'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($row['subject_name_display'] ?? 'Unknown Subject'); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name_display'] ?? 'Unknown Teacher'); ?></td>
                            <td><?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?></td>
                            <td>
                                <?php 
                                    if (!empty($row['time_start'])) {
                                        echo date("h:i A", strtotime($row['time_start']));
                                    } else {
                                        echo '--:--';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    if (!empty($row['time_end'])) {
                                        echo date("h:i A", strtotime($row['time_end']));
                                    } else {
                                        echo '--:--';
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="schedule-section mt-5">
            <h5 class="mb-4"><?php echo $student_title; ?> List</h5>
            
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Last Name</th>
                            <th>First Name</th>
                            <th>Middle Name</th>
                            <th>Age</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $students_result->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['student_number'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['last_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['middle_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['age'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['year_level'] ?? '--'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>