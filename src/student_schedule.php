<?php
session_start();
require_once __DIR__ . "/db.php";

// 1. Security: Check if logged in
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
$db = get_db();

// 2. Get Student Info (Course, Name)
$user_id = $user['id'];
$student = $db->querySingle("SELECT * FROM students WHERE user_id = $user_id", true);

if (!$student) {
    $student = ['first_name' => $user['name'], 'course_id' => 0];
}

$course_id = (int)$student['course_id'];

// 3. Logic for Profile Initials (First Letter of First Name + First Letter of Last Name)
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';

if ($l_initial === '') {
    $initials = strtoupper(substr($user['name'], 0, 2));
} else {
    $initials = $f_initial . $l_initial;
}

// 4. Handle Day Filter
$selected_day = $_GET['day'] ?? 'All'; // Default to "All" for the full schedule page
$valid_days = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

if ($selected_day !== 'All' && !in_array($selected_day, $valid_days)) {
    $selected_day = 'All';
}

// 5. Fetch Schedule Data from Database
$sql = "
    SELECT sch.*, sub.subject_name, t.name as teacher_name
    FROM schedules sch
    LEFT JOIN subjects sub ON sch.subject_id = sub.id
    LEFT JOIN teachers t ON sch.teacher_id = t.id
    WHERE sch.course_id = :cid
";
if ($selected_day !== 'All') {
    $sql .= " AND sch.day = :day";
}
$sql .= " ORDER BY 
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

$stmt = $db->prepare($sql);
$stmt->bindValue(':cid', $course_id, SQLITE3_INTEGER);

if ($selected_day !== 'All') {
    $stmt->bindValue(':day', $selected_day, SQLITE3_TEXT);
}

$result = $stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class Schedule</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../student_schedule.css">
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
                    <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="student_schedule.php">Class Schedule</a></li>
                </ul>
            </div>

            <div class="d-flex align-items-center">
                <i class="fa-solid fa-bell notification-icon"></i>
                
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
        <!-- HEADER & FILTER SECTION -->
        <div class="mb-4">
            <h2 class="page-title">My class schedule</h2>
            <p class="page-subtitle">View your class schedule per week</p>
            <form method="GET" action="">
                <select name="day" class="form-select day-select" onchange="this.form.submit()">
                    <option value="All" <?php if($selected_day == 'All') echo 'selected'; ?>>All Days</option>
                    <?php foreach($valid_days as $d): ?>
                        <option value="<?php echo $d; ?>" <?php if($selected_day == $d) echo 'selected'; ?>>
                            <?php echo $d; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- SCHEDULE TABLE CARD -->
        <div class="schedule-card">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
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
                        while ($row = $result->fetchArray(SQLITE3_ASSOC)): 
                            $hasRows = true;
                        ?>
                            <tr>
                                <td class="text-secondary"><?php echo htmlspecialchars($row['day']); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['subject_name']); ?></td>
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
</body>
</html>