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
    <title>ClassSched | Student Dashboard</title>
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
        .brand-text {
            color: #2662f0;
        }
        .brand-sub {
            color: #2662f0;
        }
        .nav-links a {
            margin-right: 25px;
            color: #333;
            font-weight: 500;
            text-decoration: none;
        }
        .nav-profile button {
            background: #2662f0;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 50px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .dashboard-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
    </style>
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar justify-content-between">
        <div class="logo">
            <img src="../img/logo.jpg" width="60" height="60">
            <span class="brand-text">Class</span>
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

    <!-- DASHBOARD -->
    <main class="container py-5">
        <h3 class="fw-semibold">Hi, <?php echo htmlspecialchars($user["name"]); ?>!</h3>
        <p class="text-muted mb-4">Here’s what’s happening today.</p>

        <!-- STAT CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted">Classes today</h6>
                    <h3 class="fw-bold">2</h3>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted">Subjects enrolled</h6>
                    <h3 class="fw-bold">10</h3>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <h6 class="text-muted">Upcoming events</h6>
                    <h3 class="fw-bold">0</h3>
                </div>
            </div>
        </div>

        <!-- TODAY’S CLASS SCHEDULE -->
        <div class="dashboard-section">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="fw-semibold">Today’s class schedule</h5>

                <select class="form-select w-auto">
                    <option>Monday</option>
                </select>
            </div>

            <table class="table table-bordered align-middle">
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
                        <td>Human WAD</td>
                        <td>Mr. Human</td>
                        <td>Human B</td>
                        <td>Human PM</td>
                    </tr>
                    <tr>
                        <td>Hu RWD</td>
                        <td>Mr. Human1</td>
                        <td>Human B</td>
                        <td>Human PM</td>
                    </tr>
                </tbody>
            </table>

            <div class="text-center mt-3">
                <a href="student_schedule.php" class="btn btn-primary">View full sched</a>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>