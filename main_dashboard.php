<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PUP Online Services</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
        }

        .container {
            display: flex;
            height: 100vh;
            width: 100%;
        }

        .left {
            flex: 2;
            background: url('img/pic1.jpg') no-repeat center center;
            background-size: cover;
        }

        .right {
            flex: 1;
            background: linear-gradient(to bottom right, #e4e8e9, #cfe0e8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 50px;
            text-align: center;
        }

        .right img {
            width: 100px;
            margin-bottom: 25px;
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

        .btn {
            width: 220px;
            padding: 14px;
            margin: 10px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            color: #fff;
            cursor: pointer;
        }

        .student {
            background-color: #007bff;
        }

        .faculty {
            background-color: #dc3545;
        }

        .terms {
            font-size: 12px;
            margin-top: 25px;
            max-width: 280px;
            color: #555;
        }

        .terms a {
            color: #007bff;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="left"></div>

    <div class="right">
        <img src="img/logo.jpg" alt="PUP Logo">
        <h1>Hi, Isidorian!</h1>
        <p>↓ Please click or tap your destination.</p>

        <form action="student_portal.php" method="get">
            <button class="btn student" type="submit">Teacher</button>
        </form>

        <form action="admin_login.php" method="get">
            <button class="btn faculty" type="submit">Admin</button>
        </form>

        <div class="terms">
            By using this service, you understood and agree to the PUP Online Services
            <a href="#">Terms of Use</a> and
            <a href="#">Privacy Statement</a>.
        </div>
    </div>
</div>

</body>
</html>
