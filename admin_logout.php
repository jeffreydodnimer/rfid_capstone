<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone"; // Your database name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if email exists in session
if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];

    // Update status to 'inactive' when logging out
    $updateStatus = "UPDATE otp SET status='inactive' WHERE email='$email'";
    $conn->query($updateStatus);

    // Destroy the session to log the user out
    session_destroy();
}

// Redirect to login page after logout
header('Location:admin_login.php');
exit();
?>
