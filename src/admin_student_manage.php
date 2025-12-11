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
if (file_exists(__DIR__ ."/notification.php")) require_once __DIR__ ."/notification.php";
if (file_exists(__DIR__ ."/db.php")) require_once __DIR__ ."/db.php";

// Basic DB connection if not provided by require
if (!function_exists('get_db')) {
    function get_db() {
        $db = new SQLite3('database.db');
        return $db;
    }
}
$db = get_db();

// Mock notification data
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

// ==========================================
// --- HANDLE AJAX SEARCH/SORT WITH PAGINATION ---
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search       = trim($_GET['q'] ?? '');
    $filterCourse = (int)($_GET['filter_course'] ?? 0);
    $sortBy       = $_GET['sort_by'] ?? 'last_name';
    
    // Pagination Variables
    $page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 5; // Number of records per page
    $offset = ($page - 1) * $limit;

    $validSorts = ['last_name', 'first_name', 'student_number'];
    if (!in_array($sortBy, $validSorts)) $sortBy = 'last_name';

    // Base WHERE clause
    $whereSQL = " WHERE 1=1";
    $params = [];

    if ($search) {
        $whereSQL .= " AND (student_number LIKE :search OR first_name LIKE :search OR last_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($filterCourse > 0) {
        $whereSQL .= " AND course_id = :course";
        $params[':course'] = $filterCourse;
    }

    // 1. COUNT QUERY (To get total pages)
    $countSql = "SELECT COUNT(*) as total FROM students $whereSQL";
    $stmtCount = $db->prepare($countSql);
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val, is_int($val) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    $totalRows = $stmtCount->execute()->fetchArray(SQLITE3_ASSOC)['total'] ?? 0;
    $totalPages = ceil($totalRows / $limit);

    // 2. DATA QUERY
    $sql = "SELECT * FROM students $whereSQL ORDER BY $sortBy ASC LIMIT :limit OFFSET :offset";

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
            <td class="fw-medium font-monospace text-primary"><?php echo htmlspecialchars($row['student_number']); ?></td>
            <td class="fw-medium text-dark"><?php echo htmlspecialchars($row['last_name']); ?></td>
            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
            <td class="text-secondary"><?php echo htmlspecialchars($row['middle_name']); ?></td>
            <td class="text-secondary"><?php echo (int)$row['age']; ?></td>
            <td class="text-secondary font-monospace"><?php echo htmlspecialchars($row['phone_number']); ?></td>
            <td class="text-secondary"><?php echo getCourseName($row['course_id']); ?></td>
            <td class="text-secondary"><?php echo getYearLevelStr($row['year_level']); ?></td>
            <td>
                <div class="d-flex gap-2">
                    <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                    <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this student?');" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></a>
                </div>
            </td>
        </tr>
        <?php
    }
    
    if (!$hasData) {
        echo '<tr><td colspan="9" class="text-center py-4 text-muted">No student records found</td></tr>';
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
<<<<<<< HEAD
        <span class="mobile-hidden">
=======
        <div class="text-end text-muted small" style="font-size: 0.85rem;">
>>>>>>> 5a2f5b6357b23ef4f417fe7f675233a444b8ef57
            Showing Page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </span>
        <?php
    }
    $paginationHtml = ob_get_clean();

    // Return JSON
    header('Content-Type: application/json');
    echo json_encode([
        'table_html' => $tableHtml,
        'pagination_html' => $paginationHtml
    ]);
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
        $stmtGetUser = $db->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmtGetUser->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmtGetUser->execute()->fetchArray(SQLITE3_ASSOC);
        $linked_user_id = $result['user_id'] ?? null;
        $db->exec("DELETE FROM notifications WHERE student_id = $id");

        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $stmt->execute();

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
    <title>ClassSched | Manage Student</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin_student_manage.css">
    <link rel="stylesheet" href="../styles/admin.css">
    <link rel="stylesheet" href="../styles/notification.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/pagination.css">
</head>
<body class="d-flex flex-column min-vh-100 position-relative">
    <?php if(file_exists(__DIR__ . "/student_nav.php")) require_once __DIR__ . "/student_nav.php"; ?>
    
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
                            <input type="number" class="form-control" name="age" value="<?php echo $student['age']??''; ?>" required 
                                    oninput="if(this.value.length > 2) this.value = this.value.slice(0, 2);">
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-medium text-secondary small">Phone Number</label>
                            <input type="text" class="form-control" name="phone_number" value="<?php echo $student['phone_number']??''; ?>" required maxlength="12">
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
                        <button type="submit" class="btn btn-primary px-4">Save</button>
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
                    <div class="col-5 col-md-3">
                        <select id="filter_course" class="form-select bg-light border-0 text-truncate" onchange="loadTable(1)" style="cursor:pointer;">
                            <option value="">All</option> 
                            <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                            <option value="<?php echo COURSE_ACT; ?>">ACT</option>
                        </select>
                    </div>
                    
                    <div class="col-7 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 ps-2 pe-1">
                                <i class="fa-solid fa-search text-secondary small"></i>
                            </span>
                            <input type="text" id="search" class="form-control bg-light border-0 ps-1" placeholder="Search student..." onkeyup="loadTable(1)">
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-3">
                        <div class="input-group">
                            <select id="sort_by" class="form-select bg-light border-0 ps-1" onchange="loadTable(1)" style="cursor:pointer;">
                                <option value="last_name">Sort by last name</option> 
                                <option value="first_name">Sort by first name</option>
                                <option value="student_number">Sort by student number</option>
                            </select>
                        </div>
                    </div>
                </div>

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
                                <th>Course</th>
                                <th>Year</th>
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
            
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
            <script src="../js/notification.js"></script>
            <script src="../js/pagination.js"></script>
        <?php endif; ?>
    </div>

    <footer class="bg-white border-top py-4 text-center mt-auto">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>
</body>
</html>