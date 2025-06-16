<!DOCTYPE html>
<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$connect = new mysqli($servername, $username, $password, $dbname);

$email = "";
$stored_otp = "";
$message = "";

$ip_address = $_SERVER['REMOTE_ADDR'];

$sql = "SELECT email, otp FROM otp WHERE ip = '$ip_address' AND status = 'pending'
ORDER BY otp_send_time DESC";

$result = $connect->query($sql);

if($result->num_rows > 0){
    $row = $result->fetch_assoc();
    $email = $row['otp'];
}
else{
    $message = "no pending OTP with this email.";

    if(isset($_POST['verify'])){
        $entered_otp = $_POST['otp'];

        if($entered_otp === $stored_otp){
            $sql_update = "UPDATE otp SET status = 'verified' WHERE email = $email AND ip = '$ip_address'";

            if($connect->query($sql_update === true)){
                
            }
            $message = "Email Verified Successfully";
            header("location:admin_success.php");
            exit();
        } else{
            $message = "error updating OTP Status" . $connect->error;
        }
    } else{
        $message = "Invalid OTP. Please try again!";
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <style>
        body, html {
            height: 100%;
            margin: 0;
        }

        body {
            background: url('img/cover.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0;
            height: 100%; width: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .form-container {
            position: relative;
            z-index: 2;
            max-width: 450px;
            margin: auto;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        h5 {
            text-align: center;
            margin-bottom: 25px;
            color: #343a40;
            border: 2px solid gray;
            border-radius: 10px;
            padding: 5px 10px;
            background-color: #f8f9fa;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }

        .form-control {
            border-left: none;
        }
    </style>
</head>
<body>

<?php
// Mock Data for testing, replace with session or POST data
$email = isset($_GET['email']) ? $_GET['email'] : 'ronroncapulong@gmail.com';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<div class="form-container">
    <h5>VERIFY OTP</h5>
    <div class="alert alert-info" role="alert">
        Your email is: <strong><?php echo htmlspecialchars($email); ?></strong>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="admin_success.php" method="POST">
        <div class="mb-3 input-group">
            <span class="input-group-text"><i class="fa fa-key"></i></span>
            <input type="text" name="otp" class="form-control" placeholder="Enter Your OTP" required>
        </div>
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <button type="submit" name="verify" class="btn btn-primary w-100">Verify OTP <i class="fa fa-arrow-right"></i></button>
    </form>
</div>

</body>
</html>