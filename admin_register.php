<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Email Verification</title>

    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background: url('img/cover.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1;
        }

        .form-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 450px;
            padding: 25px;
            margin: auto;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            border: 1px solid #ddd;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease-in-out;
            top: 50%;
            transform: translateY(-50%);
        }

        .form-container:hover {
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        h5 {
            text-align: center;
            margin-bottom: 25px;
            color: #343a40;
            border: 2px solid gray;
            border-radius: 10px;
            padding: 5px 10px;
            display: inline-block;
            background-color: #f8f9fa;
        }

        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }

        .form-control {
            border-left: none;
        }

        .input-group .form-control:focus {
            box-shadow: none;
        }

        .otp-group {
            display: flex;
            gap: 10px;
        }

        .otp-group .btn {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="form-container text-center">
        <!-- Logo -->
        <img src="img/logo.jpg" alt="Logo" class="logo mx-auto d-block">
        
        <!-- Form Heading -->
        <h5>REGISTER FORM</h5>

        <form action="admin_send.php" method="post">
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-user"></i></span>
                <input type="text" name="name" id="name" class="form-control" placeholder="Enter Your Name" autocomplete="off">
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-phone"></i></span>
                <input type="text" name="phone" id="phone" class="form-control" placeholder="Enter Your Phone" autocomplete="off">
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                <input type="text" name="email" id="email" class="form-control" placeholder="Enter Your Email" autocomplete="off">
            </div>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="fa fa-lock"></i></span>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter Your Password" autocomplete="off">
            </div>
            <div class="mb-3 otp-group">
                <input type="hidden" name="otp" id="otp" class="form-control">
                <input type="hidden" name="subject" id="subject" class="form-control" value="Recieved OTP">
            </div>
            <button type="submit" name="send" class="btn btn-primary w-100">Signup <i class="fa fa-arrow-right"></i></button>
            <div class="text-center mt-3">
                <a href="admin_login.php" class="text-decoration-none">Back to Login</a>
            </div>
        </form>
    </div>

    <script>
        function generateRandomNumber(){
            let min = 100000;
            let max = 999999;
            let randomNumber = Math.floor(Math.random() * (max - min + 1)) + min;
            let lastGenerateNumber = localStorage.getItem('lastGenerateNumber');
            while(randomNumber === parseInt(lastGenerateNumber)){
                randomNumber = Math.floor(Math.random() * (max - min + 1)) + min;
            }
            localStorage.setItem('lastGenerateNumber', randomNumber);
            return randomNumber;
        }
        document.getElementById('otp').value = generateRandomNumber();
    </script>
</body>
</html>
