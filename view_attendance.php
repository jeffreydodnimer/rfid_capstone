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
        /* Styles omitted for brevity... */
    </style>
</head>
<body>
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