<?php
// --- AJAX ENDPOINT FOR REAL-TIME DASHBOARD STATS ---
// This section handles requests for dynamic data updates without full page reloads.
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json'); // Set header for JSON response

    include 'conn.php'; // Include database connection
    // Check if connection failed
    if (!isset($conn) || $conn->connect_error) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    // Set timezone and get current date/school year for accurate data fetching
    date_default_timezone_set('Asia/Manila');
    $current_date = date('Y-m-d');
    $currentMonth = (int)date('m');
    $currentYear = (int)date('Y');
    // Determine the current school year (e.g., 2024-2025)
    if ($currentMonth >= 8) { // Assuming school year starts in August
        $current_school_year = $currentYear . '-' . ($currentYear + 1);
    } else {
        $current_school_year = ($currentYear - 1) . '-' . $currentYear;
    }

    // 1. Fetch total enrolled students for the current school year
    $totalStudents = 0;
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT lrn) AS total_students FROM enrollments WHERE school_year = ?");
    $stmt->bind_param("s", $current_school_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $totalStudents = $result->fetch_assoc()['total_students'] ?? 0; // Use 0 if no results
    }
    $stmt->close();

    // 2. Fetch number of students present today (including late)
    $presentToday = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) AS present_today FROM attendance WHERE date = ? AND status IN ('present', 'late')");
    $stmt->bind_param("s", $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $presentToday = $result->fetch_assoc()['present_today'] ?? 0; // Use 0 if no results
    }
    $stmt->close();

    // 3. Calculate absent students and attendance/absence rates
    $absentToday = $totalStudents - $presentToday;
    // Avoid division by zero if there are no students
    $attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
    $absenceRate = $totalStudents > 0 ? round(($absentToday / $totalStudents) * 100, 1) : 0;

    // 4. Prepare data for the weekly attendance chart
    // Define weekdays for which we want data
    $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $attendanceOverview = [];
    // Initialize attendance data for each weekday
    foreach ($weekdays as $day) {
        $attendanceOverview[$day] = ['present' => 0, 'percentage' => 0];
    }
    // Fetch actual attendance data for the current week
    // YEARWEEK(date, 1) calculates week number starting Monday
    $stmt = $conn->prepare("SELECT DAYNAME(date) AS day_name, COUNT(*) AS present_count FROM attendance WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('present', 'late') GROUP BY day_name");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Only process data for the defined weekdays
            if (in_array($row['day_name'], $weekdays)) {
                $presentCount = (int)$row['present_count'];
                // Calculate percentage based on total students
                $percent = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 1) : 0;
                $attendanceOverview[$row['day_name']]['percentage'] = $percent; // Store percentage
            }
        }
    }
    $stmt->close();

    // 5. Build the JSON response object
    $response = [
        'totalStudents' => number_format($totalStudents), // Formatted for display
        'presentToday' => number_format($presentToday),   // Formatted for display
        'absentToday' => number_format($absentToday),     // Formatted for display
        'attendanceRate' => $attendanceRate,
        'absenceRate' => $absenceRate,
        'chartData' => [ // Data structured for Chart.js
            'labels' => array_keys($attendanceOverview), // Days of the week
            'percentages' => array_values(array_column($attendanceOverview, 'percentage')) // Attendance percentages
        ]
    ];

    echo json_encode($response); // Send the JSON data back to the client
    $conn->close(); // Close database connection
    exit(); // Stop script execution after AJAX response
}

// --- FULL PAGE LOAD LOGIC STARTS HERE ---
// This part executes when the admin_dashboard.php page is loaded normally (not via AJAX).
session_start(); // Resume session to access logged-in user information

// Redirect to login page if the user is not logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}
$userEmail = $_SESSION['email']; // Get the logged-in user's email

include 'conn.php'; // Include database connection
// Check if connection failed
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error")); // Die with error message if connection fails
}

// Set timezone and get current date/school year
date_default_timezone_set('Asia/Manila');
$current_date = date('Y-m-d');

$currentMonth = (int)date('m');
$currentYear = (int)date('Y');
// Determine the current school year
if ($currentMonth >= 8) {
    $current_school_year = $currentYear . '-' . ($currentYear + 1);
} else {
    $current_school_year = ($currentYear - 1) . '-' . $currentYear;
}

// 1. Fetch total students for the current school year (same logic as AJAX endpoint)
$totalStudents = 0;
$stmt = $conn->prepare("SELECT COUNT(DISTINCT lrn) AS total_students FROM enrollments WHERE school_year = ?");
$stmt->bind_param("s", $current_school_year);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $totalStudents = $result->fetch_assoc()['total_students'] ?? 0;
}
$stmt->close();

// 2. Fetch present students today (same logic as AJAX endpoint)
$presentToday = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS present_today FROM attendance WHERE date = ? AND status IN ('present', 'late')");
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $presentToday = $result->fetch_assoc()['present_today'] ?? 0;
}
$stmt->close();

// Calculate absent and rates
$absentToday = $totalStudents - $presentToday;
$attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
$absenceRate = $totalStudents > 0 ? round(($absentToday / $totalStudents) * 100, 1) : 0;

// Prepare data for the attendance overview chart (same logic as AJAX endpoint)
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$attendanceOverview = [];
foreach ($weekdays as $day) {
    $attendanceOverview[$day] = ['present' => 0, 'percentage' => 0];
}
$stmt = $conn->prepare("SELECT DAYNAME(date) AS day_name, COUNT(*) AS present_count FROM attendance WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('present', 'late') GROUP BY day_name");
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (in_array($row['day_name'], $weekdays)) {
            $presentCount = (int)$row['present_count'];
            $percent = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 1) : 0;
            $attendanceOverview[$row['day_name']]['percentage'] = $percent;
        }
    }
}
$stmt->close();
$conn->close(); // Close connection after fetching initial data
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard</title>
    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-weight: 700 !important; }
        /* Sidebar transition styles */
        #accordionSidebar { transition: width 0.3s ease-in-out, margin 0.3s ease-in-out; background-color: rgb(7, 29, 230); }
        #accordionSidebar.toggled { width: 0 !important; overflow: hidden; margin-left: -225px; }
        #content-wrapper.toggled { margin-left: 0; }
        #content-wrapper { transition: margin 0.3s ease-in-out; }
        /* Custom button styles */
        .btn-maroon { color: #fff; background-color: #800000; border-color: #800000; }
        .btn-maroon:hover { color: #fff; background-color: #660000; border-color: #590000; }
        .nav-item .nav-link i { margin-right: 0.5rem; }
        .topbar .navbar-nav .nav-item .nav-link .img-profile { height: 2rem; width: 2rem; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <!-- Sidebar Brand -->
            <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center" href="admin_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
                <img src="img/logo.jpg" alt="School Logo" class="rounded-circle mb-2" style="width: 85px; height: 85px; object-fit: cover;">
                <div class="sidebar-brand-text" style="font-weight: 500; font-size: 1rem;">ADMIN</div>
            </a>
            <hr class="sidebar-divider my-0">
            <!-- Navigation Items -->
            <li class="nav-item active">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Student Management</div>
            <li class="nav-item"><a class="nav-link" href="students_list.php"><i class="fa fa-user-graduate"></i><span>Add Student</span></a></li>
            <li class="nav-item"><a class="nav-link" href="guardian.php"><i class="fa fa-house-user"></i><span>Guardian Of Students</span></a></li>
            <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fa fa-pen-alt"></i><span>Enroll Student</span></a></li>
            <li class="nav-item"><a class="nav-link" href="link_rfid.php"><i class="fa fa-credit-card"></i><span>Link RFID</span></a></li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Teachers Management</div>
            <li class="nav-item"><a class="nav-link" href="add_adviser.php"><i class="fa fa-chalkboard-teacher"></i><span>Add Adviser</span></a></li>
            <li class="nav-item"><a class="nav-link" href="section_student.php"><i class="fa fa-book-reader"></i><span>Assign Adviser</span></a></li>
            <hr class="sidebar-divider d-none d-md-block">
        </ul>
        <!-- End of Sidebar -->
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (Mobile) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <div class="topbar-divider d-none d-sm-block"></div>
                        <!-- User Profile -->
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($userEmail) ?></span>
                                <img class="img-profile rounded-circle" src="img/profile.svg">
                            </a>
                        </li>
                        <!-- Logout Button -->
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-toggle="modal" data-target="#logoutModal">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                <span class="d-none d-lg-inline text-gray-600 small">Logout</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->
                
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                        <!-- Report Generation Button -->
                        <a href="generate_report.php" class="d-none d-sm-inline-block btn btn-sm btn-maroon shadow-sm">
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
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Enrolled Students</div>
                                            <!-- Dynamically updated student count -->
                                            <h3 class="mb-0 text-gray-800" id="totalStudentsCount"><?= number_format($totalStudents) ?></h3>
                                            <small class="text-muted">For SY <?= htmlspecialchars($current_school_year) ?></small>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-user-graduate fa-2x text-danger"></i></div>
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
                                            <!-- Dynamically updated present count -->
                                            <h3 class="mb-0 text-gray-800" id="presentTodayCount"><?= number_format($presentToday) ?></h3>
                                            <!-- Dynamically updated attendance rate -->
                                            <small class="text-success" id="attendanceRate"><?= $attendanceRate ?>% attendance rate</small>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-user-check fa-2x text-success"></i></div>
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
                                            <!-- Dynamically updated absent count -->
                                            <h3 class="mb-0 text-gray-800" id="absentTodayCount"><?= number_format($absentToday) ?></h3>
                                            <!-- Dynamically updated absence rate -->
                                            <small class="text-danger" id="absenceRate"><?= $absenceRate ?>% absence rate</small>
                                        </div>
                                        <div class="col-auto"><i class="fas fa-user-times fa-2x text-warning"></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">
                        <div class="col-lg-12 mb-4">
                            <!-- Quick Actions Card -->
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header bg-secondary text-white fw-semibold">Quick Actions</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4 col-sm-6"><a href="attendance_time.php" class="btn btn-primary w-100"><i class="bi bi-clock"></i> Attendance Time Setting</a></div>
                                        <div class="col-md-4 col-sm-6"><a href="student_calendar.php" class="btn btn-success w-100"><i class="bi bi-calendar"></i> Student Calendar</a></div>
                                        <div class="col-md-4 col-sm-6"><a href="view_attendance.php" class="btn btn-warning text-dark w-100"><i class="bi bi-eye"></i> View Attendance</a></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Weekly Attendance Overview Card -->
                            <div class="card mb-4 shadow-sm">
                                <div class="card-header bg-primary text-white fw-semibold">Weekly Attendance Overview</div>
                                <div class="card-body"><canvas id="attendanceChart" height="150"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Footer -->
            <?php include 'footer.php'; ?>
        </div>
    </div>
    
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
                    <a class="btn btn-danger" href="index.php">Logout</a> <!-- Assumes index.php handles logout -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <script>
        // Global variable to hold the chart instance so we can update it later
        let attendanceChart = null;

        document.addEventListener('DOMContentLoaded', function() {
            // --- Chart.js Initialization ---
            const ctx = document.getElementById('attendanceChart');
            if (ctx) {
                // Use PHP variables to get initial chart data
                const initialLabels = <?= json_encode(array_keys($attendanceOverview)) ?>;
                const initialData = <?= json_encode(array_values(array_column($attendanceOverview, 'percentage'))) ?>;

                // Create the bar chart instance
                attendanceChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: initialLabels, // Days of the week
                        datasets: [{
                            label: 'Attendance %', // Legend for the dataset
                            data: initialData,      // Attendance percentages
                            backgroundColor: 'rgba(13, 110, 253, 0.7)', // Color for bars
                            borderColor: 'rgba(13, 110, 253, 1)',     // Border color for bars
                            borderWidth: 1,
                            borderRadius: 4,        // Rounded corners for bars
                            maxBarThickness: 40     // Max width of bars
                        }]
                    },
                    options: {
                        scales: {
                            y: { // Y-axis configuration
                                beginAtZero: true, // Start at 0
                                max: 100,          // Max value is 100%
                                ticks: { 
                                    callback: value => value + '%' // Append '%' to tick labels
                                } 
                            },
                            x: { // X-axis configuration
                                title: { 
                                    display: true, 
                                    text: 'Day of Week' 
                                }
                            }
                        },
                        plugins: { legend: { display: false } }, // Hide legend as only one dataset
                        responsive: true,        // Make chart responsive
                        maintainAspectRatio: false // Allow chart to adjust aspect ratio
                    }
                });
            }

            // --- Real-time Dashboard Stats Update Function ---
            // Get references to the DOM elements that display the stats
            const totalStudentsEl = document.getElementById('totalStudentsCount');
            const presentTodayEl = document.getElementById('presentTodayCount');
            const attendanceRateEl = document.getElementById('attendanceRate');
            const absentTodayEl = document.getElementById('absentTodayCount');
            const absenceRateEl = document.getElementById('absenceRate');
            
            async function updateDashboardStats() {
                try {
                    // Fetch the latest data from our AJAX endpoint defined at the top of this PHP file
                    const response = await fetch('admin_dashboard.php?action=get_stats');
                    // Check if the network request was successful
                    if (!response.ok) {
                        console.error('Failed to fetch dashboard stats.');
                        return; // Stop if fetch failed
                    }
                    // Parse the JSON response
                    const data = await response.json();

                    // Update the text content of the summary card elements
                    totalStudentsEl.textContent = data.totalStudents;
                    presentTodayEl.textContent = data.presentToday;
                    absentTodayEl.textContent = data.absentToday;
                    attendanceRateEl.textContent = `${data.attendanceRate}% attendance rate`;
                    absenceRateEl.textContent = `${data.absenceRate}% absence rate`;

                    // Update the chart data and redraw the chart if it exists
                    if (attendanceChart && data.chartData) {
                        attendanceChart.data.labels = data.chartData.labels; // Update chart labels (days)
                        attendanceChart.data.datasets[0].data = data.chartData.percentages; // Update chart data (percentages)
                        attendanceChart.update(); // Redraw the chart with new data
                    }

                } catch (error) {
                    console.error('Error updating dashboard stats:', error); // Log any errors during fetch or update
                }
            }

            // Set an interval to call updateDashboardStats every 3000 milliseconds (3 seconds)
            // This provides near real-time updates for the dashboard statistics.
            setInterval(updateDashboardStats, 3000);
        });
    </script>
</body>
</html>