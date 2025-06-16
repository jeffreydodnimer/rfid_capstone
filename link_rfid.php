<?php
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rfid_number = $_POST['rfid_number'];
    $lrn = $_POST['lrn'];

    $stmt = $conn->prepare("INSERT INTO rfid (rfid_number, lrn) VALUES (?, ?)");
    $stmt->bind_param("ss", $rfid_number, $lrn);

    if ($stmt->execute()) {
        echo "RFID linked successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<form method="post">
    RFID Number: <input type="text" name="rfid_number" required><br>
    LRN: <input type="text" name="lrn" required><br>
    <input type="submit" value="Link RFID">
</form>
