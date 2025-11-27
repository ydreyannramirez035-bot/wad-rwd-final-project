<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];

require_once __DIR__ ."/db.php";
$db = get_db();

// --- 1. HANDLE CONSTANTS & DEFAULTS ---
if (!defined('COURSE_ALL')) define('COURSE_ALL', 0); // Added ID for All
if (!defined('COURSE_BSIS')) define('COURSE_BSIS', 1);
if (!defined('COURSE_ACT')) define('COURSE_ACT', 2);

// Get current course from URL or default to ALL
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : COURSE_ALL;

// Determine Course Name Display
switch ($selected_course) {
    case COURSE_BSIS:
        $course_name = "Bachelor of Science in Information Systems";
        break;
    case COURSE_ACT:
        $course_name = "Associate in Computer Technology";
        break;
    default:
        $course_name = "All Courses";
        break;
}

// --- 2. DYNAMIC STATISTICS ---
if ($selected_course === COURSE_ALL) {
    // Get TOTALS for everything
    $student_enrolled = $db->querySingle("SELECT COUNT(id) FROM students");
    $classes_count = $db->querySingle("SELECT COUNT(id) FROM schedules");
    $teachers_count = $db->querySingle("SELECT COUNT(id) FROM teachers");
    $rooms_count = $db->querySingle("SELECT COUNT(DISTINCT room) FROM schedules WHERE room IS NOT NULL AND room != ''");
} else {
    // Get FILTERED counts based on selected course
    
    // Students enrolled in this course
    $student_enrolled = $db->querySingle("SELECT COUNT(id) FROM students WHERE course_id = $selected_course");
    
    // Classes (schedule entries) for this course
    $classes_count = $db->querySingle("SELECT COUNT(id) FROM schedules WHERE course_id = $selected_course");
    
    // Teachers teaching this course (Count distinct teachers in the schedule for this course)
    $teachers_count = $db->querySingle("SELECT COUNT(DISTINCT teacher_id) FROM schedules WHERE course_id = $selected_course");
    
    // Rooms used by this course
    $rooms_count = $db->querySingle("SELECT COUNT(DISTINCT room) FROM schedules WHERE room IS NOT NULL AND room != '' AND course_id = $selected_course");
}

// --- 3. LAST UPDATE LOGIC (DYNAMIC TIME AGO) ---
// FIX: Point to the actual database file defined in db.php
$db_file_path = __DIR__ . '/../users.db'; 
$last_update_text = "Unknown";

// FIX: Clear cache to get the immediate update time
clearstatcache();

if (file_exists($db_file_path)) {
    $file_mod_time = filemtime($db_file_path);
    $time_diff = time() - $file_mod_time;
    
    // Logic for dynamic "Time Ago"
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

// --- 4. FETCH SCHEDULES WITH JOIN ---
// Build Query based on selection
$sql_sched = "
    SELECT 
        sch.*, 
        sub.subject_name AS subject_name_display, 
        t.name AS teacher_name_display 
    FROM schedules sch
    LEFT JOIN subjects sub ON sch.subject_id = sub.id
    LEFT JOIN teachers t ON sch.teacher_id = t.id
";

// Add WHERE clause if a specific course is selected
if ($selected_course !== COURSE_ALL) {
    $sql_sched .= " WHERE sch.course_id = :course_id";
} else {
    // FIX: Group by details to merge duplicates when showing 'All Courses'
    // Added sch.day to GROUP BY to ensure classes on different days stay separate
    $sql_sched .= " GROUP BY sch.subject_id, sch.teacher_id, sch.room, sch.time_start, sch.time_end, sch.day";
}

$sql_sched .= " ORDER BY sch.time_start ASC";

$stmt = $db->prepare($sql_sched);

// Bind parameter only if not ALL
if ($selected_course !== COURSE_ALL) {
    $stmt->bindValue(':course_id', $selected_course, SQLITE3_INTEGER);
}

$sched_result = $stmt->execute();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSched Dashboard</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../admin_dashboard.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span class="logo-circle">LOGO</span>ClassSched
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
                </ul>
            </div>

            <div class="d-flex align-items-center">
                <i class="fa-solid fa-bell me-4" style="font-size: 1.2rem; cursor: pointer;"></i>
                <div class="dropdown">
                    <button class="btn btn-admin dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Admin • <?php echo htmlspecialchars(substr($user["name"], 0, 2)); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-1"><small>Signed in as<br><b><?php echo htmlspecialchars($user["name"]); ?></b></small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <!-- Hero Section -->
        <div class="row hero-section align-items-end">
            <div class="col-md-6">
                <h1 class="fw-bold">Hi, <?php echo htmlspecialchars($user["name"]); ?>!</h1>
                <p class="text-secondary mb-0">Here's what's happening today.</p>
            </div>
            <div class="col-md-6 last-update">
                Last update: <strong><?php echo $last_update_text; ?></strong>
            </div>
            <div class="col-12 mt-3">
                <hr>
            </div>
        </div>

        <!-- Filter Dropdown -->
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

        <!-- Stats Cards -->
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

        <!-- Schedule Table -->
        <div class="schedule-section">
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
                            <!-- Added Day Column -->
                            <td><?php echo htmlspecialchars($row['day'] ?? '--'); ?></td>
                            <td><?php echo htmlspecialchars($row['subject_name_display'] ?? 'Unknown Subject'); ?></td>
                            <td><?php echo htmlspecialchars($row['teacher_name_display'] ?? 'Unknown Teacher'); ?></td>
                            <td><?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?></td>
                            <td>
                                <?php 
                                    // FORMAT TIME START to AM/PM
                                    if (!empty($row['time_start'])) {
                                        echo date("h:i A", strtotime($row['time_start']));
                                    } else {
                                        echo '--:--';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php 
                                    // FORMAT TIME END to AM/PM
                                    if (!empty($row['time_end'])) {
                                        echo date("h:i A", strtotime($row['time_end']));
                                    } else {
                                        echo '--:--';
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if(!isset($row)): ?>
                            <!-- Optional: Show if no rows found -->
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>