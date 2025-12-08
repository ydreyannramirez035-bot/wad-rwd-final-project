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
$notif_data = notif('student', true); 
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];
$highlight_count = $notif_data['highlight_count'];

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
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';

// Initials Logic
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';
if ($l_initial === '') {
    $initials = strtoupper(substr($user['name'], 0, 2));
} else {
    $initials = $f_initial . $l_initial;
}

// Schedule Display Logic
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ClassSched | Full Schedule</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_dashboard.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="student_dashboard.php">
                <span style="background:#94a3b8; color:white; padding:5px 10px; border-radius:50%; font-size:14px; vertical-align:middle; margin-right:5px;">LOGO</span>
                Class<span class="brand-blue">Sched</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-center" id="navContent">
                <div class="collapse navbar-collapse justify-content-center" id="navContent">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="student_schedule.php">Class Schedule</a></li>
                </ul>
            </div>
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
                            <?php if ($highlight_count > 0): ?>
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
            <h1 class="hero-title">Class Schedule</h1>
            <p class="hero-sub">View your full weekly schedule.</p>
        </div>

        <div class="schedule-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title">Weekly Schedule</div>
                
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
                                <td colspan="5" class="text-center text-muted py-5">
                                    No classes found for <strong><?php echo htmlspecialchars($selected_day); ?></strong>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>