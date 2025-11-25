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
    <title>Edit Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f4f7ff;
        }
        .navbar {
            background: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        /* .logo span {
            font-weight: 600;
            font-size: 20px;
            color: #2662f0;
        } */
        .nav-links a {
            margin-right: 25px;
            color: #333;
            font-weight: 500;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar justify-content-between">
        <div class="logo">
            <img src="logo.jpg" alt="Logo" width="60" height="60">
            <span class="text-">Class</span>
            <span class="brand-sub">Sched</span>
        </div>

        <div class="nav-links">
            <a href="#">Dashboard</a>
            <a href="student_schedule.php">Class Schedule</a>
        </div>

        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <!-- make the profile dropdown show user's initials only, hindi yung buong pangalan -->
                <?php echo htmlspecialchars($user["name"]); ?>
            </button>

            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="student_profile.php">Profile</a></li>
                <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- EDIT PROFILE -->
    <main class="edit-profile">

        <h2>Edit Profile</h2>

        <form action="#" method="post">

            <label>First Name</label>
            <input type="text" name="fname">

            <label>Last Name</label>
            <input type="text" name="lname">

            <label>Email Address</label>
            <input type="email" name="email">

            <label>Phone</label>
            <input type="text" name="phone">

            <label>Bio / Notes</label>
            <textarea name="bio"></textarea>

            <button type="submit">Save Changes</button>
            <a href="profile.php">Cancel</a>

        </form>

    </main>

    <script class="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>