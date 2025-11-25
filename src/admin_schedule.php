<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
$user = $_SESSION["user"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Schedule</title>
    <!-- <link rel="stylesheet" href="../style.css"> -->
</head>

<body>

<nav class="navbar">
    <div class="logo">ClassSched</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="schedule_manage.php" class="active">Schedule</a></li>
    </ul>
    <div class="user-menu">
        <span><?php echo htmlspecialchars($user["name"]); ?></span>
        <a href="logout.php">Logout</a>
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