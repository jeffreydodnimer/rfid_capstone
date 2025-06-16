<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Appointment Request</title>

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    body {
      background-color: #f0f2f5;
      background-image: url('img/cover.jpg');
      background-repeat: no-repeat;
      background-size: cover;
      background-position: center;
      height: 100vh;
      margin: 0;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .container {
      text-align: center;
      background-color: rgba(255, 255, 255, 0.95);
      padding: 40px 30px;
      border-radius: 15px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      max-width: 400px;
      width: 90%;
    }

    .success-animation {
      animation: fadeInUp 0.5s ease forwards;
    }

    @keyframes fadeInUp {
      0% {
        opacity: 0;
        transform: translateY(20px);
      }
      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .checkmark {
      width: 80px;
      height: 80px;
      display: block;
      stroke-width: 2;
      stroke: #28a745;
      fill: none;
      margin: 0 auto 20px;
    }

    .checkmark-circle {
      stroke-dasharray: 166;
      stroke-dashoffset: 166;
      animation: strokeCircle 0.6s ease forwards;
    }

    .checkmark-check {
      stroke-dasharray: 48;
      stroke-dashoffset: 48;
      animation: drawCheck 0.6s ease forwards;
    }

    @keyframes strokeCircle {
      to {
        stroke-dashoffset: 0;
      }
    }

    @keyframes drawCheck {
      to {
        stroke-dashoffset: 0;
      }
    }

    .success-message {
      font-family: Arial, sans-serif;
      font-size: 18px;
      color: #333;
      margin-bottom: 20px;
    }

    .login-link {
      display: inline-block;
      padding: 12px 24px;
      background: linear-gradient(145deg, #e0e0e0, #c0c0c0);
      color: #333;
      text-decoration: none;
      font-size: 16px;
      font-family: Arial, sans-serif;
      border-radius: 25px;
      border: 1px solid #b0b0b0;
      transition: background 0.3s ease, transform 0.3s ease;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .login-link:hover {
      background: linear-gradient(145deg, #c8c8c8, #a8a8a8);
      transform: translateY(-2px);
      box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
    }

    .login-link i {
      margin-left: 8px;
      transition: transform 0.3s ease;
    }

    .login-link:hover i {
      transform: translateX(5px);
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="success-animation">
      <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
        <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
      </svg>
      <p class="success-message">Email Verification Process Completed</p>
      <a href="admin_login.php" class="login-link">Click Here to Login 
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>
  </div>
</body>
</html>
