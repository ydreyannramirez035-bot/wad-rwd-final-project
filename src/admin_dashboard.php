<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../index.php");
    exit;
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ ."/notification.php";
require_once __DIR__ ."/db.php";
$db = get_db();

$user = $_SESSION["user"];
$user_id = $user['id'];

if (!isset($_GET['ajax'])) {
    $notif_data = notif('admin', true);
    $unread_count = $notif_data['unread_count'];
    $notifications = $notif_data['notifications'];
    $highlight_count = $notif_data['highlight_count'];
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
    $assigned_teachers = $db->querySingle("SELECT COUNT(DISTINCT teacher_id) FROM schedules");
    $rooms_count = $db->querySingle("SELECT COUNT(DISTINCT room) FROM schedules WHERE room IS NOT NULL AND room != ''");
} else {
    $student_enrolled = $db->querySingle("SELECT COUNT(id) FROM students WHERE course_id = $selected_course");
    $classes_count = $db->querySingle("SELECT COUNT(id) FROM schedules WHERE course_id = $selected_course");
    $assigned_teachers = $db->querySingle("SELECT COUNT(DISTINCT teacher_id) FROM schedules WHERE course_id = $selected_course");
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
$students_result = $stmt_students->execute();

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    
    ob_start();
    while ($row = $sched_result->fetchArray(SQLITE3_ASSOC)) {
        ?>
        <tr>
            <td class="fw-medium text-secondary"><?php echo htmlspecialchars($row['day'] ?? '--'); ?></td>
            <td class="fw-medium text-dark"><?php echo htmlspecialchars($row['subject_name_display'] ?? 'Unknown Subject'); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($row['teacher_name_display'] ?? 'Unknown Teacher'); ?></td>
            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?></span></td>
            <td class="text-secondary">
                <?php echo !empty($row['time_start']) ? date("h:i A", strtotime($row['time_start'])) : '--:--'; ?>
            </td>
            <td class="text-secondary">
                <?php echo !empty($row['time_end']) ? date("h:i A", strtotime($row['time_end'])) : '--:--'; ?>
            </td>
        </tr>
        <?php
    }
    $sched_html = ob_get_clean();
    if (empty($sched_html)) {
        $sched_html = '<tr><td colspan="6" class="text-center py-4 text-muted">No schedules found for this course.</td></tr>';
    }

    ob_start();
    while ($student = $students_result->fetchArray(SQLITE3_ASSOC)) {
        ?>
        <tr>
            <td class="fw-medium font-monospace text-primary"><?php echo htmlspecialchars($student['student_number'] ?? '--'); ?></td>
            <td class="fw-medium text-dark"><?php echo htmlspecialchars($student['last_name'] ?? '--'); ?></td>
            <td><?php echo htmlspecialchars($student['first_name'] ?? '--'); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($student['middle_name'] ?? '--'); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($student['age'] ?? '--'); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($student['year_level'] ?? '--'); ?></td>
        </tr>
        <?php
    }
    $student_html = ob_get_clean();
    if (empty($student_html)) {
        $student_html = '<tr><td colspan="6" class="text-center py-4 text-muted">No students found.</td></tr>';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'stats' => [
            'students' => $student_enrolled,
            'classes' => $classes_count,
            'teachers' => $assigned_teachers,
            'rooms' => $rooms_count
        ],
        'titles' => [
            'course' => $course_name,
            'student_title' => $student_title
        ],
        'html' => [
            'schedule' => $sched_html,
            'students' => $student_html
        ],
        'last_update' => $last_update_text
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSched | Dashboard</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../styles/admin.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>

<body class="d-flex flex-column min-vh-100 position-relative">
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
                        <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-3">
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
                            <?php if ($highlight_count > 0): ?> <a href="?action=clear_notifications" class="text-decoration-none small text-primary">Mark all read</a>
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

    <div class="container px-4 py-5">
        
        <div class="row align-items-end mb-5">
            <div class="col-md-6">
                <h1 class="fw-bold text-dark mb-4">Hi, <?php echo htmlspecialchars($user["username"]); ?>!</h1>
                <p class="text-secondary mb-0">Here's what's happening today.</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-normal">
                    <i class="fa-solid fa-rotate-right me-1"></i> Last update: <strong id="last_update_display"><?php echo $last_update_text; ?></strong>
                </span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <select id="course_select" class="form-select bg-light border-0" style="cursor: pointer;" onchange="fetchDashboardData(this.value)">
                    <option value="<?php echo COURSE_ALL; ?>" <?php if($selected_course == COURSE_ALL) echo 'selected'; ?>>All Courses</option>
                    <option value="<?php echo COURSE_BSIS; ?>" <?php if($selected_course == COURSE_BSIS) echo 'selected'; ?>>BSIS</option>
                    <option value="<?php echo COURSE_ACT; ?>" <?php if($selected_course == COURSE_ACT) echo 'selected'; ?>>ACT</option>
                </select>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <a href="#stds-overview" class="text-decoration-none">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stats-label">Students</div>
                                    <div class="stats-number" id="stat_students"><?php echo $student_enrolled; ?></div>
                                    <div class="stats-sub">Active / Enrolled</div>
                                </div>
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                                <i class="fa-solid fa-users fs-4"></i>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label">Classes</div>
                            <div class="stats-number" id="stat_classes"><?php echo $classes_count; ?></div>
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
                                <div class="stats-number" id="stat_teachers"><?php echo $assigned_teachers; ?></div>
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
                            <div class="stats-number" id="stat_rooms"><?php echo $rooms_count; ?></div>
                            <div class="stats-sub">Currently used</div>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3">
                            <i class="fa-solid fa-door-open fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-4 shadow-sm border p-4 mb-5">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <i class="fa-regular fa-calendar text-brand-blue"></i>
                <span id="header_course_name"><?php echo $course_name; ?></span> Schedule
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
                    <tbody id="schedule_table_body">
                        <?php while ($row = $sched_result->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td class="fw-medium text-secondary"><?php echo htmlspecialchars($row['day'] ?? '--'); ?></td>
                            <td class="fw-medium text-dark"><?php echo htmlspecialchars($row['subject_name_display'] ?? 'Unknown Subject'); ?></td>
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

        <div class="bg-white rounded-4 shadow-sm border p-4">
            <h5 class="fw-bold mb-4 d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-graduate text-brand-blue me-2"></i>
                <span id="header_student_title"><?php echo $student_title; ?></span> List
            </h5>
            
            <div id="stds-overview" class="table-responsive">
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
                    <tbody id="student_table_body">
                        <?php while ($student = $students_result->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td class="fw-medium font-monospace text-primary"><?php echo htmlspecialchars($student['student_number'] ?? '--'); ?></td>
                            <td class="fw-medium text-dark"><?php echo htmlspecialchars($student['last_name'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] ?? '--'); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($student['middle_name'] ?? '--'); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($student['age'] ?? '--'); ?></td>
                            <td class="text-secondary"><?php echo htmlspecialchars($student['year_level'] ?? '--'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <footer class="bg-white border-top py-4 text-center">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
    <script src="../js/load.js"></script>
    <script src="../js/course.js"></script>
</body>
</html>