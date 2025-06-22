<?php
session_start();

include 'conn.php';

if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $input_password = $conn->real_escape_string($_POST['password']);

    // Fetch user by email
    $sql = "SELECT * FROM users WHERE email='$email' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Check password (plain text for now; use password_verify if hashed)
        if ($input_password === $user['password']) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            echo "<script>
                alert('Login Successful!');
                window.location.href = '" . ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'faculty_dashboard.php') . "';
            </script>";
            exit(); // ✅ THIS STOPS THE PAGE FROM CONTINUING
        }
    }

    // Failed login fallback
    echo "<script>
        alert('Access Denied! Email or Password incorrect.');
        window.location.href='admin_login.php';
    </script>";
    exit(); // ✅ Also stop here
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" />

  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-box {
      padding: 50px 40px;
      max-width: 420px;
      height: 95%;
      width: 100%;
      background-color: rgb(191, 212, 233);
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease-in-out;
    }

    .login-box:hover {
      transform: scale(1.02);
    }

    .login-box img.logo {
      width: 120px;
      height: 120px;
      margin-top: 30px;
      margin-bottom: 20px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    form input[type="text"],
    form input[type="password"] {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border-radius: 5px;
      border: 1px solid #ccc;
    }

    form input[type="submit"] {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      font-size: 16px;
      font-weight: 500;
      border-radius: 30px;
      background: linear-gradient(135deg, #007BFF, #0056b3);
      color: white;
      border: none;
      transition: background 0.3s ease-in-out;
    }

    form input[type="submit"]:hover {
      background: linear-gradient(135deg, #0056b3, #003d80);
    }

    .object-fit-cover {
      object-fit: cover;
    }

    @media (max-width: 767px) {
      .img-side {
        display: none;
      }

      .login-box {
        padding: 30px 20px;
        border-radius: 10px;
      }
    }
  </style>
</head>

<body>
  <div class="container-fluid vh-100">
    <div class="row h-100">
      <!-- Left: Image -->
      <div class="col-md-8 p-0 img-side">
        <img src="img/pic1.jpg" alt="Visual" class="img-fluid vh-100 w-100 object-fit-cover" />
      </div>

      <!-- Right: Admin Login -->
      <div class="col-md-4 d-flex justify-content-center align-items-center bg-light">
        <div class="login-box text-center">
          <img src="img/logo.jpg" alt="Logo" class="logo" />
          <h2 style="font-size: 25px; font-weight: 600; margin-bottom: 30px;">Admin Login </h2>
          <form action="" method="POST">
            <input type="text" name="email" class="form-control mb-3" placeholder="Enter Email" required />
            <input type="password" name="password" class="form-control mb-2" placeholder="Enter Password" required />
            <br>
            <button href="index.php" type="submit" name="login" class="btn btn-primary w-100">Login</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>