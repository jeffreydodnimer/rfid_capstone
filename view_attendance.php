<?php
include 'conn.php';

$sql = "SELECT s.firstname, s.lastname, a.date, a.time, e.grade_level, sec.section_name
        FROM attendance a
        JOIN students s ON a.lrn = s.lrn
        JOIN enrollments e ON a.enrollment_id = e.enrollment_id
        JOIN sections sec ON e.section_id = sec.section_id
        ORDER BY a.date DESC, a.time DESC";

$result = $conn->query($sql);
?>

<h2>Attendance Records</h2>
<table border="1">
    <tr>
        <th>Name</th>
        <th>Grade</th>
        <th>Section</th>
        <th>Date</th>
        <th>Time</th>
    </tr>

    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['firstname'] . ' ' . $row['lastname'] ?></td>
        <td><?= $row['grade_level'] ?></td>
        <td><?= $row['section_name'] ?></td>
        <td><?= $row['date'] ?></td>
        <td><?= $row['time'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>
