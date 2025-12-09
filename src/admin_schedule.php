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
$notif_data = notif('admin', true); ;
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];
$highlight_count = $notif_data['highlight_count'];

define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

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
            <td class="fw-medium text-secondary"><?php echo htmlspecialchars($row['day']); ?></td>
            <td class="fw-medium text-dark"><?php echo htmlspecialchars($row['subject_name']); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($row['teacher_name']); ?></td>
            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['room']); ?></span></td>
            <td class="text-secondary"><?php echo $row['time_start'] ? date("h:i A", strtotime($row['time_start'])) : ''; ?></td>
            <td class="text-secondary"><?php echo $row['time_end'] ? date("h:i A", strtotime($row['time_end'])) : ''; ?></td>
            <td>
                <div class="d-flex gap-2">
                    <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                    <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this schedule?');" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></a>
                </div>
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
    <title>ClassSched | Manage Schudule</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_schedule.css">
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
                        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="admin_student_manage.php">Students</a></li>
                        <li class="nav-item"><a class="nav-link active" href="admin_schedule.php">Schedule</a></li>
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
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <div class="bg-white rounded-4 shadow-sm border p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="fa-regular fa-calendar-plus text-brand-blue"></i>
                        <?php echo ucfirst($action); ?> Schedule
                    </h5>
                    <a href="admin_schedule.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back
                    </a>
                </div>

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

                <form id="scheduleForm" method="post" action="?action=<?php echo ($action==='edit') ? 'update' : 'store'; ?>" onsubmit="return validateCourseSelection()">
                    <?php if ($action==='edit') echo '<input type="hidden" name="id" value="'.$row['id'].'">'; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-secondary small">Day</label>
                            <select class="form-select" name="day" required>
                                <?php $val = $row['day'] ?? ''; ?>
                                <option value="" disabled <?php if($val == "") echo "selected"; ?>>-- Select Day --</option>
                                <?php foreach(["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"] as $d): ?>
                                    <option value="<?php echo $d; ?>" <?php if($val==$d) echo "selected"; ?>><?php echo $d; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-secondary small">Room</label>
                            <input type="text" class="form-control" name="room" value="<?php echo $row['room']??''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-secondary small">Subject</label>
                            <select class="form-select" name="subject_id" required>
                                <option value="" disabled <?php if(!isset($row['subject_id'])) echo "selected"; ?>>-- Select Subject --</option>
                                <?php foreach($subjectOptions as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" <?php echo ($o['id']==($row['subject_id']??0)?'selected':''); ?>>
                                        <?php echo htmlspecialchars($o['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-secondary small">Teacher</label>
                            <select class="form-select" name="teacher_id" required>
                                <option value="" disabled <?php if(!isset($row['teacher_id'])) echo "selected"; ?>>-- Select Teacher --</option>
                                <?php foreach($teacherOptions as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" <?php echo ($o['id']==($row['teacher_id']??0)?'selected':''); ?>>
                                        <?php echo htmlspecialchars($o['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-secondary small">Start Time</label>
                            <input type="time" class="form-control" name="time_start" value="<?php echo $row['time_start']??''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-secondary small">End Time</label>
                            <input type="time" class="form-control" name="time_end" value="<?php echo $row['time_end']??''; ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-secondary small">Applicable Course(s)</label>
                        <div class="card p-3 bg-light border-0">
                            <div id="course_checkbox_group" class="d-flex flex-wrap gap-3">
                                <?php foreach($courseOptions as $o): 
                                    $isChecked = in_array($o['id'], $currentCourseIds) ? 'checked' : '';
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="course_ids[]" value="<?php echo $o['id']; ?>" <?php echo $isChecked; ?>>
                                        <label class="form-check-label"><?php echo htmlspecialchars($o['course_name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="course_error" class="text-danger mt-2 small" style="display:none;">
                                <i class="fa-solid fa-circle-exclamation me-1"></i> Please select at least one course.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">Save</button>
                        <a href="admin_schedule.php" class="btn btn-light px-4">Cancel</a>
                    </div>
                </form>
            </div>

        <?php else: ?> 
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h1 class="fw-bold text-dark mb-2">Schedule</h1>
                    <p class="text-secondary mb-0 small d-none d-sm-block">Manage class schedules and assignments.</p>
                </div>
                <div>
                    <a href="?action=create" class="btn btn-primary btn-sched rounded-pill px-3 px-md-4">
                        <i class="fa-solid fa-plus me-1"></i> <span>Add Schedule</span>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-4 shadow-sm border p-4">
                
                <div class="row mb-4 g-2">
                    <div class="col-5 col-md-3">
                        <select id="filter_course" class="form-select bg-light border-0 text-truncate" onchange="loadTable()" style="cursor: pointer;">
                            <option value="">All</option>
                            <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                            <option value="<?php echo COURSE_ACT; ?>">ACT</option>
                        </select>
                    </div>
                    
                    <div class="col-7 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 ps-2 pe-1"><i class="fa-solid fa-search text-secondary small"></i></span>
                            <input type="text" id="search" class="form-control bg-light border-0 ps-1" placeholder="Search..." onkeyup="loadTable()">
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-3">
                        <div class="input-group">
                            <select id="sort_by" class="form-select bg-light border-0 ps-1" onchange="loadTable()" style="cursor: pointer;">
                                <option value="time_start">Sort by time start</option>
                                <option value="time_end">Sort by time end</option>
                                <option value="day">Sort by day</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php 
                $count = $db->querySingle("SELECT COUNT(*) FROM schedules"); 
                
                if ($count == 0): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-regular fa-calendar-xmark fs-1 mb-3 text-secondary opacity-50"></i>
                        <p class="mb-0">No schedule records found.</p>
                        <small>Click "Add Schedule" to get started.</small>
                    </div>
                <?php else: ?>
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="table_data"></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-white border-top py-4 text-center mt-auto">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/load.js"></script>
    <script src="../js/selected.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>