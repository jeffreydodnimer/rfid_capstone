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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-image: url('img/pi.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .container {
            background-color: rgba(255, 255, 255, 0.75); /* Less opaque for more transparency */
            padding: 30px;
            width: 100%;
            min-height: 100vh;
            box-sizing: border-box;
            margin: 0;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f1f1f1;
            font-weight: bold;
            text-transform: uppercase;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #e2f2ff;
        }
        .status-present { color: #28a745; }
        .status-absent { color: #dc3545; }
        .status-late { color: #ffc107; }
        .back-link-container {
            text-align: center;
            margin-top: 20px;
        }
        .back-link {
            display: inline-block;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .back-link:hover {
            background-color: #0056b3;
        }
        .record-count {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1rem;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-clipboard-list"></i> Attendance Records</h1>
        <div class="record-count">
            <strong>Total Records: <?= count($attendanceRecords) ?></strong>
        </div>
        <div class="table-container">
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
                    <?php if (empty($attendanceRecords)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">
                                No attendance records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('M j, Y', strtotime($record['date']))) ?></td>
                                <td><?= htmlspecialchars($record['firstname'] . ' ' . $record['lastname']) ?></td>
                                <td><?= $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : 'N/A' ?></td>
                                <td><?= $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : 'N/A' ?></td>
                                <td class="status-<?= strtolower($record['status']) ?>">
                                    <?= ucfirst($record['status']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="back-link-container">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>