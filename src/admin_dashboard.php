<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];

require_once __DIR__ ."/db.php";
// Initialize database connection
$db = get_db();
$total_result = $db->query("SELECT COUNT(id) AS total_students FROM students");
$total_bsis = $db->query("SELECT COUNT(id) AS total_bsis FROM students WHERE course_id = ?");
$row = $total_result->fetchArray(SQLITE3_ASSOC);
$student_enrolled = $row['total_students'] ?? 0;
$student_bsis = $row['total_bsis'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <!-- <link rel="stylesheet" href="../style.css"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body>
    <div class="btn-group">
        <button type="button" class="btn btn-danger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            ADMIN
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
        </ul>
    </div>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Navbar</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="admin_student_manage.php">Students</a>
                </li>
                <li class="nav-item">
                <a class="nav-link" href="admin_schedule.php">Schedule</a>
                </li>
            </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2>Hi, <?php echo htmlspecialchars($user["name"]); ?>!</h2>
        <p>Here's what's happening today.</p>
        <p>Last update: just now</p>

        <div class="stats">
            <div class="card">Students<br><strong><?php echo $student_enrolled; ?></strong></div>
            <div class="card">Classes<br><strong>19</strong></div>
            <div class="card">Teachers<br><strong>19</strong></div>
            <div class="card">Rooms<br><strong>20</strong></div>
        </div>

        <p> 
            <select name="course_id" required>
                <option value="<?php echo COURSE_BSIS; ?>" <?php if($student['course_id']==COURSE_BSIS) echo 'selected'; ?>>Bachelor of Science in Information System</option>
                <option value="<?php echo COURSE_ACT; ?>" <?php if($student['course_id']==COURSE_ACT) echo 'selected'; ?>>Associate in Computer Technology</option>
            </select>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>>

</body>
</html>