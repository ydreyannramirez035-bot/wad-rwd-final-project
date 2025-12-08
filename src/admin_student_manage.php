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
$highlight_stmt = $db->prepare("
    SELECT COUNT(*) FROM notifications 
    WHERE is_read = 0
      AND (message LIKE '%bio%' OR message LIKE '%phone%')
");
$highlight_count = $highlight_stmt->execute()->fetchArray()[0];

define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

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

    // Render Rows with UI Styling
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        ?>
        <tr>
            <td class="fw-medium font-monospace text-primary"><?php echo htmlspecialchars($row['student_number']); ?></td>
            <td class="fw-medium text-dark"><?php echo htmlspecialchars($row['last_name']); ?></td>
            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($row['middle_name']); ?></td>
            <td class="text-secondary"><?php echo (int)$row['age']; ?></td>
            <td class="text-secondary font-monospace"><?php echo htmlspecialchars($row['phone_number']); ?></td>
            <td><span class="badge bg-brand-blue rounded-pill"><?php echo getYearLevelStr($row['year_level']); ?></span></td>
            <td>
                <div class="d-flex gap-2">
                    <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                    <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this student?');" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></a>
                </div>
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
        $stmt->bindValue(7, strtolower(trim($_POST["email"])));
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
    $stmt->bindValue(7, strtolower(trim($_POST["email"])));
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
        // Step 1: Find if this student has a linked User Account
        $stmtGetUser = $db->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmtGetUser->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmtGetUser->execute()->fetchArray(SQLITE3_ASSOC);
        $linked_user_id = $result['user_id'] ?? null;

        // Step 2: Delete the Student Profile
        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();

        // Step 3: If a linked User Account exists, delete it too!
        if ($linked_user_id) {
            $stmtUser = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmtUser->bindValue(1, $linked_user_id, SQLITE3_INTEGER);
            $stmtUser->execute();
        }

        header("Location: admin_student_manage.php?msg=Student+and+linked+User+Account+deleted");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_student_manage.css">
    <link rel="stylesheet" href="../styles/admin.css">
    <link rel="stylesheet" href="../styles/notification.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="d-flex flex-column min-vh-100 position-relative">

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid px-4">

            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <a class="navbar-brand ms-2" href="admin_dashboard.php">
                <img src="../img/logo.png" width="60" height="60" class="me-2">
            </a>

            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
                <img src="../img/logo.png" width="60" height="60" class="me-2">
                <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Menu</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav justify-content-start flex-grow-1 pe-3">
                <li class="nav-item"><a class="nav-link" href="admin_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="admin_student_manage.php">Students</a></li>
                <li class="nav-item"><a class="nav-link" href="admin_schedule.php">Schedule</a></li>
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

    <div class="container px-4 py-5">
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <div class="bg-white rounded-4 shadow-sm border p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-pen text-brand-blue"></i>
                        <?php echo ucfirst($action); ?> Student
                    </h5>
                    <a href="admin_student_manage.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back
                    </a>
                </div>

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

                <form method="post" action="?action=<?php echo ($action==='edit') ? 'update' : 'store'; ?>">
                    <?php if ($action==='edit') echo '<input type="hidden" name="id" value="'.$student['id'].'">'; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-secondary small">Student Number</label>
                            <input type="text" class="form-control" name="student_number" value="<?php echo $student['student_number']??''; ?>" required placeholder="e.g. 2023-0001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-medium text-secondary small">Course</label>
                            <select class="form-select" name="course_id" required>
                                <option value="">-- Select Course --</option>
                                <option value="<?php echo COURSE_BSIS; ?>" <?php if(($student['course_id']??0)==COURSE_BSIS) echo 'selected'; ?>>BS Information System</option>
                                <option value="<?php echo COURSE_ACT; ?>" <?php if(($student['course_id']??0)==COURSE_ACT) echo 'selected'; ?>>Associate in Computer Tech</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-medium text-secondary small">First Name</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo $student['first_name']??''; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-medium text-secondary small">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" value="<?php echo $student['middle_name']??''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-medium text-secondary small">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo $student['last_name']??''; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-medium text-secondary small">Age</label>
                            <input type="number" class="form-control" name="age" value="<?php echo $student['age']??''; ?>" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-medium text-secondary small">Phone Number</label>
                            <input type="text" class="form-control" name="phone_number" value="<?php echo $student['phone_number']??''; ?>" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-medium text-secondary small">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo $student['email']??''; ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-secondary small d-block">Year Level</label>
                        <div class="card p-3 bg-light border-0 d-inline-block w-100">
                            <?php for($i=1; $i<=4; $i++): ?>
                                <div class="form-check form-check-inline me-4">
                                    <input class="form-check-input" type="radio" name="year_level" id="year_<?php echo $i; ?>" value="<?php echo $i; ?>" 
                                    <?php if(($student['year_level']??0) == $i) echo 'checked'; ?> required>
                                    <label class="form-check-label" for="year_<?php echo $i; ?>"><?php echo getYearLevelStr($i); ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">Save Student</button>
                        <a href="admin_student_manage.php" class="btn btn-light px-4">Cancel</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h1 class="fw-bold text-dark mb-2">Students</h1>
                    <p class="text-secondary mb-0 small d-none d-sm-block">Manage student records and information.</p>
                </div>
                <div>
                    <a href="?action=create" class="btn btn-primary btn-sched rounded-pill px-3 px-md-4">
                        <i class="fa-solid fa-plus me-1"></i> <span>Add Student</span>
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-4 shadow-sm border p-4">
                
                <div class="row mb-4 g-2">
                    <div class="col-3 col-md-3">
                        <select id="filter_course" class="form-select bg-light border-0 text-truncate" onchange="loadTable()" style="cursor:pointer;">
                            <option value="">All</option> <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                            <option value="<?php echo COURSE_ACT; ?>">ACT</option>
                        </select>
                    </div>
                    
                    <div class="col-5 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 ps-2 pe-1"><i class="fa-solid fa-search text-secondary small"></i></span>
                            <input type="text" id="search" class="form-control bg-light border-0 ps-1" placeholder="Search..." onkeyup="loadTable()">
                        </div>
                    </div>
                    
                    <div class="col-4 col-md-3">
                        <select id="sort_by" class="form-select bg-light border-0 text-truncate" onchange="loadTable()" style="cursor:pointer;">
                            <option value="last_name">Sort: Name</option> <option value="first_name">Sort: First</option>
                        </select>
                    </div>
                </div>

                <?php 
                $count = $db->querySingle("SELECT COUNT(*) FROM students"); 
                if ($count == 0): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-user-graduate fs-1 mb-3 text-secondary opacity-50"></i>
                        <p class="mb-0">No student records found.</p>
                        <small>Click "Add Student" to get started.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table custom-table table-hover mb-0">
                            <thead>
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
                    </div>
                <?php endif; ?>
            </div>
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="../js/load.js"></script>
            <script src="../js/notification.js"></script>
        <?php endif; ?>
    </div>
</body>
</html>