<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("Location: ../index.php");
    exit;
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Mocking required files for this standalone generation if they don't exist in your environment
// In your actual project, keep your original require_once lines.
if (file_exists(__DIR__ ."/notification.php")) require_once __DIR__ ."/notification.php";
if (file_exists(__DIR__ ."/db.php")) require_once __DIR__ ."/db.php";

// Basic DB connection if not provided by require
if (!function_exists('get_db')) {
    function get_db() {
        $db = new SQLite3('database.db'); // Adjust path as needed
        return $db;
    }
}
$db = get_db();

// Mock notification data if function missing
if (!function_exists('notif')) {
    $notif_data = ['unread_count' => 0, 'notifications' => [], 'highlight_count' => 0];
} else {
    $notif_data = notif('admin', true);
}

$user = $_SESSION["user"];
$user_id = $user['id'];
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];
$highlight_count = $notif_data['highlight_count'];

define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

// FETCH DATA FOR DROPDOWNS
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

// ==========================================
// --- HANDLE AJAX LIST WITH PAGINATION ---
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search        = trim($_GET['q'] ?? '');
    $filterCourse  = (int)($_GET['filter_course'] ?? 0);
    $sortBy        = $_GET['sort_by'] ?? 'time_start';
    
    // Pagination Variables
    $page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 5; // Number of records per page
    $offset = ($page - 1) * $limit;

    $sortMap = [
        'time_start' => 's.time_start',
        'time_end'   => 's.time_end',
        'day'        => 's.day'
    ];
    $orderBy = $sortMap[$sortBy] ?? 's.time_start';

    // Base WHERE clause building
    $whereSQL = " WHERE 1=1";
    $params = [];

    if ($search) {
        $whereSQL .= " AND (sub.subject_name LIKE :search OR t.name LIKE :search OR s.room LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($filterCourse > 0) {
        $whereSQL .= " AND s.course_id = :course";
        $params[':course'] = $filterCourse;
    }

    // 1. COUNT QUERY (To get total pages)
    // We must wrap the Group By in a subquery to count correctly in SQLite
    $countSql = "SELECT COUNT(*) as total FROM (
                    SELECT 1 
                    FROM schedules s
                    LEFT JOIN subjects sub ON s.subject_id = sub.id
                    LEFT JOIN teachers t ON s.teacher_id = t.id
                    LEFT JOIN courses c ON s.course_id = c.id
                    $whereSQL
                    GROUP BY s.day, s.time_start, s.room, s.teacher_id
                )";
    
    $stmtCount = $db->prepare($countSql);
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val, is_int($val) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    $totalRows = $stmtCount->execute()->fetchArray(SQLITE3_ASSOC)['total'] ?? 0;
    $totalPages = ceil($totalRows / $limit);

    // 2. DATA QUERY
    $sql = "SELECT s.*, 
                   sub.subject_name, 
                   t.name as teacher_name, 
                   c.course_name 
            FROM schedules s
            LEFT JOIN subjects sub ON s.subject_id = sub.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            LEFT JOIN courses c ON s.course_id = c.id
            $whereSQL
            GROUP BY s.day, s.time_start, s.room, s.teacher_id 
            ORDER BY $orderBy ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, is_int($val) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    
    $result = $stmt->execute();

    // Start Buffering HTML for Table Rows
    ob_start();
    $hasData = false;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $hasData = true;
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
    
    if (!$hasData) {
        echo '<tr><td colspan="7" class="text-center py-4 text-muted">No results found</td></tr>';
    }
    $tableHtml = ob_get_clean();

    // Start Buffering HTML for Pagination
    ob_start();
    if ($totalPages > 1) {
        ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-end mb-0">
                <!-- Previous -->
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="#" onclick="loadTable(<?php echo $page - 1; ?>); return false;">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                </li>

                <!-- Page Numbers -->
                <?php 
                // Show a limited window of pages to prevent overflow
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);

                if($startPage > 1) { 
                    echo '<li class="page-item"><a class="page-link" href="#" onclick="loadTable(1); return false;">1</a></li>';
                    if($startPage > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }

                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="#" onclick="loadTable(<?php echo $i; ?>); return false;">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; 

                if($endPage < $totalPages) {
                    if($endPage < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    echo '<li class="page-item"><a class="page-link" href="#" onclick="loadTable('.$totalPages.'); return false;">'.$totalPages.'</a></li>';
                }
                ?>

                <!-- Next -->
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="#" onclick="loadTable(<?php echo $page + 1; ?>); return false;">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
        <span class="mobile-hidden">
            Showing Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </span>
        <?php
    }
    $paginationHtml = ob_get_clean();

    // Return JSON Response
    header('Content-Type: application/json');
    echo json_encode([
        'table_html' => $tableHtml,
        'pagination_html' => $paginationHtml
    ]);
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
    $oldRow = $db->querySingle("SELECT * FROM schedules WHERE id=$id", true);

    if ($oldRow) {
        $sqlSiblings = "SELECT id, course_id FROM schedules 
                        WHERE day = :day 
                        AND room = :room 
                        AND time_start = :ts 
                        AND teacher_id = :tid";
        
        $stmtSib = $db->prepare($sqlSiblings);
        $stmtSib->bindValue(':day', $oldRow['day']);
        $stmtSib->bindValue(':room', $oldRow['room']);
        $stmtSib->bindValue(':ts', $oldRow['time_start']);
        $stmtSib->bindValue(':tid', $oldRow['teacher_id']);
        $resSib = $stmtSib->execute();

        $existingMap = []; 
        while($sib = $resSib->fetchArray(SQLITE3_ASSOC)){
            $existingMap[$sib['course_id']] = $sib['id'];
        }

        $stmtUpdate = $db->prepare("UPDATE schedules SET day=?, subject_id=?, teacher_id=?, room=?, time_start=?, time_end=? WHERE id=?");
        $stmtInsert = $db->prepare("INSERT INTO schedules (day, subject_id, teacher_id, room, time_start, time_end, course_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtDelete = $db->prepare("DELETE FROM schedules WHERE id=?");

        foreach ($selectedCourses as $courseId) {
            $courseId = (int)$courseId;

            if (isset($existingMap[$courseId])) {
                $schedIdToUpdate = $existingMap[$courseId];
                
                $stmtUpdate->reset();
                $stmtUpdate->bindValue(1, $_POST["day"]);
                $stmtUpdate->bindValue(2, $_POST["subject_id"]);
                $stmtUpdate->bindValue(3, $_POST["teacher_id"]);
                $stmtUpdate->bindValue(4, $_POST["room"]);
                $stmtUpdate->bindValue(5, $_POST["time_start"]);
                $stmtUpdate->bindValue(6, $_POST["time_end"]);
                $stmtUpdate->bindValue(7, $schedIdToUpdate);
                $stmtUpdate->execute();
                unset($existingMap[$courseId]);

            } else {
                $stmtInsert->reset();
                $stmtInsert->bindValue(1, $_POST["day"]);
                $stmtInsert->bindValue(2, $_POST["subject_id"]);
                $stmtInsert->bindValue(3, $_POST["teacher_id"]);
                $stmtInsert->bindValue(4, $_POST["room"]);
                $stmtInsert->bindValue(5, $_POST["time_start"]);
                $stmtInsert->bindValue(6, $_POST["time_end"]);
                $stmtInsert->bindValue(7, $courseId);
                $stmtInsert->execute();
            }
        }
        foreach ($existingMap as $cId => $schedIdToDelete) {
            $stmtDelete->reset();
            $stmtDelete->bindValue(1, $schedIdToDelete);
            $stmtDelete->execute();
        }
    }

    header("Location: admin_schedule.php?msg=Schedule+updated");
    exit;
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
    <title>ClassSched | Manage Schedule</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_schedule.css">
    <link rel="stylesheet" href="../styles/admin.css">
    <link rel="stylesheet" href="../styles/notification.css">
    <link rel="stylesheet" href="../styles/pagination.css">
</head>
<body class="d-flex flex-column min-vh-100 position-relative">
    <?php if(file_exists(__DIR__ . "/student_nav.php")) require_once __DIR__ . "/student_nav.php"; ?>
    
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
                        <!-- Reset to page 1 on filter change -->
                        <select id="filter_course" class="form-select bg-light border-0 text-truncate" onchange="loadTable(1)" style="cursor: pointer;">
                            <option value="">All</option>
                            <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                            <option value="<?php echo COURSE_ACT; ?>">ACT</option>
                        </select>
                    </div>
                    
                    <div class="col-7 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 ps-2 pe-1"><i class="fa-solid fa-search text-secondary small"></i></span>
                            <!-- Reset to page 1 on search -->
                            <input type="text" id="search" class="form-control bg-light border-0 ps-1" placeholder="Search..." onkeyup="loadTable(1)">
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-3">
                        <div class="input-group">
                            <!-- Reset to page 1 on sort -->
                            <select id="sort_by" class="form-select bg-light border-0 ps-1" onchange="loadTable(1)" style="cursor: pointer;">
                                <option value="time_start">Sort by time start</option>
                                <option value="time_end">Sort by time end</option>
                                <option value="day">Sort by day</option>
                            </select>
                        </div>
                    </div>
                </div>

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
                        <tbody id="table_data">
                            <!-- Data injected here via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Container -->
                <div id="pagination_container" class="mt-4 mb-1 pagination">
                    <!-- Pagination injected here via JS -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-white border-top py-4 text-center mt-auto">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/selected.js"></script>
    <script src="../js/pagination.js"></script>
</body>
</html>
