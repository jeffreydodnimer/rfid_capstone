<?php
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfid_number = $_POST['rfid_number'];

    // Get LRN from RFID
    $query = "SELECT lrn FROM rfid WHERE rfid_number = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $rfid_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $lrn = $row['lrn'];

        // Get enrollment ID
        $query2 = "SELECT enrollment_id FROM enrollments WHERE lrn = ?";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bind_param("s", $lrn);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($row2 = $result2->fetch_assoc()) {
            $enrollment_id = $row2['enrollment_id'];
            $date = date("Y-m-d");
            $time = date("H:i:s");

            $stmt3 = $conn->prepare("INSERT INTO attendance (lrn, enrollment_id, date, time) VALUES (?, ?, ?, ?)");
            $stmt3->bind_param("siss", $lrn, $enrollment_id, $date, $time);
            $stmt3->execute();

            echo "Attendance recorded.";
        } else {
            echo "Enrollment not found.";
        }
    } else {
        echo "RFID not found.";
    }
}
?>

<form method="post">
    RFID Number: <input type="text" name="rfid_number" required><br>
    <input type="submit" value="Log Attendance">
</form>
