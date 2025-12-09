<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ ."/notification.php";
require_once __DIR__ ."/db.php";

$user = $_SESSION['user'];
$db = get_db();

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

$display_name = $student['first_name'] ?: $user['name'];
$fullName = trim($student['first_name'] . ' ' . $student['last_name']);
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';
$initials = ($l_initial === '') ? strtoupper(substr($user['name'], 0, 2)) : $f_initial . $l_initial;

$notif_data = notif('student', true); 
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];
$highlight_count = $notif_data['highlight_count'];

$selected_day = $_GET['day'] ?? 'All'; 
$valid_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($selected_day !== 'All' && !in_array($selected_day, $valid_days)) {
    $selected_day = "Monday";
}

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
    sch.time_start ASC";

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

// Theme Color (Brand Blue)
$themeColor = '#3b66d1'; 

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    ob_start();
    
    $hasRows = false;
    
    foreach ($schedule_data as $row): 
        $hasRows = true;
        $currentColor = $themeColor;
        
        // Format Start and End Time
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ClassSched | Schedule</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_schedule.css">
    <link rel="stylesheet" href="../styles/student.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>
<body class="d-flex flex-column min-vh-100 position-relative">

    <nav class="navbar navbar-expand-sm sticky-top">
        <div class="container-fluid px-4">
            
            <div class="d-flex align-items-center">
                <button class="navbar-toggler me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <a class="navbar-brand me-0" href="student_dashboard.php">
                    <img src="../img/logo.png" width="60" height="60">
                </a>
            </div>
            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <img src="../img/logo.png" width="60" height="60" class="me-2">
                <h5 class="offcanvas-title" id="offcanvasNavbarLabel"></h5>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav justify-content-start flex-grow-1 pe-3">
                    <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="student_schedule.php">Class Schedule</a></li>
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
                                                    <?php echo htmlspecialchars(!empty($notif['first_name']) ? $notif['first_name'] . ' ' . $notif['last_name'] : 'ClassSched Alert'); ?>
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
                    <button class="btn btn-student dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <span class="student-text">Student • </span><?php echo htmlspecialchars($initials); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-1"><small>Signed in as<br><b><?php echo htmlspecialchars($fullName); ?></b></small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="student_profile.php">Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    <div class="container px-4 py-5" style="max-width: 1000px;">
        
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="fw-bold text-dark mb-2">Full Schedule</h1>
                <p class="text-secondary mb-0 small">View your complete class timetable.</p>
            </div>
            
            <form id="scheduleForm" method="GET" action="">
                <select name="day" id="day-select" class="form-select bg-white border shadow-sm" style="width: auto; cursor: pointer; min-width: 150px;">
                    <option value="All" <?php if($selected_day == 'All') echo 'selected'; ?>>All Days</option>
                    <?php foreach($valid_days as $d): ?>
                        <option value="<?php echo $d; ?>" <?php if($selected_day == $d) echo 'selected'; ?>>
                            <?php echo $d; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div id="schedule-body" class="schedule-list">
            <?php 
            $hasRows = false;
            foreach ($schedule_data as $row): 
                $hasRows = true;
                $currentColor = $themeColor;
                
                // Format Start and End Time
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
    </div>

    <footer class="bg-white border-top py-4 text-center mt-auto">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
    <script src="../js/day.js"></script>
</body>
</html>