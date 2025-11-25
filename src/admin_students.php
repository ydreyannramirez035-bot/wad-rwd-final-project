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
    <title>Students</title>
    <!-- <link rel="stylesheet" href="../style.css"> -->
</head>

<body>

<nav class="navbar">
    <div class="logo">ClassSched</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="students.php" class="active">Students</a></li>
        <li><a href="schedule_manage.php">Schedule</a></li>
    </ul>
    <div class="user-menu">
        <span><?php echo htmlspecialchars($user["name"]); ?></span>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <h2>Students</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Last Name</th>
            <th>First Name</th>
            <th>Middle Name</th>
            <th>Age</th>
            <th>Year</th>
        </tr>

        <tr>
            <td>101</td>
            <td>Agnate</td>
            <td>Janice</td>
            <td>V</td>
            <td>19</td>
            <td>2nd year</td>
        </tr>

        <tr>
            <td>102</td>
            <td>Balmoceda</td>
            <td>JR</td>
            <td>—</td>
            <td>19</td>
            <td>2nd year</td>
        </tr>

        <tr>
            <td>103</td>
            <td>Balitista</td>
            <td>Anthony</td>
            <td>—</td>
            <td>19</td>
            <td>2nd year</td>
        </tr>

    </table>

</div>

</body>
</html>