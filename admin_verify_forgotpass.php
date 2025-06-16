<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

if (!isset($_SESSION['email']) || !isset($_SESSION['otp'])) {
    echo "
    <script>
        alert('Session expired. Please request OTP again.');
        window.location.href='admin_forgot_pass.php';
    </script>
    ";
    exit();
}

// Verify OTP
if (isset($_POST['verify_otp'])) {
    $entered_otp = $_POST['otp'];

    if ($entered_otp == $_SESSION['otp']) {
        $_SESSION['otp_verified'] = true;
    } else {
        echo "
        <script>
            alert('Invalid OTP. Please try again.');
            window.location.href='admin_verify_forgotpass.php';
        </script>
        ";
        exit();
    }
}

// Change Password
if (isset($_POST['change_password'])) {
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        echo "
        <script>
            alert('Unauthorized access. Verify OTP first.');
            window.location.href='admin_forgotpass.php';
        </script>
        ";
        exit();
    }

    $new_password = $conn->real_escape_string($_POST['new_password']);
    $confirm_password = $conn->real_escape_string($_POST['confirm_password']);
    $email = $_SESSION['email'];

    if ($new_password != $confirm_password) {
        echo "
        <script>
            alert('Passwords do not match!');
            window.location.href='admin_verify_forgotpass.php';
        </script>
        ";
        exit();
    }

    $update = "UPDATE otp SET password='$new_password' WHERE email='$email'";
    if ($conn->query($update) === TRUE) {
        session_unset();
        session_destroy();
        echo "
        <script>
            alert('Password changed successfully! Please login.');
            window.location.href='admin_login.php';
        </script>
        ";
    } else {
        echo "
        <script>
            alert('Error updating password.');
            window.location.href='admin_verify_forgotpass.php';
        </script>
        ";
    }
}
?>

<!-- OTP Verification + Change Password Form -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify OTP</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-image: url('img/cover.jpg');
      background-size: cover;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .verify-box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      width: 400px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
  </style>
</head>
<body>

<div class="verify-box">
  <h2 class="text-center mb-4">Verify OTP</h2>

  <?php if (!isset($_SESSION['otp_verified'])) { ?>
    <!-- Enter OTP Form -->
    <form action="" method="POST">
      <input type="text" name="otp" class="form-control mb-3" placeholder="Enter OTP" required>
      <button type="submit" name="verify_otp" class="btn btn-primary w-100">Verify OTP</button>
    </form>
  <?php } else { ?>
    <!-- Change Password Form -->
    <form action="" method="POST">
      <input type="password" name="new_password" class="form-control mb-3" placeholder="Enter New Password" required>
      <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm New Password" required>
      <button type="submit" name="change_password" class="btn btn-success w-100">Change Password</button>
    </form>
  <?php } ?>

  <div class="text-center mt-3">
    <p>Back to <a href="admin_login.php">Login</a></p>
  </div>
</div>

</body>
</html>