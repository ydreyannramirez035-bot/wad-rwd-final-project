<?php
session_start();

// 1. CONFIGURATION & DB
require_once __DIR__ . "/db.php";
$db = get_db();

// Define constants
define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

// 2. SECURITY CHECK
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];

$subjectOptions = [];
$subRes = $db->query("SELECT id, subject_name FROM subjects ORDER BY subject_name ASC");
while ($row = $subRes->fetchArray(SQLITE3_ASSOC)) {
    $subjectOptions[] = $row;
}

$teacherOptions = [];
$teachRes = $db->query("SELECT id, name FROM teachers ORDER BY name ASC");
while ($row = $teachRes->fetchArray(SQLITE3_ASSOC)) {
    $teacherOptions[] = $row;
}

$courseOptions = [];
$courseRes = $db->query("SELECT id, course_name FROM courses ORDER BY course_name ASC");
while ($row = $courseRes->fetchArray(SQLITE3_ASSOC)) {
    $courseOptions[] = $row;
}

$action = $_GET["action"] ?? "list";
$msg    = $_GET["msg"] ?? "";
$error  = "";

// AJAX / SEARCH HANDLER
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search        = trim($_GET['q'] ?? '');
    $filterCourse  = (int)($_GET['filter_course'] ?? 0);
    $filterSubject = (int)($_GET['filter_subject'] ?? 0);
    $filterTeacher = (int)($_GET['filter_teacher'] ?? 0);
    $sortBy        = $_GET['sort_by'] ?? 'time_start';

    $sortMap = [
        'time_start' => 's.time_start',
        'time_end'   => 's.time_end',
        'subject'    => 'sub.subject_name',
        'day'        => 's.day'
    ];
    $orderBy = $sortMap[$sortBy] ?? 's.time_start';

    // Build Query
    $sql = "SELECT s.*, 
                   sub.subject_name, 
                   t.name as teacher_name, 
                   c.course_name 
            FROM schedules s
            LEFT JOIN subjects sub ON s.subject_id = sub.id
            LEFT JOIN teachers t ON s.teacher_id = t.id
            LEFT JOIN courses c ON s.course_id = c.id
            WHERE 1=1";

    // Add Filters
    if ($search) {
        $sql .= " AND (sub.subject_name LIKE :search OR t.name LIKE :search OR s.room LIKE :search)";
    }
    if ($filterCourse > 0) {
        $sql .= " AND s.course_id = :course";
    }
    if ($filterSubject > 0) {
        $sql .= " AND s.subject_id = :subject";
    }
    if ($filterTeacher > 0) {
        $sql .= " AND s.teacher_id = :teacher";
    }

    $sql .= " ORDER BY $orderBy ASC";

    $stmt = $db->prepare($sql);
    if ($search) $stmt->bindValue(':search', "%$search%", SQLITE3_TEXT);
    if ($filterCourse > 0) $stmt->bindValue(':course', $filterCourse, SQLITE3_INTEGER);
    if ($filterSubject > 0) $stmt->bindValue(':subject', $filterSubject, SQLITE3_INTEGER);
    if ($filterTeacher > 0) $stmt->bindValue(':teacher', $filterTeacher, SQLITE3_INTEGER);
    
    $result = $stmt->execute();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['day']); ?></td>
            <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
            <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
            <td><?php echo htmlspecialchars($row['room']); ?></td>
            <td>
                <?php 
                echo $row['time_start'] ? date("h:i A", strtotime($row['time_start'])) : ''; 
                ?>
            </td>
            <td>
                <?php 
                echo $row['time_end'] ? date("h:i A", strtotime($row['time_end'])) : ''; 
                ?>
            </td>
            <td>
                <a href="?action=edit&id=<?php echo $row['id']; ?>">Edit</a> <br>
                <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this schedule?');">Delete</a>
            </td>
        </tr>
        <?php
    }
    exit;
}

// STORE (Create)
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "store") {
    $day = trim($_POST["day"]); $subjectId = (int)$_POST["subject_id"]; $teacherId = (int)$_POST["teacher_id"];
    $courseId = (int)$_POST["course_id"]; $room = trim($_POST["room"]); $time_start = trim($_POST["time_start"]);
    $time_end = trim($_POST["time_end"]);

    // Check for duplicates
    $checkSql = "SELECT COUNT(*) as count FROM schedules WHERE subject_id = :sub AND teacher_id = :teach AND day = :day AND time_start = :ts";
    $stmt = $db->prepare($checkSql);
    $stmt->bindValue(':sub', $subjectId); $stmt->bindValue(':teach', $teacherId); $stmt->bindValue(':day', $day); $stmt->bindValue(':ts', $time_start);
    
    if ($stmt->execute()->fetchArray()['count'] > 0) {
        $error = "Error: Schedule conflict!"; $action = "create"; 
    } else {
        $sql = "INSERT INTO schedules (day, subject_id, teacher_id, room, time_start, time_end, course_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $day); $stmt->bindValue(2, $subjectId); $stmt->bindValue(3, $teacherId);
        $stmt->bindValue(4, $room); $stmt->bindValue(5, $time_start); $stmt->bindValue(6, $time_end); $stmt->bindValue(7, $courseId);
        $stmt->execute();
        header("Location: admin_schedule.php?msg=Saved"); exit;
    }
}
// UPDATE
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "update") {
    $stmt = $db->prepare("UPDATE schedules SET day=?, subject_id=?, teacher_id=?, room=?, time_start=?, time_end=?, course_id=? WHERE id=?");
    $stmt->bindValue(1, $_POST["day"]); $stmt->bindValue(2, $_POST["subject_id"]); $stmt->bindValue(3, $_POST["teacher_id"]);
    $stmt->bindValue(4, $_POST["room"]); $stmt->bindValue(5, $_POST["time_start"]); $stmt->bindValue(6, $_POST["time_end"]);
    $stmt->bindValue(7, $_POST["course_id"]); $stmt->bindValue(8, $_POST["id"]);
    $stmt->execute();
    header("Location: admin_schedule.php?msg=Updated"); exit;
}
// DELETE
if ($action === "delete") {
    $db->exec("DELETE FROM schedules WHERE id = " . (int)$_GET["id"]);
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

    <!-- <?php if ($msg) echo "<p style='color:green'><b>$msg</b></p>"; ?>
    <?php if ($error) echo "<p style='color:red'><b>$error</b></p>"; ?> -->

    <?php if ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Forms -->
        <?php 
        $id = (int)($_GET["id"] ?? 0);
        $row = ($action === 'edit') ? $db->querySingle("SELECT * FROM schedules WHERE id=$id", true) : [];
        ?>
        <div class="container">
            <h3><?php echo ucfirst($action); ?> Schedule</h3>
            <form method="post" action="?action=<?php echo ($action==='edit') ? 'update' : 'store'; ?>">
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
                    <option value="" disabled <?php if($val == "") echo "selected"; ?>>-- Select Subject --</option>
                    <?php foreach($subjectOptions as $o) echo "<option value='{$o['id']}' ".($o['id']==($row['subject_id']??0)?'selected':'').">{$o['subject_name']}</option>"; ?>
                </select></p>
                <p>Teacher: <select name="teacher_id" required>
                    <option value="" disabled <?php if($val == "") echo "selected"; ?>>-- Select Teacher --</option>
                    <?php foreach($teacherOptions as $o) echo "<option value='{$o['id']}' ".($o['id']==($row['teacher_id']??0)?'selected':'').">{$o['name']}</option>"; ?>
                </select></p>
                
                <p>Course: <select name=~"course_id" required>
                    <option value="" disabled <?php if($val == "") echo "selected"; ?>>-- Select Course --</option>
                    <?php foreach($courseOptions as $o) echo "<option value='{$o['id']}' ".($o['id']==($row['course_id']??0)?'selected':'').">{$o['course_name']}</option>"; ?>
                </select></p>
                
                <p>Room: <input type="text" name="room" value="<?php echo $row['room']??''; ?>" required></p>
                <p>Start: <input type="time" name="time_start" value="<?php echo $row['time_start']??''; ?>" required></p>
                <p>End: <input type="time" name="time_end" value="<?php echo $row['time_end']??''; ?>" required></p>
                <button type="submit">Save</button>
                <a href="admin_schedule.php">Cancel</a>
            </form>
        </div>

    <?php else: ?>
        <h2>Manage Schedule</h2>
        <div class="controls">
            <select id="filter_course" onchange="loadTable()">
                <option value="">All Courses</option>
                <option value="<?php echo COURSE_BSIS; ?>" <?php if($student['course_id'] == COURSE_BSIS) echo 'selected'; ?>>BSIS</option>
                <option value="<?php echo COURSE_ACT; ?>" <?php if($student['course_id'] == COURSE_ACT) echo 'selected'; ?>>ACT</option>
            </select>

            <input type="text" id="search" placeholder="Search..." onkeyup="loadTable()">

            <select id="sort_by" onchange="loadTable()">
                <option value="time_start">Start Time</option>
                <option value="time_end">End Time</option>
                <option value="day">By Time</option>
            </select>
        </div>
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
                <!-- Data loads here via JS -->
            </tbody>
        </table>
        <br>
        <div style="text-align: right;">
            <a href="?action=create"><button>+ Add Schedule</button></a>
        </div>

        <script src="../js/load.js"></script>
    <?php endif; ?>

</body>
</html>