<?php
// Database Configuration
$host = 'localhost'; // Your database host
$db_name = 'rfid_attendance_db'; // Your database name
$username = 'root'; // Your database username
$password = ''; // Your database password (empty for XAMPP default)

// Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = '';

// Handle Student Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $rfid_uid = trim($_POST['rfid_uid']);
    $name = trim($_POST['name']);
    $section = trim($_POST['section']);
    $grade = trim($_POST['grade']);
    $adviser = trim($_POST['adviser']);
    $gender = trim($_POST['gender']);

    // Check if all required fields are filled out
    if (empty($rfid_uid) || empty($name) || empty($section) || empty($grade) || empty($adviser) || empty($gender)) {
        $message = '<div class="alert error">All fields are required.</div>';
    } else {
        // Check if RFID UID already exists
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE rfid_uid = :rfid_uid");
        $stmt_check->execute(['rfid_uid' => $rfid_uid]);
        $rfid_exists = $stmt_check->fetchColumn();

        if ($rfid_exists) {
            $message = '<div class="alert error">This RFID card (' . htmlspecialchars($rfid_uid) . ') is already registered.</div>';
        } else {
            // Insert new student record including gender
            $stmt_insert = $pdo->prepare("
                INSERT INTO students (rfid_uid, name, section, grade, adviser, gender)
                VALUES (:rfid_uid, :name, :section, :grade, :adviser, :gender)
            ");
            $params = [
                'rfid_uid' => $rfid_uid,
                'name' => $name,
                'section' => $section,
                'grade' => $grade,
                'adviser' => $adviser,
                'gender' => $gender
            ];

            if ($stmt_insert->execute($params)) {
                $message = '<div class="alert success">Student ' . htmlspecialchars($name) . ' registered successfully!</div>';
            } else {
                $message = '<div class="alert error">Failed to register student.</div>';
            }
        }
    }
}

// Fetch all registered students for display
$students_list = [];
try {
    $stmt_students = $pdo->query("SELECT * FROM students ORDER BY name ASC");
    $students_list = $stmt_students->fetchAll();
} catch (PDOException $e) {
    $message .= '<div class="alert error">Error fetching students: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-container {
            max-width: 500px;
            margin: 0 auto 40px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group button {
            width: 100%;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-group button:hover {
            background-color: #218838;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .students-list {
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            color: #0056b3;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .nav-links {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-links a {
            margin: 0 15px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Student Registration</h1>
        
        <div class="nav-links">
            <a href="register.php">Register Student</a>
            <a href="attendance.php">Attendance System</a>
        </div>

        <?php echo $message; ?>

        <div class="form-container">
            <h2>Add New Student</h2>
            <form action="" method="post">
                <div class="form-group">
                    <label for="rfid_uid">RFID Card UID:</label>
                    <input type="text" id="rfid_uid" name="rfid_uid" placeholder="Scan or enter RFID UID" required>
                </div>
                <div class="form-group">
                    <label for="name">Student Name:</label>
                    <input type="text" id="name" name="name" placeholder="e.g., John Doe" required>
                </div>
                <div class="form-group">
                    <label for="section">Section:</label>
                    <input type="text" id="section" name="section" placeholder="e.g., Section A" required>
                </div>
                <div class="form-group">
                    <label for="grade">Grade:</label>
                    <input type="text" id="grade" name="grade" placeholder="e.g., Grade 10" required>
                </div>
                <div class="form-group">
                    <label for="adviser">Adviser:</label>
                    <input type="text" id="adviser" name="adviser" placeholder="e.g., Mrs. Smith" required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender:</label>
                    <select name="gender" id="gender" required>
                        <option value="" disabled selected>Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" name="register">Register Student</button>
                </div>
            </form>
        </div>

        <div class="students-list">
            <h2>Registered Students</h2>
            <table>
                <thead>
                    <tr>
                        <th>RFID UID</th>
                        <th>Student Name</th>
                        <th>Section</th>
                        <th>Grade</th>
                        <th>Adviser</th>
                        <th>Gender</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students_list)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No students registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students_list as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['rfid_uid']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['section']); ?></td>
                                <td><?php echo htmlspecialchars($student['grade']); ?></td>
                                <td><?php echo htmlspecialchars($student['adviser']); ?></td>
                                <td><?php echo htmlspecialchars($student['gender']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>