<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . "/db.php";
$db = get_db();

define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
$user_id = $user['id']; // Needed for notification logic

// --- 1. Auto-Migration: Ensure Column Exists (Safety Check) ---
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

// --- 2. AJAX Handler for Bell Click (Syncs with Dashboard) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
    $db->exec("UPDATE users SET last_notification_check = datetime('now', 'localtime') WHERE id = $user_id");
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// --- Existing GET Actions ---
if (isset($_GET['action']) && $_GET['action'] === 'clear_notifications') {
    $db->exec("UPDATE notifications SET is_read = 1 
               WHERE is_read = 0 
               AND (message LIKE '%bio%' OR message LIKE '%phone%')");
    header("Location: admin_schedule.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'read_notif' && isset($_GET['id'])) {
    $notif_id = (int)$_GET['id'];
    $db->exec("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
    header("Location: admin_student_manage.php"); 
    exit;
}

// --- 3. Read Timestamp & Count Unread Notifications ---
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

// --- 4. Fetch Notifications List ---
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

// FETCH DATA
$subjectOptions = [];
$subRes = $db->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC");
while ($row = $subRes->fetchArray(SQLITE3_ASSOC)) { $subjectOptions[] = $row; }

$teacherOptions = [];
$teachRes = $db->query("SELECT id, name FROM teachers ORDER BY name ASC");
while ($row = $teachRes->fetchArray(SQLITE3_ASSOC)) { $teacherOptions[] = $row; }

$courseOptions = [];
$courseRes = $db->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
while ($row = $courseRes->fetchArray(SQLITE3_ASSOC)) { $courseOptions[] = $row; }


// Initialize Variables
$action = $_GET["action"] ?? "list";
$msg    = $_GET["msg"] ?? "";
$error  = "";

// --- HANDLE AJAX LIST ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search        = trim($_GET['q'] ?? '');
    $filterCourse  = (int)($_GET['filter_course'] ?? 0);
    $sortBy        = $_GET['sort_by'] ?? 'time_start';

    $sortMap = [
        'time_start' => 's.time_start',
        'time_end'   => 's.time_end',
        'day'        => 's.day'
    ];
    $orderBy = $sortMap[$sortBy] ?? 's.time_start';

    $sql = "SELECT s.*, 
                   sub.subject_name, 
                   t.name as teacher_name, 
                   c.course_name 
            FROM schedules s
            LEFT JOIN subjects sub ON s.subject_id = sub.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            LEFT JOIN courses c ON s.course_id = c.id
            WHERE 1=1";

    if ($search) {
        $sql .= " AND (sub.subject_name LIKE :search OR t.name LIKE :search OR s.room LIKE :search)";
    }
    if ($filterCourse > 0) {
        $sql .= " AND s.course_id = :course";
    }
    // Grouping to merge rows visually
    $sql .= " GROUP BY s.day, s.time_start, s.room, s.teacher_id"; 
    $sql .= " ORDER BY $orderBy ASC";

    $stmt = $db->prepare($sql);
    if ($search) $stmt->bindValue(':search', "%$search%", SQLITE3_TEXT);
    if ($filterCourse > 0) $stmt->bindValue(':course', $filterCourse, SQLITE3_INTEGER);
    $result = $stmt->execute();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['day']); ?></td>
            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
            <td><?php echo htmlspecialchars($row['room']); ?></td>
            <td><?php echo $row['time_start'] ? date("h:i A", strtotime($row['time_start'])) : ''; ?></td>
            <td><?php echo $row['time_end'] ? date("h:i A", strtotime($row['time_end'])) : ''; ?></td>
            <td>
                <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn-link">Edit</a> </br>
                <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this schedule?');" class="text-danger">Delete</a>
            </td>
        </tr>
        <?php
    }
    exit;
}

// --- HANDLE STORE (Create) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "store") {
    $day = trim($_POST["day"]); 
    $subjectId = (int)$_POST["subject_id"]; 
    $teacherId = (int)$_POST["teacher_id"];
    $room = trim($_POST["room"]); 
    $time_start = trim($_POST["time_start"]);
    $time_end = trim($_POST["time_end"]);
    $selectedCourses = $_POST["course_ids"] ?? [];

    if (empty($selectedCourses)) {
        $error = "Please select at least one course.";
        $action = "create";
    } else {
        $stmtInsert = $db->prepare("INSERT INTO schedules (day, subject_id, teacher_id, room, time_start, time_end, course_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $count = 0;
        foreach ($selectedCourses as $courseId) {
            $stmtInsert->reset();
            $stmtInsert->bindValue(1, $day);
            $stmtInsert->bindValue(2, $subjectId);
            $stmtInsert->bindValue(3, $teacherId);
            $stmtInsert->bindValue(4, $room);
            $stmtInsert->bindValue(5, $time_start);
            $stmtInsert->bindValue(6, $time_end);
            $stmtInsert->bindValue(7, (int)$courseId);
            $stmtInsert->execute();
            $count++;
        }
        
        header("Location: admin_schedule.php?msg=Saved+$count+schedule(s)");
        exit;
    }
}

// --- HANDLE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "update") {
    $id = (int)$_POST["id"];
    $selectedCourses = $_POST["course_ids"] ?? [];
    
    if (empty($selectedCourses)) {
        $error = "Please select at least one course.";
        $action = "edit";
    } else {
        $oldRow = $db->querySingle("SELECT * FROM schedules WHERE id=$id", true);

        if ($oldRow) {
            $stmtDel = $db->prepare("DELETE FROM schedules WHERE day=:d AND room=:r AND time_start=:ts AND teacher_id=:t");
            $stmtDel->bindValue(':d', $oldRow['day']);
            $stmtDel->bindValue(':r', $oldRow['room']);
            $stmtDel->bindValue(':ts', $oldRow['time_start']);
            $stmtDel->bindValue(':t', $oldRow['teacher_id']);
            $stmtDel->execute();
        }
        $stmtInsert = $db->prepare("INSERT INTO schedules (day, subject_id, teacher_id, room, time_start, time_end, course_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($selectedCourses as $courseId) {
            $stmtInsert->reset();
            $stmtInsert->bindValue(1, $_POST["day"]);
            $stmtInsert->bindValue(2, $_POST["subject_id"]);
            $stmtInsert->bindValue(3, $_POST["teacher_id"]);
            $stmtInsert->bindValue(4, $_POST["room"]);
            $stmtInsert->bindValue(5, $_POST["time_start"]);
            $stmtInsert->bindValue(6, $_POST["time_end"]);
            $stmtInsert->bindValue(7, (int)$courseId);
            $stmtInsert->execute();
        }
        
        header("Location: admin_schedule.php?msg=Schedule+updated");
        exit;
    }
}

// --- HANDLE DELETE ---
if ($action === "delete") {
    $id = (int)$_GET["id"];
    $row = $db->querySingle("SELECT * FROM schedules WHERE id=$id", true);
    
    if($row) {
        $stmt = $db->prepare("DELETE FROM schedules WHERE day=:d AND room=:r AND time_start=:ts AND teacher_id=:t");
        $stmt->bindValue(':d', $row['day']);
        $stmt->bindValue(':r', $row['room']);
        $stmt->bindValue(':ts', $row['time_start']);
        $stmt->bindValue(':t', $row['teacher_id']);
        $stmt->execute();
    }
    
    header("Location: admin_schedule.php?msg=Deleted"); exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Schedule</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_schedule.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="admin_dashboard.php">
                <img src="../img/logo.jpg" width="50" height="50" class="me-2">
                <span class="fw-bold text-primary">Class</span><span class="text-primary">Sched</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link active" href="admin_schedule.php">Schedule</a></li>
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

    <div class="container my-4">
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php 
            $id = (int)($_GET["id"] ?? 0);
            $row = ($action === 'edit') ? $db->querySingle("SELECT * FROM schedules WHERE id=$id", true) : [];
            if ($action === 'edit' && !$row) {
                echo "<div class='alert alert-warning'>Schedule not found.</div><a href='admin_schedule.php' class='btn btn-secondary'>Back</a>";
                exit;
            }

            // Get related courses for edit
            $currentCourseIds = [];
            if ($action === 'edit') {
                $sqlSiblings = "SELECT course_id FROM schedules 
                                WHERE day = :day 
                                AND room = :room 
                                AND time_start = :ts 
                                AND teacher_id = :tid";
                
                $stmtSib = $db->prepare($sqlSiblings);
                $stmtSib->bindValue(':day', $row['day']);
                $stmtSib->bindValue(':room', $row['room']);
                $stmtSib->bindValue(':ts', $row['time_start']);
                $stmtSib->bindValue(':tid', $row['teacher_id']);
                
                $resSib = $stmtSib->execute();
                while($sib = $resSib->fetchArray(SQLITE3_ASSOC)){
                    $currentCourseIds[] = $sib['course_id'];
                }
            }
            ?>

            <h3><?php echo ucfirst($action); ?> Schedule</h3>
            <form id="scheduleForm" method="post" action="?action=<?php echo ($action==='edit') ? 'update' : 'store'; ?>" class="mt-3" onsubmit="return validateCourseSelection()">
                <?php if ($action==='edit') echo '<input type="hidden" name="id" value="'.$row['id'].'">'; ?>

                <div class="mb-3">
                    <label class="form-label">Day</label>
                    <select class="form-select" name="day" required>
                        <?php $val = $row['day'] ?? ''; ?>
                        <option value="" disabled <?php if($val == "") echo "selected"; ?>>-- Select Day --</option>
                        <?php foreach(["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"] as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if($val==$d) echo "selected"; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Subject</label>
                    <select class="form-select" name="subject_id" required>
                        <option value="" disabled <?php if(!isset($row['subject_id'])) echo "selected"; ?>>-- Select Subject --</option>
                        <?php foreach($subjectOptions as $o): ?>
                            <option value="<?php echo $o['id']; ?>" <?php echo ($o['id']==($row['subject_id']??0)?'selected':''); ?>>
                                <?php echo htmlspecialchars($o['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Teacher</label>
                    <select class="form-select" name="teacher_id" required>
                        <option value="" disabled <?php if(!isset($row['teacher_id'])) echo "selected"; ?>>-- Select Teacher --</option>
                        <?php foreach($teacherOptions as $o): ?>
                            <option value="<?php echo $o['id']; ?>" <?php echo ($o['id']==($row['teacher_id']??0)?'selected':''); ?>>
                                <?php echo htmlspecialchars($o['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Course(s)</label>
                    <div id="course_checkbox_group" class="d-flex flex-column">
                        <?php foreach($courseOptions as $o): 
                            $isChecked = in_array($o['id'], $currentCourseIds) ? 'checked' : '';
                        ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="course_ids[]" value="<?php echo $o['id']; ?>" <?php echo $isChecked; ?>>
                                <label class="form-check-label"><?php echo htmlspecialchars($o['course_name']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="course_error" class="text-danger mt-1" style="display:none;">Please select at least one course.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Room</label>
                    <input type="text" class="form-control" name="room" value="<?php echo $row['room']??''; ?>" required>
                </div>

                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="time_start" value="<?php echo $row['time_start']??''; ?>" required>
                    </div>
                    <div class="col">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="time_end" value="<?php echo $row['time_end']??''; ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save</button>
                <a href="admin_schedule.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>

        <?php else: ?>  
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Manage Schedule</h2>
            </div>

            <div class="row mb-3 g-2">
                <div class="col-md-3">
                    <select id="filter_course" class="form-select" onchange="loadTable()">
                        <option value="">All Courses</option>
                        <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                        <option value="<?php echo COURSE_ACT; ?>">ACT</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" id="search" class="form-control" placeholder="Search subject, room..." onkeyup="loadTable()">
                </div>
                <div class="col-md-3">
                    <select id="sort_by" class="form-select" onchange="loadTable()">
                        <option value="time_start">Start Time</option>
                        <option value="time_end">End Time</option>
                        <option value="day">By Day</option>
                    </select>
                </div>
            </div>

            <?php 
            $count = $db->querySingle("SELECT COUNT(*) FROM schedules"); 
            
            if ($count == 0): ?>
                <div class="alert alert-info">No schedule record found. Click "Add Schedule" to get started.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Room</th>
                                <th>Time Start</th>
                                <th>Time End</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="table_data"></tbody>
                    </table>
                    <a href="?action=create" class="btn btn-primary btn-sched">+ Add Schedule</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/load.js"></script>
    <script src="../js/selected.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>