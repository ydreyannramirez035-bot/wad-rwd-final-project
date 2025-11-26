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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Schedule</title>
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

    <h2>Manage Schedule</h2>

    <input type="text" placeholder="Search class...">

    <table>
        <tr>
            <th>Day</th>
            <th>Subject</th>
            <th>Teacher</th>
            <th>Room</th>
            <th>Time Start</th>
            <th>Time End</th>
            <th>Actions</th>
        </tr>

        <tr>
            <td>Monday</td>
            <td>Web Dev</td>
            <td>Mr. Casimiro</td>
            <td>Comlab B</td>
            <td>12:00 PM</td>
            <td>3:00 PM</td>
            <td><a href="#">edit</a> | <a href="#">delete</a></td>
        </tr>

        <tr>
            <td>Monday</td>
            <td>Responsive Web</td>
            <td>Mr. Salette</td>
            <td>Comlab B</td>
            <td>5:00 PM</td>
            <td>7:00 PM</td>
            <td><a href="#">edit</a> | <a href="#">delete</a></td>
        </tr>

        <tr>
            <td>Tuesday</td>
            <td>DSA</td>
            <td>Mr. Saballo</td>
            <td>401</td>
            <td>8:00 AM</td>
            <td>10:00 AM</td>
            <td><a href="#">edit</a> | <a href="#">delete</a></td>
        </tr>

    </table>

    <button>Add new class</button>

</div>

</body>
</html>