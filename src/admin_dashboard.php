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
$result = $db->query("SELECT COUNT(id) AS total_students FROM students");
$row = $result->fetchArray(SQLITE3_ASSOC);
$student_enrolled = $row['total_students'] ?? 0;


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <!-- <link rel="stylesheet" href="../style.css"> -->
</head>

<body>
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

        <h3>Bachelor of Scince in Information Systems Class Schedule</h3> <!--template lang ito -->

        <table>
            <tr>
                <th>Subject</th>
                <th>Teacher</th>
                <th>Room</th>
                <th>Time Start</th>
                <th>Time End</th>
            </tr>

            <tr>
                <td>Web Dev</td>
                <td>Mr. Casimiro</td>
                <td>Comlab B</td>
                <td>12:00 PM</td>
                <td>3:00 PM</td>
            </tr>

            <tr>
                <td>Responsive Web</td>
                <td>Mr. Salette</td>
                <td>Comlab B</td>
                <td>5:00 PM</td>
                <td>7:00 PM</td>
            </tr>
        </table>

    </div>

</body>
</html>