<?php
session_start();
require_once __DIR__ . "/db.php";
$db = get_db();

define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];

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

$unread_count = $db->querySingle("
    SELECT COUNT(*) FROM notifications 
    WHERE is_read = 0 
    AND (message LIKE '%bio%' OR message LIKE '%phone%')
");

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

// Initialize Variables
$action = $_GET["action"] ?? "list";
$msg    = $_GET["msg"] ?? "";
$error  = "";

function getCourseName($id) {
    if ($id == COURSE_BSIS) return "BSIS";
    if ($id == COURSE_ACT) return "ACT";
    return "Unknown";
}

function getYearLevelStr($level) {
    $level = (int)$level;
    $suffixes = [1 => "1st Year", 2 => "2nd Year", 3 => "3rd Year", 4 => "4th Year"];
    return $suffixes[$level] ?? "Unknown";
}

// --- HANDLE AJAX SEARCH/SORT ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search       = trim($_GET['q'] ?? '');
    $filterCourse = (int)($_GET['filter_course'] ?? 0);
    $sortBy       = $_GET['sort_by'] ?? 'last_name';

    $validSorts = ['last_name', 'first_name', 'student_number'];
    if (!in_array($sortBy, $validSorts)) $sortBy = 'last_name';

    // Build Query
    $sql = "SELECT * FROM students WHERE 1=1";
    
    if ($search) {
        $sql .= " AND (student_number LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
    }
    
    // Add Filter Conditions
    if ($filterCourse > 0) {
        $sql .= " AND course_id = :course";
    }

    $sql .= " ORDER BY $sortBy ASC";

    // Prepare and Execute
    $stmt = $db->prepare($sql);
    if ($search) {
        $stmt->bindValue(':search', "%$search%", SQLITE3_TEXT);
    }
    if ($filterCourse > 0) {
        $stmt->bindValue(':course', $filterCourse, SQLITE3_INTEGER);
    }
    
    $result = $stmt->execute();

    // Render Rows
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['student_number']); ?></td>
            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
            <td><?php echo htmlspecialchars($row['middle_name']); ?></td>
            <td><?php echo (int)$row['age']; ?></td>
            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
            <td><?php echo getYearLevelStr($row['year_level']); ?></td>
            <td>
                <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn-link">Edit</a> | 
                <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this student?');" class="text-danger">Delete</a>
            </td>
        </tr>
        <?php
    }
    exit; 
}

// --- HANDLE FORM SUBMISSION (ADD) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "store") {
    $student_number = trim($_POST["student_number"]);
    
    // Check for duplicates
    $checkSql = "SELECT COUNT(*) as count FROM students WHERE student_number = :sn";
    $stmt = $db->prepare($checkSql);
    $stmt->bindValue(':sn', $student_number, SQLITE3_TEXT);
    $exists = $stmt->execute()->fetchArray()['count'];

    if ($exists > 0) {
        $error = "Error: Student Number '$student_number' already exists!";
        $action = "create"; 
    } else {
        $sql = "INSERT INTO students (student_number, first_name, middle_name, last_name, age, phone_number, email, course_id, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $student_number);
        $stmt->bindValue(2, trim($_POST["first_name"]));
        $stmt->bindValue(3, trim($_POST["middle_name"]));
        $stmt->bindValue(4, trim($_POST["last_name"]));
        $stmt->bindValue(5, (int)$_POST["age"]);
        $stmt->bindValue(6, trim($_POST["phone_number"]));
        $stmt->bindValue(7, trim($_POST["email"]));
        $stmt->bindValue(8, (int)$_POST["course_id"]);
        $stmt->bindValue(9, (int)$_POST["year_level"]);
        $stmt->execute();

        header("Location: admin_student_manage.php?msg=Student+added+successfully");
        exit;
    }
}

// --- HANDLE FORM SUBMISSION (UPDATE) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "update") {
    $sql = "UPDATE students SET student_number=?, first_name=?, middle_name=?, last_name=?, age=?, phone_number=?, email=?, course_id=?, year_level=? WHERE id=?";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, trim($_POST["student_number"])); 
    $stmt->bindValue(2, trim($_POST["first_name"])); 
    $stmt->bindValue(3, trim($_POST["middle_name"]));
    $stmt->bindValue(4, trim($_POST["last_name"])); 
    $stmt->bindValue(5, (int)$_POST["age"]); 
    $stmt->bindValue(6, trim($_POST["phone_number"]));
    $stmt->bindValue(7, trim($_POST["email"]));
    $stmt->bindValue(8, (int)$_POST["course_id"]); 
    $stmt->bindValue(9, (int)$_POST["year_level"]);
    $stmt->bindValue(10, (int)$_POST["id"]);
    $stmt->execute();
    
    header("Location: admin_student_manage.php?msg=Student+updated");
    exit;
}

// --- HANDLE DELETE ---
if ($action === "delete") {
    $id = (int)($_GET["id"] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();
        header("Location: admin_student_manage.php?msg=Student+deleted");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_student_manage.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="../img/logo.jpg" width="50" height="50" class="me-2">
                <span class="fw-bold text-primary">Class</span><span class="text-primary">Sched</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="admin_student_manage.php">Students</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
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
                                <li>
                                    <a class="dropdown-item notification-item" href="?action=read_notif&id=<?php echo $notif['id']; ?>">
                                        
                                        <div class="notif-content">
                                            <div>
                                                <strong><?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?></strong>
                                            </div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($notif['message']); ?></div>
                                            <div class="notif-time"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></div>
                                        </div>

                                        <?php if ($notif['is_read'] == 0): ?>
                                            <div class="unread-dot"></div>
                                        <?php endif; ?>

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
            <?php if ($msg): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php 
            $id = (int)($_GET["id"] ?? 0);
            $student = ($action === 'edit') ? $db->querySingle("SELECT * FROM students WHERE id=$id", true) : [];
            if ($action === 'edit' && !$student) {
                echo "<div class='alert alert-warning'>Student not found.</div><a href='admin_student_manage.php' class='btn btn-secondary'>Back</a>";
                exit;
            }
            ?>

            <h3><?php echo ucfirst($action); ?> Student</h3>
            <form method="post" action="?action=<?php echo ($action==='edit') ? 'update' : 'store'; ?>" class="mt-3">
                <?php if ($action==='edit') echo '<input type="hidden" name="id" value="'.$student['id'].'">'; ?>

                <div class="mb-3">
                    <label class="form-label">Student Number</label>
                    <input type="text" class="form-control" name="student_number" value="<?php echo $student['student_number']??''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?php echo $student['first_name']??''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Middle Name</label>
                    <input type="text" class="form-control" name="middle_name" value="<?php echo $student['middle_name']??''; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?php echo $student['last_name']??''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Age</label>
                    <input type="number" class="form-control" name="age" value="<?php echo $student['age']??''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control" name="phone_number" value="<?php echo $student['phone_number']??''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?php echo $student['email']??''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Year Level</label><br>
                    <?php for($i=1; $i<=4; $i++): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="year_level" value="<?php echo $i; ?>" 
                            <?php if(($student['year_level']??0) == $i) echo 'checked'; ?> required>
                            <label class="form-check-label"><?php echo getYearLevelStr($i); ?></label>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Course</label>
                    <select class="form-select" name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <option value="<?php echo COURSE_BSIS; ?>" <?php if(($student['course_id']??0)==COURSE_BSIS) echo 'selected'; ?>>BS Information System</option>
                        <option value="<?php echo COURSE_ACT; ?>" <?php if(($student['course_id']??0)==COURSE_ACT) echo 'selected'; ?>>Associate in Computer Tech</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save</button>
                <a href="admin_student_manage.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>

        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Manage Students</h2>
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
                    <input type="text" id="search" class="form-control" placeholder="Search name or ID..." onkeyup="loadTable()">
                </div>
                <div class="col-md-3">
                    <select id="sort_by" class="form-select" onchange="loadTable()">
                        <option value="last_name">Last Name</option>
                        <option value="first_name">First Name</option>
                    </select>
                </div>
            </div>

            <?php 
            $count = $db->querySingle("SELECT COUNT(*) FROM students"); 
            if ($count == 0): ?>
                <div class="alert alert-info">No student record found. Click "+ Add Student" to get started.</div>
                <a href="?action=create" class="btn btn-primary mt-2">+ Add Student</a>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student Number</th>
                                <th>Last Name</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Age</th>
                                <th>Phone</th>
                                <th>Year</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="table_data"></tbody>
                    </table>
                    <a href="?action=create" class="btn btn-primary btn-sched">+ Add Student</a>
                </div>
            <?php endif; ?>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="../js/load.js"></script>
        <?php endif; ?>
    </div>
</body>
</html>