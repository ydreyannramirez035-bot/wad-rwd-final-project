<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}

require_once __DIR__ ."/notification.php";
require_once __DIR__ ."/db.php";

date_default_timezone_set('Asia/Manila');
$today = date('l'); 
$user = $_SESSION['user'];
$db = get_db();

$selected_day = $_GET['day'] ?? $today; 
$valid_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($selected_day !== 'All' && !in_array($selected_day, $valid_days)) {
    $selected_day = $today;
}

$user_id = $user['id'];
$student = $db->querySingle("SELECT * FROM students WHERE user_id = $user_id", true);

if (!$student) {
    $student = [
        'id' => 0,
        'first_name' => $user['name'], 
        'last_name' => '',
        'course_id' => 0,
        'year_level' => 1
    ];
}

$course_id = (int)$student['course_id'];

$sqlSched = "
    SELECT sch.*, sub.subject_name, t.name as teacher_name
    FROM schedules sch
    LEFT JOIN subjects sub ON sch.subject_id = sub.id
    LEFT JOIN teachers t ON sch.teacher_id = t.id
    WHERE sch.course_id = :cid
";

if ($selected_day !== 'All') {
    $sqlSched .= " AND sch.day = :day";
}

$sqlSched .= " ORDER BY 
    CASE 
        WHEN sch.day = 'Monday' THEN 1
        WHEN sch.day = 'Tuesday' THEN 2
        WHEN sch.day = 'Wednesday' THEN 3
        WHEN sch.day = 'Thursday' THEN 4
        WHEN sch.day = 'Friday' THEN 5
        WHEN sch.day = 'Saturday' THEN 6
        WHEN sch.day = 'Sunday' THEN 7
        ELSE 8
    END,
    sch.time_start ASC LIMIT 5";

$stmt = $db->prepare($sqlSched);
$stmt->bindValue(':cid', $course_id, SQLITE3_INTEGER);

if ($selected_day !== 'All') {
    $stmt->bindValue(':day', $selected_day, SQLITE3_TEXT);
}

$sched_result = $stmt->execute();
$schedule_data = [];

while ($row = $sched_result->fetchArray(SQLITE3_ASSOC)) {
    $schedule_data[] = $row;
}

$classes_today_count = count($schedule_data); 
$stats_label = ($selected_day === 'All') ? 'Total Classes' : 'Classes ' . $selected_day;

// Define Theme Color
$themeColor = '#3b66d1'; 

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
    
    $hasRows = false;
    foreach ($schedule_data as $row): 
        $hasRows = true;
        $currentColor = $themeColor;
        $start_time = $row['time_start'] ? date("H:i", strtotime($row['time_start'])) : '--';
        $end_time   = $row['time_end']   ? date("H:i", strtotime($row['time_end']))   : '';
    ?>
        <div class="schedule-card mb-3 shadow-sm border-0">
            <div class="time-col">
                <div><?php echo $start_time; ?></div>
                <?php if($end_time): ?>
                    <div style="font-size: 0.8em; color: #9ca3af; font-weight: 500; margin-top: 2px;">
                        <?php echo $end_time; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="accent-bar" style="background-color: <?php echo $currentColor; ?>;"></div>
            
            <div class="subject-info">
                <div class="subject-name" style="color: <?php echo $currentColor; ?>;">
                    <?php echo htmlspecialchars($row['subject_name']); ?>
                </div>
                <div class="room-badge">
                    <?php if($selected_day === 'All'): ?>
                        <span class="badge bg-light text-dark border me-1"><?php echo substr($row['day'], 0, 3); ?></span>
                    <?php endif; ?>
                    Rm <?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?>
                </div>
            </div>
            
            <div class="teacher-col">
                <i class="fa-regular fa-user"></i>
                <?php echo htmlspecialchars($row['teacher_name']); ?>
            </div>
        </div>

    <?php endforeach; ?>

    <?php if (!$hasRows): ?>
        <div class="text-center py-5">
            <i class="fa-regular fa-calendar-xmark empty-state-icon"></i>
            <p class="text-muted mb-0">
                <?php if ($selected_day === 'All'): ?>
                    No classes scheduled for this week.
                <?php else: ?>
                    No classes scheduled for <?php echo htmlspecialchars($selected_day); ?>.
                <?php endif; ?>
            </p>
        </div>
    <?php endif;

    $table_html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'html' => $table_html,
        'count' => $classes_today_count,
        'label' => $stats_label
    ]);
    exit; 
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$notif_data = notif('student', true); 
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];
$highlight_count = $notif_data['highlight_count'];

$display_name = $student['first_name'] ?: $user['name'];
$fullName = trim($student['first_name'] . ' ' . $student['last_name']);
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';

if ($l_initial === '') {
    $initials = strtoupper(substr($user['name'], 0, 2));
} else {
    $initials = $f_initial . $l_initial;
}

$sqlSubs = "SELECT COUNT(DISTINCT subject_id) FROM schedules WHERE course_id = :cid";
$stmtSub = $db->prepare($sqlSubs);
$stmtSub->bindValue(':cid', $course_id, SQLITE3_INTEGER);
$subjects_count = $stmtSub->execute()->fetchArray()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ClassSched | Student Dashboard</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_dashboard.css">
    <link rel="stylesheet" href="../styles/student.css">
    <link rel="stylesheet" href="../styles/notification.css">    
</head>

<body class="d-flex flex-column min-vh-100 position-relative">
    <?php require_once __DIR__ . "/student_nav.php"; ?>

    <div class="container px-4 py-5">
        
        <div class="row align-items-end mb-5">
            <div class="col-md-6">
                <h1 class="fw-bold text-dark mb-4">Hi, <?php echo htmlspecialchars($display_name); ?>!</h1>
                <p class="text-secondary mb-0">Here's what's happening today.</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-normal">
                    <i class="fa-regular fa-calendar me-1"></i> Today: <strong><?php echo date("F j, Y"); ?></strong>
                </span>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label" id="stats-label">
                                <?php echo $stats_label; ?>
                            </div>
                            <div class="stats-number" id="stats-number"><?php echo $classes_today_count; ?></div>
                            <div class="stats-sub">Scheduled</div>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3">
                            <i class="fa-solid fa-calendar-day fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stats-label">Subjects</div>
                            <div class="stats-number"><?php echo $subjects_count; ?></div>
                            <div class="stats-sub">Enrolled</div>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 text-info p-3">
                            <i class="fa-solid fa-book-open fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-4 shadow-sm border p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="fa-regular fa-clock text-brand-blue"></i>
                    Today's Class Schedule
                </h5>
                
                <div>
                    <select id="day-select" class="form-select bg-light border-0" style="width: auto; cursor: pointer;">
                        <option value="All" <?php if($selected_day == 'All') echo 'selected'; ?>>All Days</option>
                        <?php foreach($valid_days as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if($selected_day == $d) echo 'selected'; ?>>
                                <?php echo $d; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- CHANGED: Replaced Table with Card Layout -->
            <div id="schedule-body" class="schedule-list">
                <?php 
                $hasRows = false;
                foreach ($schedule_data as $row): 
                    $hasRows = true;
                    $currentColor = $themeColor;
                    $start_time = $row['time_start'] ? date("H:i", strtotime($row['time_start'])) : '--';
                    $end_time   = $row['time_end']   ? date("H:i", strtotime($row['time_end']))   : '';
                ?>
                    <div class="schedule-card mb-3 shadow-sm border-0">
                        <div class="time-col">
                            <div><?php echo $start_time; ?></div>
                            <?php if($end_time): ?>
                                <div style="font-size: 0.8em; color: #9ca3af; font-weight: 500; margin-top: 2px;">
                                    <?php echo $end_time; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="accent-bar" style="background-color: <?php echo $currentColor; ?>;"></div>
                        
                        <div class="subject-info">
                            <div class="subject-name" style="color: <?php echo $currentColor; ?>;">
                                <?php echo htmlspecialchars($row['subject_name']); ?>
                            </div>
                            <div class="room-badge">
                                <?php if($selected_day === 'All'): ?>
                                    <span class="badge bg-light text-dark border me-1"><?php echo substr($row['day'], 0, 3); ?></span>
                                <?php endif; ?>
                                Rm <?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?>
                            </div>
                        </div>
                        
                        <div class="teacher-col">
                            <i class="fa-regular fa-user"></i>
                            <?php echo htmlspecialchars($row['teacher_name']); ?>
                        </div>
                    </div>

                <?php endforeach; ?>

                <?php if (!$hasRows): ?>
                    <div class="text-center py-5">
                        <i class="fa-regular fa-calendar-xmark empty-state-icon"></i>
                        <p class="text-muted mb-0">
                            <?php if ($selected_day === 'All'): ?>
                                No classes scheduled for this week.
                            <?php else: ?>
                                No classes scheduled for <?php echo htmlspecialchars($selected_day); ?>.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- END CHANGED SECTION -->

            <div class="text-center mt-4">
                <a href="student_schedule.php" class="btn btn-outline-primary rounded-pill px-4">View Full Schedule</a>
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
    <script src="../js/day.js"></script>
</body>
</html>