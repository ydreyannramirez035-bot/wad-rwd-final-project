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
    <title>Profile</title>
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
        footer{
            margin-top:80px;
            width:100%;
            padding:24px 32px;
            background:#f8f9fc;
            color:#6b7280;
            text-align:center;

            /* para sticky feel pero hindi naka-fix */
            border-top:1px solid #eee;
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

    <!-- PROFILE -->
    <main class="container py-5">

        <h3 class="fw-bold mb-4">My profile</h3>

        <div class="row g-4">

            <!-- LEFT PROFILE CARD -->
            <div class="col-md-4">
                <div class="p-4 bg-white rounded-3 shadow-sm text-center">

                    <!-- PROFILE INITIALS CIRCLE -->
                    <?php  
                        $words = explode(" ", $user["name"]);
                        $initials = "";
                        foreach($words as $w) { $initials .= strtoupper($w[0]); }
                    ?>
                    <div class="rounded-circle mx-auto mb-3 d-flex justify-content-center align-items-center"
                        style="width: 80px; height: 80px; background: #4a6cf7; color: white; font-size: 30px;">
                        <?= $initials ?>
                    </div>

                    <h5 class="fw-semibold mb-0">
                        <?= htmlspecialchars($user["name"]); ?>
                    </h5>
                    <small class="text-muted d-block mb-3">Student · ACT 2</small>

                    <p class="text-muted small mb-4">
                        Bio here. Bio here. Bio here.
                    </p>

                    <a href="student_edit_profile.php" class="btn btn-primary btn-sm mb-4">
                        ✎ Edit profile
                    </a>

                    <h6 class="fw-semibold text-start">Contacts</h6>
                    <p class="text-start mb-1">
                        <a href="mailto:student@example.com" class="text-decoration-none">
                            student@example.com
                        </a>
                    </p>
                    <p class="text-start mb-0">090000000</p>

                </div>
            </div>

            <!-- RIGHT INFO FORM CARD -->
            <div class="col-md-8">
                <div class="p-4 bg-white rounded-3 shadow-sm">

                    <h5 class="mb-4 fw-semibold">Profile details</h5>

                    <section>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First name</label>
                                <input type="text" class="form-control" value="Ydrey Ann">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last name</label>
                                <input type="text" class="form-control" value="Ramirez">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Email address</label>
                                <input type="email" class="form-control" value="student@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" value="090000000">
                            </div>
                        </div>
                    </section>

                </div>
            </div>

        </div>

        <footer>
            ClassSched © 2025 — All rights of Humans XD.
        </footer>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>