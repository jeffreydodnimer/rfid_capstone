<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

// PHPMailer includes (ensure this matches your folder structure)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Send OTP when email is submitted
if (isset($_POST['send_otp'])) {
    $email = $conn->real_escape_string($_POST['email']);

    // Check if email exists in the database
    $check = "SELECT * FROM otp WHERE email='$email'";
    $result = $conn->query($check);

    if ($result->num_rows == 1) {
        // Email exists, generate OTP
        $otp = rand(100000, 999999);

        // Fetch the user's name from the database (assuming a 'name' field exists)
        $row = $result->fetch_assoc();
        $name = $row['name'];

        // Save OTP to session
        $_SESSION['otp'] = $otp;
        $_SESSION['email'] = $email;

        // Update the forgotpass_otp column with the new OTP
        $update_otp = "UPDATE otp SET forgotpass_otp = '$otp', otp_send_time_forgotpass = NOW() WHERE email = '$email'";

        if ($conn->query($update_otp) === TRUE) {
            // Send OTP via email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'ronroncapulong@gmail.com';  // Your Gmail account
                $mail->Password = 'kqcv wrop mitj zuak';  // App password (not your real password)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                // Recipients
                $mail->setFrom('ronroncapulong@gmail.com', 'Senzuwi');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = "Received OTP for Password Reset";
                $mail->Body = "Hello <b>$name</b>,<br>Your Forgot Password OTP code is: <strong>$otp</strong><br><br>Please use it to reset your password.";

                // Send the email
                $mail->send();
                echo "<script>alert('Verification Code Sent To Your Email Successfully. Check it!'); location.href='admin_verify_forgotpass.php';</script>";
            } catch (Exception $e) {
                echo "<script>alert('Mailer Error: {$mail->ErrorInfo}'); location.href='admin_forgotpass.php';</script>";
            }
        } else {
            echo "<script>alert('Error updating OTP: {$conn->error}'); location.href='admin_forgotpass.php';</script>";
        }
    } else {
        echo "<script>alert('Email not found in database!'); location.href='admin_forgotpass.php';</script>";
    }
}
?>


<!-- Send OTP Form -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - Send OTP</title>
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
    .forgot-box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      width: 400px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
  </style>
</head>
<body>

<div class="forgot-box">
  <h2 class="text-center mb-4">Forgot Password</h2>
  <form action="" method="POST">
    <input type="email" name="email" class="form-control mb-3" placeholder="Enter your Email" required>
    <button type="submit" name="send_otp" class="btn btn-primary w-100">Send OTP</button>
  </form>
  <div class="text-center mt-3">
    <p>Back to <a href="admin_login.php">Login</a></p>
  </div>
</div>

</body>
</html>