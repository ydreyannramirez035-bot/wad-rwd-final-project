<?php
session_start();

// --- CONFIGURATION & DATABASE ---
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
    <link rel="stylesheet" href="../try.css">
</head>
<body>

    <nav>
        <a href="admin_dashboard.php">Dashboard</a> | 
        <a href="admin_schedule.php">Schedule</a> | 
        <a href="admin_student_manage.php"><strong>Manage Students</strong></a>
    </nav>
    <hr>

    <?php if ($msg): ?>
        <p class="msg-success"><?php echo htmlspecialchars($msg); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="msg-error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>


    <?php if ($action === 'create'): ?>
        
        <h3>Add New Student</h3>
        <form method="post" action="?action=store">
            <p>Student No: <input type="text" name="student_number" required></p>
            <p>First Name: <input type="text" name="first_name" required></p>
            <p>Middle Name: <input type="text" name="middle_name"></p>
            <p>Last Name: <input type="text" name="last_name" required></p>
            <p>Age: <input type="number" name="age" required></p>
            <p>Phone Number: <input type="text" name="phone_number" required></p>
            <p>Email: <input type="email" name="email" required></p>
            
            <p>Year Level: 
                <label><input type="radio" name="year_level" value="1" required> 1st Year</label>
                <label><input type="radio" name="year_level" value="2"> 2nd Year</label>
                <label><input type="radio" name="year_level" value="3"> 3rd Year</label>
                <label><input type="radio" name="year_level" value="4"> 4th Year</label>
            </p>

            <p>Course: 
                <select name="course_id" required>
                    <option value="">-- Select --</option>
                    <option value="<?php echo COURSE_BSIS; ?>">Bachelor of Science in Information System</option>
                    <option value="<?php echo COURSE_ACT; ?>">Associate in Computer Technology</option>
                </select>
            </p>
            <button type="submit">Save</button> 
            <a href="admin_student_manage.php">Cancel</a>
        </form>


    <?php elseif ($action === 'edit'): 
        // VIEW: EDIT STUDENT FORM
        $id = (int)($_GET["id"] ?? 0);
        $student = $db->querySingle("SELECT * FROM students WHERE id = $id", true);

        if (!$student): ?>
            <p>Student not found.</p>
            <a href="admin_student_manage.php">Back to List</a>
        <?php else: ?>
            
            <h3>Edit Student</h3>
            <form method="post" action="?action=update">
                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                
                <p>Student No: <input type="text" name="student_number" value="<?php echo htmlspecialchars($student['student_number']); ?>" required></p>
                <p>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required></p>
                <p>Middle Name: <input type="text" name="middle_name" value="<?php echo htmlspecialchars($student['middle_name']); ?>"></p>
                <p>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required></p>
                <p>Age: <input type="number" name="age" value="<?php echo $student['age']; ?>" required></p>
                <p>Phone Number: <input type="text" name="phone_number" value="<?php echo $student['phone_number']; ?>" required></p>
                <p>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required></p>
                
                <p>Year Level:
                    <?php for($i=1; $i<=4; $i++): ?>
                        <label>
                            <input type="radio" name="year_level" value="<?php echo $i; ?>" 
                            <?php if($student['year_level'] == $i) echo 'checked'; ?>> 
                            <?php echo getYearLevelStr($i); ?>
                        </label>
                    <?php endfor; ?>
                </p>
                
                <p>Course:
                    <select name="course_id" required>
                        <option value="<?php echo COURSE_BSIS; ?>" <?php if($student['course_id'] == COURSE_BSIS) echo 'selected'; ?>>BS Information System</option>
                        <option value="<?php echo COURSE_ACT; ?>" <?php if($student['course_id'] == COURSE_ACT) echo 'selected'; ?>>Associate in Computer Tech</option>
                    </select>
                </p>
                
                <button type="submit">Update</button> 
                <a href="admin_student_manage.php">Cancel</a>
            </form>
        <?php endif; ?>


    <?php else: ?>
        
        <h2>Manage Students</h2>
        
        <div class="controls">
            <select id="filter_course" onchange="loadTable()">
                <option value="">All Courses</option>
                <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                <option value="<?php echo COURSE_ACT; ?>">ACT</option>
            </select>

            <input type="text" id="search" placeholder="Search name or ID..." onkeyup="loadTable()">
            
            <select id="sort_by" onchange="loadTable()">
                <option value="last_name">Last Name</option>
                <option value="first_name">First Name</option>
            </select>
        </div>

        <?php 
        $count = $db->querySingle("SELECT COUNT(*) FROM students");
        
        if ($count == 0): ?>
            
            <div class="empty-state">
                <h3>No students record found</h3>
                <p>Click the button below to get started.</p>
                <a href="?action=create"><button>+ Add Student</button></a>
            </div>

        <?php else: ?>
            <table>
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
                <tbody id="table_data">
                    </tbody>
            </table>
            <div style="margin-bottom: 10px; text-align: right;">
                <a href="?action=create"><button>+ Add Student</button></a>
            </div>

            <script src="../js/load.js"></script>

        <?php endif; ?> 
    <?php endif; ?>

</body>
</html>