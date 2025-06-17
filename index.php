<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Choose Role</title>
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
      background-color:rgb(191, 212, 233);
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
      margin-bottom: 10px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    h1 {
      font-size: 36px;
      margin: 10px 0;
      color: #333;
    }

    p {
      font-size: 16px;
      margin-bottom: 30px;
      color: #444;
    }

    .role-buttons {
      display: flex;
      flex-direction: column;
      gap: 15px;
      align-items: center;
    }

    .role-buttons a {
      width: 200px;
      padding: 12px 30px;
      font-size: 16px;
      font-weight: 500;
      border-radius: 30px;
      text-decoration: none;
      color: white;
      background: linear-gradient(135deg, #007BFF, #0056b3);
      transition: background 0.3s ease-in-out;
    }

    .role-buttons a:hover {
      background: linear-gradient(135deg, #0056b3, #003d80);
    }

    .object-fit-cover {
      object-fit: cover;
    }

    .terms {
      font-size: 12px;
      margin-top: 25px;
      max-width: 335px;
      color: #555;
    }

    .terms a {
      color: #007bff;
      text-decoration: none;
    }

    .terms a:hover {
      text-decoration: underline;
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

      <!-- Right: Role Selection -->
      <div class="col-md-4 d-flex justify-content-center align-items-center bg-light">
        <div class="login-box text-center">
          <img src="img/logo.jpg" alt="Logo" class="logo" />
          <h1>Hi, Isidorian!</h1>
          <p>↓ Please click or tap your destination.</p>
          <div class="role-buttons">
            <a href="admin_login.php">Admin</a>
            <a href="faculty_login.php">Faculty</a>
          </div>

          <div class="terms">
            By using this service, you understood and agree to the PUP Online Services
            <a href="#">Terms of Use</a> and
            <a href="#">Privacy Statement</a>.
        </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
