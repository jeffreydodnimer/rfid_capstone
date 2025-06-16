<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$connect = new mysqli($servername, $username, $password, $dbname);
if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}

// PHPMailer includes
require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

if (isset($_POST['send'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $otp      = trim($_POST['otp']);
    $ip       = $_SERVER['REMOTE_ADDR'];

    // ✅ Server-side form validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        echo "<script>alert('Please fill out all required fields.'); window.history.back();</script>";
        exit;
    }

    // ✅ Optional: Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email address.'); window.history.back();</script>";
        exit;
    }

    // ✅ Optional: Basic phone validation (e.g., numeric & length check)
    if (!preg_match("/^[0-9]{10,15}$/", $phone)) {
        echo "<script>alert('Please enter a valid phone number.'); window.history.back();</script>";
        exit;
    }

    // ✅ Optional: Check password length
    if (strlen($password) < 6) {
        echo "<script>alert('Password must be at least 6 characters long.'); window.history.back();</script>";
        exit;
    }

    // ✅ Optional: Hash password before storing
    // $password = password_hash($password, PASSWORD_DEFAULT);

    // Save to DB
    $sql = "INSERT INTO otp (name, email, phone, password, otp, status, otp_send_time, ip)
            VALUES ('$name', '$email', '$phone', '$password', '$otp', 'pending', NOW(), '$ip')";

    if ($connect->query($sql) === TRUE) {
        $mail = new PHPMailer(true);
        try {
            // Mailer setup
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ronroncapulong@gmail.com';
            $mail->Password = 'kqcv wrop mitj zuak';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Email content
            $mail->setFrom('ronroncapulong@gmail.com', 'Senzuwi');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Received OTP";
            $mail->Body = "Hello <b>$name</b>,<br>Your OTP code is: <strong>$otp</strong><br><br>Please use it to verify your account.";

            $mail->send();
            echo "<script>alert('Verification Code Sent To Your Email Successfully!'); location.href='admin_verify.php';</script>";
        } catch (Exception $e) {
            echo "<script>alert('Mailer Error: {$mail->ErrorInfo}'); location.href='admin_register.php';</script>";
        }
    } else {
        echo "<script>alert('Database Error: {$connect->error}'); location.href='admin_register.php';</script>";
    }
}
?>
