<?php
require 'db_config.php';
$current_date = date('Y-m-d');
$stmt = $pdo->prepare("
    UPDATE attendance 
    SET status = 'absent'
    WHERE DATE(time_in) = :current_date
    AND time_out IS NULL
    AND status IS NULL
");
$stmt->execute(['current_date' => $current_date]);
?>