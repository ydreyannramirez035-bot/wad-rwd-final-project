<?php
session_start();
require_once __DIR__ . "/db.php";

// 1. Security Check
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
$db = get_db();

// 2. Fetch Student Details
$user_id = $user['id'];
$student = $db->querySingle("SELECT * FROM students WHERE user_id = $user_id", true);

if (!$student) {
    $student = [
        'first_name' => $user['name'], 
        'last_name' => '',
        'course_id' => 0,
        'year_level' => 1
    ];
}

$course_id = (int)$student['course_id'];
$display_name = $student['first_name'] ?: $user['name']; 

// CALCULATE INITIALS
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';

if ($l_initial === '') {
    $initials = strtoupper(substr($user['name'], 0, 2));
} else {
    $initials = $f_initial . $l_initial;
}

// 3. Handle Day Selection
$selected_day = $_GET['day'] ?? date('l'); 
$valid_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($selected_day !== 'All' && !in_array($selected_day, $valid_days)) {
    $selected_day = "Monday";
}

// 4. STATS LOGIC
$actual_day = date('l');
$sqlToday = "SELECT COUNT(*) FROM schedules WHERE course_id = :cid AND day = :day";
$stmt = $db->prepare($sqlToday);
$stmt->bindValue(':cid', $course_id, SQLITE3_INTEGER);
$stmt->bindValue(':day', $actual_day, SQLITE3_TEXT);
$classes_today_count = $stmt->execute()->fetchArray()[0];

$sqlSubs = "SELECT COUNT(DISTINCT subject_id) FROM schedules WHERE course_id = :cid";
$stmt = $db->prepare($sqlSubs);
$stmt->bindValue(':cid', $course_id, SQLITE3_INTEGER);
$subjects_count = $stmt->execute()->fetchArray()[0];

// 5. FETCH SCHEDULE DATA
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ClassSched | Student Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../student_dashboard.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span style="background:#94a3b8; color:white; padding:5px 10px; border-radius:50%; font-size:14px; vertical-align:middle; margin-right:5px;">LOGO</span>
                Class<span class="brand-blue">Sched</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-center" id="navContent">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="student_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="student_schedule.php">Class Schedule</a></li>
                </ul>
            </div>

            <div class="d-flex align-items-center">
                <i class="fa-solid fa-bell notification-icon"></i>
                
                <div class="dropdown">
                    <!-- Profile Toggle -->
                    <div class="profile-container dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="profile-circle">
                            <?php echo $initials; ?>
                        </div>
                        <i class="fa-solid fa-chevron-down profile-chevron"></i>
                    </div>

                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><a class="dropdown-item" href="student_profile.php">Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        
        <div class="mb-5">
            <h1 class="hero-title">Hi, <?php echo htmlspecialchars($display_name); ?>!</h1>
            <p class="hero-sub">Here’s what’s happening today.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label">Classes today</div>
                    <div class="stat-number"><?php echo $classes_today_count; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label">Subjects enrolled</div>
                    <div class="stat-number"><?php echo $subjects_count; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-label">Upcoming events</div>
                    <div class="stat-number">0</div>
                </div>
            </div>
        </div>

        <div class="schedule-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title">Today’s class schedule</div>
                
                <form method="GET" action="">
                    <select name="day" class="form-select form-select-sm" style="width: auto; font-weight:500;" onchange="this.form.submit()">
                        <option value="All" <?php if($selected_day == 'All') echo 'selected'; ?>>All Days</option>
                        <?php foreach($valid_days as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if($selected_day == $d) echo 'selected'; ?>>
                                <?php echo $d; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="15%">Day</th>
                            <th width="30%">Subject</th>
                            <th width="25%">Teacher</th>
                            <th width="15%">Room</th>
                            <th width="15%">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasRows = false;
                        while ($row = $sched_result->fetchArray(SQLITE3_ASSOC)): 
                            $hasRows = true;
                        ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?php echo htmlspecialchars($row['day']); ?></td>
                                <td class="fw-semibold text-primary"><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['room'] ?? 'TBA'); ?></td>
                                <td>
                                    <?php 
                                        $start = $row['time_start'] ? date("h:i A", strtotime($row['time_start'])) : '--';
                                        $end = $row['time_end'] ? date("h:i A", strtotime($row['time_end'])) : '--';
                                        echo "$start - $end";
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (!$hasRows): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <?php if ($selected_day === 'All'): ?>
                                        No classes scheduled for this week.
                                    <?php else: ?>
                                        No classes scheduled for <?php echo htmlspecialchars($selected_day); ?>.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-4">
                <a href="student_schedule.php" class="btn btn-primary rounded-pill px-4">View full sched</a>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>