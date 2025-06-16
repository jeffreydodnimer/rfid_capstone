<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location:admin_login.php');
    exit();
}
$userEmail = $_SESSION['email'];

include 'conn.php';

$sqlStudents = "SELECT COUNT(*) AS total_students FROM students";
$resultStudents = $conn->query($sqlStudents);
$totalStudents = ($resultStudents && $row = $resultStudents->fetch_assoc()) ? $row['total_students'] : 0;
$totalStudents = 500;

$today = date('Y-m-d');
$sqlPresentToday = "SELECT COUNT(*) AS present_today FROM attendance WHERE date = '$today' AND status = 'present'";
$resultPresent = $conn->query($sqlPresentToday);
$presentToday = ($resultPresent && $row = $resultPresent->fetch_assoc()) ? $row['present_today'] : 0;

$absentToday = $totalStudents - $presentToday;
$attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
$absenceRate = 100 - $attendanceRate;

$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$attendanceOverview = [];

foreach ($weekdays as $day) {
    $sqlDate = "SELECT MAX(date) AS last_date FROM attendance WHERE DAYNAME(date) = '$day'";
    $resDate = $conn->query($sqlDate);
    $lastDate = ($resDate && $row = $resDate->fetch_assoc()) ? $row['last_date'] : null;

    if ($lastDate) {
        $sqlPresent = "SELECT COUNT(*) AS present_count FROM attendance WHERE date = '$lastDate' AND status = 'present'";
        $resPresent = $conn->query($sqlPresent);
        $presentCount = ($resPresent && $row = $resPresent->fetch_assoc()) ? $row['present_count'] : 0;
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
    <meta name="description" content="">
    <meta name="author" content="">
    <title>Admin Dashboard</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body id="page-top">

<?php 
    include 'nav.php';
?>

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                                class="fas fa-download fa-sm text-white-50"></i> Generate Report</a>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Earnings (Monthly) Card Example -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Students</div>
                                            <h3><?= number_format($totalStudents) ?></h3>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Earnings (Monthly) Card Example -->
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Present Today
                                            </div>
                                            <h3><?= number_format($presentToday) ?></h3>
                                            <small class="text-success"><?= $attendanceRate ?>% attendance rate</small>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Absent Today</div>
                                            <h3><?= number_format($absentToday) ?></h3>
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

                        <!-- Content Column -->
                        <div class="col-lg-12 mb-4">
                        <!-- Project Card Example -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-secondary text-white fw-semibold">Quick Actions</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 col-sm-6">
                                        <a href="edit_student.php" class="btn btn-primary w-100"><i class="bi bi-person"></i> Edit Student</a>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <a href="edit_advisor.php" class="btn btn-success w-100"><i class="bi bi-person-badge"></i> Edit Advisor</a>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <a href="send_notification_advisors.php" class="btn btn-warning text-dark w-100"><i class="bi bi-envelope"></i> Notify Advisors</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-primary text-white fw-semibold">Attendance Overview</div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="150" style="display: block; box-sizing: border-box; height: 150px; width: 442px;" width="442"></canvas>
                            </div>
                        </div>
                

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Your Website 2021</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->
        </div>
    </div>   

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
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

        <!-- Bootstrap core JavaScript-->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="vendor/chart.js/Chart.min.js"></script>

    <!-- Page level custom scripts -->
    <script src="js/demo/chart-area-demo.js"></script>
    <script src="js/demo/chart-pie-demo.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const sidebar = document.getElementById('sidebar');
    const btnToggle = document.getElementById('btnToggleSidebar');
    btnToggle.addEventListener('click', () => {
      sidebar.classList.toggle('active');
    });

    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const labels = <?= json_encode(array_keys($attendanceOverview)) ?>;
    const percentages = <?= json_encode(array_map(fn($d) => $d['percentage'], $attendanceOverview)) ?>;

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
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
            ticks: {
              callback: value => value + '%'
            },
            title: {
              display: true,
              text: 'Attendance %'
            }
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ctx.parsed.y + '% attendance'
            }
          }
        },
        responsive: true,
        maintainAspectRatio: false
      }
    });
  </script>

</body>