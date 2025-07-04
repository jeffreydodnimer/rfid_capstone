<?php
// Database Configuration
$host       = 'localhost';
$db_name    = 'rfid_capstone';
$username   = 'root';
$password   = '';

// Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch Attendance Records
$attendanceRecords = [];
$stmt = $pdo->query("SELECT a.*, s.firstname, s.lastname FROM attendance a INNER JOIN students s ON a.lrn = s.lrn ORDER BY a.date DESC, a.time_in DESC");
$attendanceRecords = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="container">
        <h1>Attendance Records</h1>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendanceRecords as $record): ?>
                    <tr>
                        <td><?= htmlspecialchars($record['date']) ?></td>
                        <td><?= htmlspecialchars($record['firstname'] . ' ' . $record['lastname']) ?></td>
                        <td><?= htmlspecialchars($record['time_in']) ?></td>
                        <td><?= htmlspecialchars($record['time_out'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars(ucfirst($record['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>