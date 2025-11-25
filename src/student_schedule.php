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
    <title>Class Schedule</title>
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
            <a href="student_dashboard.php">Dashboard</a>
            <a href="#">Class Schedule</a>
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

    <!-- CLASS SCHEDULE PAGE -->
    <main class="container py-5">
        <div class="dashboard-section">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-semibold m-0">Class Schedule</h4>

                <select class="form-select w-auto">
                    <option>Monday</option>
                    <option>Tuesday</option>
                    <option>Wednesday</option>
                    <option>Thursday</option>
                    <option>Friday</option>
                </select>
            </div>

            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Subject</th>
                        <th>Teacher</th>
                        <th>Room</th>
                        <th>Time</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Web Dev</td>
                        <td>Mr. Teacher</td>
                        <td>Comlab A</td>
                        <td>9:00 AM – 12:00 PM</td>
                    </tr>
                    <tr>
                        <td>ISNT</td>
                        <td>Ms. Professor</td>
                        <td>Room 203</td>
                        <td>1:00 PM – 3:00 PM</td>
                    </tr>
                </tbody>
            </table>

        </div>
    </main>

    <script class="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>