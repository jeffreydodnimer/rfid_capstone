<?php
include 'conn.php';

if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

$email = $_SESSION['email'];
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS (adjust path as needed) -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
</head>
<body id="page-top">
<div id="wrapper">

    <!-- Sidebar -->
    <ul class="navbar-nav bg-bg-maroon sidebar sidebar-dark accordion" id="accordionSidebar" style="background-color:rgb(7, 29, 230);">

        <!-- Sidebar - Brand -->
        <a class="sidebar-brand d-flex align-items-center justify-content-center">
            <div class="d-flex align-items-center mb-3">
                <img src="img/logo.jpg" alt="Admin Profile Picture" class="rounded-circle" 
                     style="width: 95px; height: 95px; object-fit: fit; border-radius: 50%; padding: 5px; margin-top: 70px;">
            </div>
            <div class="strong">
            <div class="sidebar-brand-text mx-3" style="margin-top: 30px">Admin <sup></sup></div>
        </a>

        <!-- Divider -->
        <hr class="sidebar-divider my-0">

        <!-- Nav Item - Dashboard -->
        <li class="nav-item active">
            <a class="nav-link" href="admin_dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span></a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Student Management
        </div>

        <!-- Nav Item - Pages Collapse Menu -->
        <li class="nav-item" >
            <a class="nav-link" href="students_list.php">
                <i class="fa fa-user-graduate" ></i>
                <span >Add Student</span></a>
        </li> 

        <li class="nav-item">
            <a class="nav-link" href="guardian.php">
                <i class="fa fa-house-user"></i>
                <span>Guardian Of Students</span>
            </a>
        </li>

        <li class="nav-item" >
            <a class="nav-link" href="enrollment.php">
                <i class="fa fa-pen-alt" ></i>
                <span >Enroll Student</span></a>
        </li>

        <li class="nav-item" >
            <a class="nav-link" href="link_rfid.php">
                <i class="fa fa-credit-card" ></i>
                <span >Link RFID</span></a>
        </li>
        <hr class="sidebar-divider">

        <!-- Heading -->
        <div class="sidebar-heading">
            Teachers Management
        </div>

        <li class="nav-item" >
            <a class="nav-link" href="add_adviser.php">
                <i class="fa fa-chalkboard-teacher" ></i>
                <span >Add Adviser</span></a>
        </li>
        
        <li class="nav-item" >
            <a class="nav-link" href="section_student.php">
                <i class="fa fa-book-reader" ></i>
                <span >Assign Adviser</span></a>
        </li>

         <hr class="sidebar-divider">

         <div class="sidebar-heading">
                Socials
            </div>

            <!-- Social Media Links - UPDATED TO FACEBOOK, YOUTUBE, GOOGLE -->
            <div class="social-links">
                <li class="nav-item">
                    <a class="nav-link facebook-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank">
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
                    <a class="nav-link google-link" href="https://ph.search.yahoo.com/search;_ylt=Awrx.tEep2doLwIAgj6zRwx.;_ylc=X1MDMjExNDczNDAwMwRfcgMyBGZyA21j
                    YWZlZQRmcjIDc2ItdG9wBGdwcmlkAwRuX3JzbHQDMARuX3N1Z2cDMARvcmlnaW4DcGguc2VhcmNoLnlhaG9vLmNvbQRwb3MDMARwcXN0cgMEcHFzdHJsAzAEcXN0cmwDNTEEcXVlcnkD
                    c2FuJTIwaXNpZHJvJTIwbmF0aW9uYWwlMjBoaWdoJTIwc2Nob29sJTIwcGFkcmUlMjBidXJnb3MlMjBxdWV6b24EdF9zdG1wAzE3NTE2MjM0NzM-?p=san+isidro+national+high+
                    school+padre+burgos+quezon&fr=mcafee&type=E211PH1589G0&fr2=sb-top" target="_blank">
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
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>

                <!-- Topbar Navbar -->
                <ul class="navbar-nav ml-auto">

                    <!-- Nav Item - Search Dropdown (Visible Only XS) -->
                    <li class="nav-item dropdown no-arrow d-sm-none">
                        <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-search fa-fw"></i>
                        </a>
                        <!-- Dropdown - Messages -->
                        <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                            aria-labelledby="searchDropdown">
                            <form class="form-inline mr-auto w-100 navbar-search">
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light border-0 small"
                                        placeholder="Search for..." aria-label="Search"
                                        aria-describedby="basic-addon2">
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

                    <!-- User Email Display (non-clickable) -->
                    <li class="nav-item">
                        <span class="nav-link">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </span>
                            <img class="img-profile rounded-circle" src="img/profile.svg">
                        </span>
                    </li>
                    

                </ul>
            </nav>
            <!-- End of Topbar -->