<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}
$userEmail = $_SESSION['email'];

// Include database connection
include 'conn.php';

// Check if $conn is a valid database connection object
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

// Fetch total students
$sqlStudents = "SELECT COUNT(*) AS total_students FROM students";
$resultStudents = $conn->query($sqlStudents);
$totalStudents = $resultStudents && $resultStudents->num_rows > 0 ? $resultStudents->fetch_assoc()['total_students'] : 0;

// Fetch present students today
$today = date('Y-m-d');
$sqlPresentToday = "SELECT COUNT(*) AS present_today FROM attendance WHERE date = '$today' AND status = 'present'";
$resultPresent = $conn->query($sqlPresentToday);
$presentToday = $resultPresent && $resultPresent->num_rows > 0 ? $resultPresent->fetch_assoc()['present_today'] : 0;

$absentToday = $totalStudents - $presentToday;
$attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
$absenceRate = 100 - $attendanceRate;

// Prepare data for attendance overview chart
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$attendanceOverview = [];

foreach ($weekdays as $day) {
    $sqlDate = "SELECT MAX(date) AS last_date FROM attendance WHERE DAYNAME(date) = '$day'";
    $resDate = $conn->query($sqlDate);
    $lastDate = ($resDate && $resDate->num_rows > 0) ? $resDate->fetch_assoc()['last_date'] : null;

    if ($lastDate) {
        $sqlPresent = "SELECT COUNT(*) AS present_count FROM attendance WHERE date = '$lastDate' AND status = 'present'";
        $resPresent = $conn->query($sqlPresent);
        $presentCount = ($resPresent && $resPresent->num_rows > 0) ? $resPresent->fetch_assoc()['present_count'] : 0;
        $percent = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 1) : 0;

        $attendanceOverview[$day] = [
            'date' => $lastDate,
            'present' => $presentCount,
            'percentage' => $percent
        ];
    } else {
        $attendanceOverview[$day] = [
            'date' => 'N/A',
            'present' => 0,
            'percentage' => 0
        ];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Admin Dashboard for Student Management System">
    <meta name="author" content="Your Name">
    <title>Admin Dashboard</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (used for Quick Actions) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* Custom styles for sidebar toggling */
        #accordionSidebar {
            transition: width 0.3s ease-in-out, margin 0.3s ease-in-out;
            background-color: rgb(7, 29, 230);
        }

        #accordionSidebar.toggled {
            width: 0 !important;
            overflow: hidden;
            margin-left: -225px;
        }

        #content-wrapper.toggled {
            margin-left: 0;
        }

        #content-wrapper {
            transition: margin 0.3s ease-in-out;
        }

        #sidebarToggle {
            background-color: transparent;
            color: rgba(255, 255, 255, 0.8);
            border: none;
            outline: none;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #sidebarToggle:hover {
            color: white;
        }

        #sidebarToggle i {
            transition: transform 0.3s ease-in-out;
        }

        #accordionSidebar.toggled #sidebarToggle i {
            transform: rotate(180deg);
        }

        /* Social media links styling */
        .social-links .nav-link {
            padding: 0.5rem 1rem;
        }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center" href="admin_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
                <img src="img/logo.jpg" alt="School Logo" class="rounded-circle mb-2" style="width: 85px; height: 85px; object-fit: cover;">
                <div class="sidebar-brand-text" style="font-weight: 500; font-size: 1rem;">ADMIN</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item active">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Student Management
            </div>

            <!-- Nav Item - Student Management Links -->
            <li class="nav-item">
                <a class="nav-link" href="students_list.php">
                    <i class="fa fa-user-graduate"></i>
                    <span>Add Student</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="guardian.php">
                    <i class="fa fa-house-user"></i>
                    <span>Guardian Of Students</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="enrollment.php">
                    <i class="fa fa-pen-alt"></i>
                    <span>Enroll Student</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="link_rfid.php">
                    <i class="fa fa-credit-card"></i>
                    <span>Link RFID</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Teachers Management
            </div>

            <!-- Nav Item - Teachers Management Links -->
            <li class="nav-item">
                <a class="nav-link" href="add_adviser.php">
                    <i class="fa fa-chalkboard-teacher"></i>
                    <span>Add Adviser</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="section_student.php">
                    <i class="fa fa-book-reader"></i>
                    <span>Assign Adviser</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Socials
            </div>

            <!-- Social Media Links -->
            <div class="social-links">
                <li class="nav-item">
                    <a class="nav-link facebook-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/" target="_blank">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link youtube-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank">
                        <i class="fab fa-youtube"></i>
                        <span>YouTube</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link google-link" href="https://ph.search.yahoo.com/...your_google_link..." target="_blank">
                        <i class="fab fa-google"></i>
                        <span>Google</span>
                    </a>
                </li>
            </div>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                        <li class="nav-item dropdown no-arrow d-sm-none">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                                <form class="form-inline mr-auto w-100 navbar-search">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($userEmail) ?></span>
                                <img class="img-profile rounded-circle" src="img/profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                        <a href="generate_report.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                        </a>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Total Students Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Students</div>
                                            <h3 class="mb-0 text-gray-800"><?= number_format($totalStudents) ?></h3>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Present Today Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Present Today</div>
                                            <h3 class="mb-0 text-gray-800"><?= number_format($presentToday) ?></h3>
                                            <small class="text-success"><?= $attendanceRate ?>% attendance rate</small>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Absent Today Card -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Absent Today</div>
                                            <h3 class="mb-0 text-gray-800"><?= number_format($absentToday) ?></h3>
                                            <small class="text-danger"><?= $absenceRate ?>% absence rate</small>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-user-times fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Quick Actions Card -->
                        <div class="col-lg-12 mb-4">
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header bg-secondary text-white fw-semibold">Quick Actions</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4 col-sm-6">
                                            <a href="edit_student.php" class="btn btn-primary w-100"><i class="bi bi-person"></i> Edit Student</a>
                                        </div>
                                        <div class="col-md-4 col-sm-6">
                                            <a href="calendar.php" class="btn btn-success w-100"><i class="bi bi-person-calendar"></i> Student Calendar</a>
                                        </div>
                                        <div class="col-md-4 col-sm-6">
                                            <a href="view_attendance.php" class="btn btn-warning text-dark w-100"><i class="bi bi-eye"></i> View Attendance</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Overview Chart -->
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header bg-primary text-white fw-semibold">Attendance Overview</div>
                                <div class="card-body">
                                    <canvas id="attendanceChart" height="150"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php include 'footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="index.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- Custom JavaScript for Chart.js and Sidebar Toggle -->
    <script>
        // Sidebar Toggling
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('accordionSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const contentWrapper = document.getElementById('content-wrapper');

            function toggleSidebar() {
                sidebar.classList.toggle('toggled');
                contentWrapper.classList.toggle('toggled');
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
        });

        // Chart.js Initialization
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart');
            if (ctx) {
                const labels = <?= json_encode(array_keys($attendanceOverview)) ?>;
                const percentages = <?= json_encode(array_map(fn($d) => $d['percentage'], $attendanceOverview)) ?>;

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Attendance %',
                            data: percentages,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            maxBarThickness: 40
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: { callback: value => value + '%' },
                                title: { display: true, text: 'Attendance Percentage' }
                            },
                            x: { title: { display: true, text: 'Day of Week' } }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: ctx => ctx.parsed.y + '% attendance' } }
                        },
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        });
    </script>

</body>
</html>