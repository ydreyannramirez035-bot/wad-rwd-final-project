<?php
session_start();
require_once __DIR__ . "/db.php";
$db = get_db();

// Constants
define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

// Security Check
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];

// --- FETCH DATA ---
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
    <link rel="stylesheet" href="../try.css">
</head>
<body>

    <nav>
        <a href="admin_dashboard.php">Dashboard</a> | 
        <a href="admin_schedule.php"><strong>Schedule</strong></a> | 
        <a href="admin_student_manage.php">Manage Students</a>
    </nav>
    <hr>

    <?php if ($action === 'create' || $action === 'edit'): ?>
        <?php 
        $id = (int)($_GET["id"] ?? 0);
        $row = ($action === 'edit') ? $db->querySingle("SELECT * FROM schedules WHERE id=$id", true) : [];
        if ($action === 'edit' && !$row) {
            echo "<p>Schedule not found.</p> <a href='admin_schedule.php'>Back</a>";
            exit;
        }

        // --- NEW LOGIC: FIND RELATED COURSES ---
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
        
        <form id="scheduleForm" method="post" action="?action=<?php echo ($action==='edit') ? 'update' : 'store'; ?>" onsubmit="return validateCourseSelection()">
            <?php if ($action==='edit') echo '<input type="hidden" name="id" value="'.$row['id'].'">'; ?>
            
            <p>Day: 
                <select name="day" required>
                    <?php $val = $row['day'] ?? ''; ?>
                    <option value="" disabled <?php if($val == "") echo "selected"; ?>>-- Select Day --</option>
                    <option value="Monday"    <?php if($val == "Monday") echo "selected"; ?>>Monday</option>
                    <option value="Tuesday"   <?php if($val == "Tuesday") echo "selected"; ?>>Tuesday</option>
                    <option value="Wednesday" <?php if($val == "Wednesday") echo "selected"; ?>>Wednesday</option>
                    <option value="Thursday"  <?php if($val == "Thursday") echo "selected"; ?>>Thursday</option>
                    <option value="Friday"    <?php if($val == "Friday") echo "selected"; ?>>Friday</option>
                    <option value="Saturday"  <?php if($val == "Saturday") echo "selected"; ?>>Saturday</option>
                </select>
            </p>

            <p>Subject: <select name="subject_id" required>
                <option value="" disabled <?php if(!isset($row['subject_id'])) echo "selected"; ?>>-- Select Subject --</option>
                <?php foreach($subjectOptions as $o) echo "<option value='{$o['id']}' ".($o['id']==($row['subject_id']??0)?'selected':'').">{$o['subject_name']}</option>"; ?>
            </select></p>

            <p>Teacher: <select name="teacher_id" required>
                <option value="" disabled <?php if(!isset($row['teacher_id'])) echo "selected"; ?>>-- Select Teacher --</option>
                <?php foreach($teacherOptions as $o) echo "<option value='{$o['id']}' ".($o['id']==($row['teacher_id']??0)?'selected':'').">{$o['name']}</option>"; ?>
            </select></p>
            
            <p>Course:</p>
            <div id="course_checkbox_group">
                <?php foreach($courseOptions as $o): 
                    $isChecked = in_array($o['id'], $currentCourseIds) ? 'checked' : '';
                ?>
                    <label style="display:block; margin-bottom:3px;">
                        <input type="checkbox" name="course_ids[]" value="<?php echo $o['id']; ?>" <?php echo $isChecked; ?>> 
                        <?php echo htmlspecialchars($o['course_name']); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p id="course_error" style="color:red; display:none;">Please select at least one course.</p>

            <p>Room: <input type="text" name="room" value="<?php echo $row['room']??''; ?>" required></p>
            <p>Start: <input type="time" name="time_start" value="<?php echo $row['time_start']??''; ?>" required></p>
            <p>End: <input type="time" name="time_end" value="<?php echo $row['time_end']??''; ?>" required></p>
            
            <button type="submit">Save</button>
            <a href="admin_schedule.php">Cancel</a>
        </form>
    
    <?php else: ?>  
        <h2>Manage Schedule</h2>
        <div class="controls">
            <select id="filter_course" onchange="loadTable()">
                <option value="">All Courses</option>
                <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                <option value="<?php echo COURSE_ACT; ?>">ACT</option>
            </select>

            <input type="text" id="search" placeholder="Search subject, room..." onkeyup="loadTable()">

            <select id="sort_by" onchange="loadTable()">
                <option value="time_start">Start Time</option>
                <option value="time_end">End Time</option>
                <option value="day">By Day</option>
            </select>
        </div>

        <?php 
        $count = $db->querySingle("SELECT COUNT(*) FROM schedules"); 
        
        if ($count == 0): ?>
            <div class="empty-state">
                <h3>No schedule record found</h3>
                <p>Click the button below to get started.</p>
                <a href="?action=create"><button>+ Add Schedule</button></a>
            </div>
            
        <?php else: ?>
            <table border="1">
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
                    </tbody>
            </table>
            <br>
            <div style="text-align: right;">
                <a href="?action=create"><button>+ Add Schedule</button></a>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <script src="../js/load.js"></script>
    <script src="../js/selected.js"></script>

</body>
</html>