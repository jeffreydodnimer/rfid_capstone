<?php
$mysqli = new mysqli("localhost", "root", "", "capstone");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
?>
