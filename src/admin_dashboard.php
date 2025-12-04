<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user"])) {
    header("Location: ../index.php");
    exit;
}
$user = $_SESSION["user"];
$user_id = $user['id'];

require_once __DIR__ ."/db.php";
$db = get_db();

// Ensure column exists
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

// Handle Badge Clearing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
    $db->exec("UPDATE users SET last_notification_check = datetime('now', 'localtime') WHERE id = $user_id");
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// Update System Log
$current_db_val = $db->querySingle("SELECT last_activity FROM admin_system_log WHERE id = 1");
$current_time = time();

if (!is_numeric($current_db_val) || ($current_db_val > $current_time + 60)) {
    $db->exec("UPDATE admin_system_log SET last_activity = strftime('%s', 'now') WHERE id = 1");
}

// Handle Clear/Read Notifications
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

// Calculate Unread Count
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

// Fetch Notifications
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

// Course Constants
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

// Fetch Stats
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

// Calculate Time Diff
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

// Fetch Schedules
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

// Fetch Students
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
    <link rel="icon" type="image/x-icon" href="../img/logo.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>
<body class="d-flex flex-column min-vh-100 position-relative">

    <!-- Background Decoration -->
    <div class="blob blob-blue"></div>
    <div class="blob blob-purple"></div>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
                <img src="../img/logo.png" width="50" height="50" class="me-2">
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
                </ul>
            </div>

            <div class="d-flex align-items-center gap-3">
                
                <!-- Notifications -->
                <div class="dropdown notification-container position-relative">
                    <button class="btn btn-link text-secondary p-0 border-0" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-bell fs-5"></i>
                    </button>
                    
                    <?php if ($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                            <?php echo ($unread_count > 9) ? '9+' : $unread_count; ?>
                        </span>
                    <?php endif; ?>

                    <ul class="dropdown-menu dropdown-menu-end notification-list shadow-lg border-0 rounded-4 mt-2" aria-labelledby="notificationDropdown" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center bg-white sticky-top py-3 border-bottom">
                            <span class="fw-bold text-dark">Notifications</span>
                            <?php if ($unread_count > 0): ?>
                                <a href="?action=clear_notifications" class="text-decoration-none small text-brand-blue fw-semibold">Mark read</a>
                            <?php endif; ?>
                        </li>

                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <?php $isUnread = ($notif['is_read'] == 0); ?>
                                <li>
                                    <a class="dropdown-item p-3 border-bottom <?php echo $isUnread ? 'bg-light' : ''; ?>" href="?action=read_notif&id=<?php echo $notif['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <strong class="small text-dark">
                                                <?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?>
                                            </strong>
                                            <?php if ($isUnread): ?>
                                                <span class="badge bg-primary rounded-pill" style="font-size: 0.5rem;">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-secondary small text-wrap mb-1" style="font-size: 0.8rem; line-height: 1.4;">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.7rem;">
                                            <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-center py-5 text-muted small">
                                <i class="fa-regular fa-bell-slash fs-4 mb-2 d-block opacity-50"></i>
                                No notifications yet
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Admin Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-white border rounded-pill px-3 py-1 d-flex align-items-center gap-2 shadow-sm" type="button" data-bs-toggle="dropdown">
                        <div class="rounded-circle bg-brand-blue text-white d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 12px;">
                            <?php echo htmlspecialchars(substr($user["username"], 0, 1)); ?>
                        </div>
                        <span class="small fw-semibold text-secondary">Admin</span>
                        <i class="fa-solid fa-chevron-down text-muted" style="font-size: 10px;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-2">
                        <li class="px-3 py-2">
                            <small class="text-muted d-block" style="font-size: 10px;">SIGNED IN AS</small>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($user["username"]); ?></span>
                        </li>
                        <li><hr class="dropdown-divider my-2"></li>
                        <li>
                            <a class="dropdown-item rounded-2 text-danger fw-medium" href="logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <div class="container py-5">
        
        <!-- Header Section -->
        <div class="row align-items-end mb-5">
            <div class="col-md-6">
                <h1 class="fw-bold display-5 text-dark mb-1">Hi, <?php echo htmlspecialchars($user["username"]); ?>!</h1>
                <p class="text-secondary mb-0">Here's what's happening today.</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-normal">
                    <i class="fa-solid fa-rotate-right me-1"></i> Last update: <strong><?php echo $last_update_text; ?></strong>
                </span>
            </div>
        </div>

        <!-- Filter -->
        <div class="row mb-4">
            <div class="col-md-4">
                <form action="" method="GET">
                    <select name="course_id" class="form-select form-select-lg border-0 shadow-sm bg-light fw-medium text-secondary" style="cursor: pointer;" onchange="this.form.submit()">
                        <option value="<?php echo COURSE_ALL; ?>" <?php if($selected_course == COURSE_ALL) echo 'selected'; ?>>All Courses</option>
                        <option value="<?php echo COURSE_BSIS; ?>" <?php if($selected_course == COURSE_BSIS) echo 'selected'; ?>>BSIS</option>
                        <option value="<?php echo COURSE_ACT; ?>" <?php if($selected_course == COURSE_ACT) echo 'selected'; ?>>ACT</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label">Students</div>
                            <div class="stats-number"><?php echo $student_enrolled; ?></div>
                            <div class="stats-sub">Active / Enrolled</div>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                            <i class="fa-solid fa-users fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label">Classes</div>
                            <div class="stats-number"><?php echo $classes_count; ?></div>
                            <div class="stats-sub">Total entries</div>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 text-info p-3">
                            <i class="fa-regular fa-calendar-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label">Teachers</div>
                            <div class="stats-number"><?php echo $teachers_count; ?></div>
                            <div class="stats-sub">Assigned</div>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3">
                            <i class="fa-solid fa-chalkboard-user fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label">Rooms</div>
                            <div class="stats-number"><?php echo $rooms_count; ?></div>
                            <div class="stats-sub">Currently used</div>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3">
                            <i class="fa-solid fa-door-open fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Section -->
        <div class="bg-white rounded-4 shadow-sm border p-4 mb-5">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <i class="fa-regular fa-calendar text-brand-blue"></i>
                <?php echo $course_name; ?> Schedule
            </h5>
            
            <div class="table-responsive">
                <table class="table custom-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Room</th>
                            <th>Time Start</th>
                            <th>Time End</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $sched_result->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td class="fw-medium text-secondary"><?php echo htmlspecialchars($row['day'] ?? '--'); ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['subject_name_display'] ?? 'Unknown Subject'); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($row['teacher_name_display'] ?? 'Unknown Teacher'); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?></span></td>
                            <td class="text-secondary">
                                <?php echo !empty($row['time_start']) ? date("h:i A", strtotime($row['time_start'])) : '--:--'; ?>
                            </td>
                            <td class="text-secondary">
                                <?php echo !empty($row['time_end']) ? date("h:i A", strtotime($row['time_end'])) : '--:--'; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Students Section -->
        <div class="bg-white rounded-4 shadow-sm border p-4">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-graduate text-brand-blue"></i>
                <?php echo $student_title; ?> List
            </h5>
            
            <div class="table-responsive">
                <table class="table custom-table table-hover mb-0">
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
                            <td class="fw-medium font-monospace text-primary"><?php echo htmlspecialchars($student['student_number'] ?? '--'); ?></td>
                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($student['last_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] ?? '--'); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($student['middle_name'] ?? '--'); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($student['age'] ?? '--'); ?></td>
                            <td><span class="badge bg-brand-blue rounded-pill"><?php echo htmlspecialchars($student['year_level'] ?? '--'); ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>