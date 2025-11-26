<?php
session_start();

// 1. Security Check
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/db.php";
$db = get_db();

define('COURSE_BSIS', 1);
define('COURSE_ACT', 2);

$action = $_GET["action"] ?? "list";
$msg    = $_GET["msg"] ?? "";
$error  = "";

// --- PART A: AJAX HANDLER (Updates table without refresh) ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $search = trim($_GET['q'] ?? '');
    $filterCourse = (int)($_GET['filter_course'] ?? 0);
    $sortBy = $_GET['sort_by'] ?? 'last_name';

    // Build the query
    $sql = "SELECT * FROM students WHERE 1=1";
    if ($search) {
        $sql .= " AND (student_number LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%')";
    }
    if ($filterCourse > 0) {
        $sql .= " AND course_id = $filterCourse";
    }
    $sql .= " ORDER BY $sortBy ASC";

    $result = $db->query($sql);

    // Output only the rows (HTML)
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        
        $courseName = "Unknown";
        if ($row['course_id'] == COURSE_BSIS) {
            $courseName = "BSIS";
        } elseif ($row['course_id'] == COURSE_ACT) {
            $courseName = "ACT";
        }

        $yearStr = "Unknown";
        $yl = (int)$row['year_level'];
        if ($yl == 1) $yearStr = "1st Year";
        elseif ($yl == 2) $yearStr = "2nd Year";
        elseif ($yl == 3) $yearStr = "3rd Year";
        elseif ($yl == 4) $yearStr = "4th Year";

        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['student_number']); ?></td>
            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
            <td><?php echo htmlspecialchars($row['middle_name']); ?></td>
            <td><?php echo (int)$row['age']; ?></td>
            <td><?php echo htmlspecialchars($yearStr); ?></td>
            <td>
                <a href="?action=edit&id=<?php echo $row['id']; ?>">Edit</a> | 
                <a href="?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this student?');">Delete</a>
            </td>
        </tr>
        <?php
    }
    exit; // Stop PHP here
}

// --- PART B: HANDLE ADD STUDENT ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "store") {
    $student_number = trim($_POST["student_number"]);
    $first_name     = trim($_POST["first_name"]);
    $middle_name    = trim($_POST["middle_name"]);
    $last_name      = trim($_POST["last_name"]);
    $age            = (int)$_POST["age"];
    $email          = trim($_POST["email"]);
    $courseId       = (int)$_POST["course_id"];
    $year_level     = (int)$_POST["year_level"];

    // Check if exists
    $checkSql = "SELECT COUNT(*) as count FROM students WHERE student_number = '$student_number'";
    $exists = $db->querySingle($checkSql);

    if ($exists > 0) {
        $error = "Error: Student Number '$student_number' already exists!";
        $action = "create"; 
    } else {
        $stmt = $db->prepare("INSERT INTO students (student_number, first_name, middle_name, last_name, age, email, course_id, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bindValue(1, $student_number, SQLITE3_TEXT);
        $stmt->bindValue(2, $first_name, SQLITE3_TEXT);
        $stmt->bindValue(3, $middle_name, SQLITE3_TEXT);
        $stmt->bindValue(4, $last_name, SQLITE3_TEXT);
        $stmt->bindValue(5, $age, SQLITE3_INTEGER);
        $stmt->bindValue(6, $email, SQLITE3_TEXT);
        $stmt->bindValue(7, $courseId, SQLITE3_INTEGER);
        $stmt->bindValue(8, $year_level, SQLITE3_INTEGER); 
        $stmt->execute();
        header("Location: admin_student_manage.php?msg=Student+added+successfully");
        exit;
    }
}

// --- PART C: HANDLE UPDATE STUDENT ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $action === "update") {
    $id = (int)$_POST["id"];
    $student_number = trim($_POST["student_number"]);
    $first_name = trim($_POST["first_name"]); $middle_name = trim($_POST["middle_name"]); $last_name = trim($_POST["last_name"]);
    $age = (int)$_POST["age"]; $email = trim($_POST["email"]); $courseId = (int)$_POST["course_id"]; $year_level = (int)$_POST["year_level"];

    $stmt = $db->prepare("UPDATE students SET student_number=?, first_name=?, middle_name=?, last_name=?, age=?, email=?, course_id=?, year_level=? WHERE id=?");
    $stmt->bindValue(1, $student_number); 
    $stmt->bindValue(2, $first_name); 
    $stmt->bindValue(3, $middle_name);
    $stmt->bindValue(4, $last_name); 
    $stmt->bindValue(5, $age); 
    $stmt->bindValue(6, $email);
    $stmt->bindValue(7, $courseId); 
    $stmt->bindValue(8, $year_level);
    $stmt->bindValue(9, $id);
    $stmt->execute();
    
    header("Location: admin_student_manage.php?msg=Student+updated");
    exit;
}

// --- PART D: HANDLE DELETE ---
if ($action === "delete") {
    $id = (int)($_GET["id"] ?? 0);
    if ($id > 0) {
        $db->exec("DELETE FROM students WHERE id = $id");
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
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="#">Navbar</a>
        <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
            <div class="navbar-nav">
            <a class="nav-item nav-link" href="admin_dashboard.php">Dashboard</a>
            <a class="nav-item nav-link" href="admin_schedule.php">Schedule</a>
            <a class="nav-item nav-link" href="admin_student_manage.php">Manage Students</a>
            </div>
        </div>
    </nav>
    <hr>

    <?php if ($msg) echo "<p style='color:green'><b>$msg</b></p>"; ?>
    <?php if ($error) echo "<p style='color:red'><b>$error</b></p>"; ?>

    <?php if ($action === 'create'): ?>
        <h3>Add New Student</h3>
        <form method="post" action="?action=store">
            <p>Student No: <input type="text" name="student_number" required></p>
            <p>First Name: <input type="text" name="first_name" required></p>
            <p>Middle Name: <input type="text" name="middle_name"></p>
            <p>Last Name: <input type="text" name="last_name" required></p>
            <p>Age: <input type="number" name="age" required></p>
            <p>Email: <input type="email" name="email" required></p>
            
            <p>Year Level: 
                <input type="radio" name="year_level" value="1" required> 1st Year
                <input type="radio" name="year_level" value="2"> 2nd Year
                <input type="radio" name="year_level" value="3"> 3rd Year
                <input type="radio" name="year_level" value="4"> 4th Year
            </p>

            <p>Course: 
                <select name="course_id" required>
                    <option value="">-- Select --</option>
                    <option value="<?php echo COURSE_BSIS; ?>">Bachelor of Science in Information System</option>
                    <option value="<?php echo COURSE_ACT; ?>">Associate in Computer Technology</option>
                </select>
            </p>
            <button type="submit">Save</button> <a href="admin_student_manage.php">Cancel</a>
        </form>

    <?php elseif ($action === 'edit'): 
        $id = (int)$_GET['id'];
        $s = $db->querySingle("SELECT * FROM students WHERE id=$id", true);
    ?>
        <h3>Edit Student</h3>
        <form method="post" action="?action=update">
            <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
            <p>Student No: <input type="text" name="student_number" value="<?php echo htmlspecialchars($s['student_number']); ?>" required></p>
            <p>First Name: <input type="text" name="first_name" value="<?php echo htmlspecialchars($s['first_name']); ?>" required></p>
            <p>Middle Name: <input type="text" name="middle_name" value="<?php echo htmlspecialchars($s['middle_name']); ?>"></p>
            <p>Last Name: <input type="text" name="last_name" value="<?php echo htmlspecialchars($s['last_name']); ?>" required></p>
            <p>Age: <input type="number" name="age" value="<?php echo $s['age']; ?>" required></p>
            <p>Email: <input type="email" name="email" value="<?php echo htmlspecialchars($s['email']); ?>" required></p>
            
            <p>Year Level:
                <input type="radio" name="year_level" value="1" <?php if($s['year_level']==1) echo 'checked'; ?>> 1st Year
                <input type="radio" name="year_level" value="2" <?php if($s['year_level']==2) echo 'checked'; ?>> 2nd Year
                <input type="radio" name="year_level" value="3" <?php if($s['year_level']==3) echo 'checked'; ?>> 3rd Year
                <input type="radio" name="year_level" value="4" <?php if($s['year_level']==4) echo 'checked'; ?>> 4th Year
            </p>

            <p>Course: 
                <select name="course_id" required>
                    <option value="<?php echo COURSE_BSIS; ?>" <?php if($s['course_id']==COURSE_BSIS) echo 'selected'; ?>>Bachelor of Science in Information System</option>
                    <option value="<?php echo COURSE_ACT; ?>" <?php if($s['course_id']==COURSE_ACT) echo 'selected'; ?>>Associate in Computer Technology</option>
                </select>
            </p>
            <button type="submit">Update</button> <a href="admin_student_manage.php">Cancel</a>
        </form>

    <?php else: ?>

        <h2>Manage Students</h2>
        
        <div style="background:#eee; padding:10px;">
            <label></label>
            <select id="filter_course" onchange="loadTable()">
                <option value="">All Courses</option>
                <option value="<?php echo COURSE_BSIS; ?>">BSIS</option>
                <option value="<?php echo COURSE_ACT; ?>">ACT</option>
            </select>

            <input type="text" id="search" placeholder="Search..." onkeyup="loadTable()">
            <label></label>
            <select id="sort_by" onchange="loadTable()">
                <option value="last_name">Last Name</option>
                <option value="first_name">First Name</option>
            </select>
            
        </div>
        <br>

        <table border="1" cellpadding="8" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>Student Number</th>
                    <th>Last Name</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Age</th>
                    <th>Year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="table_data">
                </tbody>
        </table>
        <br>
        <a href="?action=create"><button style="float:right">+ Add Student</button></a>

        <script src="../js/load.js"></script>

    <?php endif; ?>

</body>
</html>