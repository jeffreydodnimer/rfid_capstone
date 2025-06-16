<?php
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: admin_login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rfid_capstone";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Profile</title>

    <!-- Fonts and CSS -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,700,800,900"
        rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body id="page-top">
    <?php include 'nav.php'; ?>

    <div class="container mt-4">
        <div class="col-lg-10 col-md-12 col-sm-12">
            <h1>Admin Profile</h1>
            <div class="card shadow-sm border-light p-3" style="border-radius: 15px;">
                <div class="card-body p-8" style="box-shadow: 2px 6px 10px">
                    <!-- Profile Picture and Name -->
                    <div class="d-flex align-items-center mb-3">
                        <img src="img/you.png" alt="Profile Picture" class="rounded-circle"
                             style="width: 80px; height: 80px; object-fit: cover;">
                        <div class="ms-3">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars(explode('@', $user['email'])[0]); ?>
                            </h5>
                            <p class="card-text text-muted">
                                <?php echo ucfirst($user['role']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="mb-3">
                        <h6 class="text-primary">Contact Information</h6>
                        <ul class="list-unstyled">
                            <li><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></li>
                            <li><strong>Phone:</strong> Not Available</li> <!-- Optional if you add phone column -->
                        </ul>
                    </div>

                    <!-- Role -->
                    <div class="mb-3">
                        <h6 class="text-primary">Position/Role</h6>
                        <p class="card-text"><?php echo ucfirst($user['role']); ?></p>
                    </div>

                    <!-- Login Credentials -->
                    <div class="mb-3">
                        <h6 class="text-primary">Login Credentials</h6>
                        <ul class="list-unstyled">
                            <li><strong>Username:</strong> <?php echo htmlspecialchars(explode('@', $user['email'])[0]); ?></li>
                            <li><strong>Password:</strong> **********</li> <!-- Masked for security -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap (optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
