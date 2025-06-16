<?php
ob_start();

require('fpdf.php');

// Database connection info — change database name as needed
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "capstone";  // Change this to your DB name ('sf2_system' or 'capstone')

// Connect to MySQL
$mysqli = new mysqli($host, $user, $pass);

// Check connection
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Create database if it doesn't exist
if (!$mysqli->select_db($dbname)) {
    $createDB = "CREATE DATABASE `$dbname`";
    if ($mysqli->query($createDB)) {
        echo "Database '$dbname' created successfully.<br>";
        $mysqli->select_db($dbname);
    } else {
        die("Database creation failed: " . $mysqli->error);
    }
}

// Create table if not exists
$createTableSQL = "
CREATE TABLE IF NOT EXISTS students_attendance (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    student_name VARCHAR(100) NOT NULL,
    attendance_data TEXT
) ENGINE=InnoDB;
";

if (!$mysqli->query($createTableSQL)) {
    die("Table creation failed: " . $mysqli->error);
}

// Sample data insertion (comment out after first run to avoid duplicates)
$insertSampleData = "
INSERT INTO students_attendance (student_name, attendance_data)
VALUES
    ('Juan Dela Cruz', 'P,A,P,P,P'),
    ('Maria Clara', 'P,P,P,A,P'),
    ('Pedro Penduko', 'A,A,P,P,P')
";
$mysqli->query($insertSampleData);  // Ignore errors if duplicates

// Query data
$query = "SELECT student_id, student_name, attendance_data FROM students_attendance";
$result = $mysqli->query($query);

if (!$result) {
    die("Error in query: " . $mysqli->error);
}

// Fetch data into array safely
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'student_id' => $row['student_id'] ?? '',
        'student_name' => $row['student_name'] ?? '',
        'attendance_data' => $row['attendance_data'] ?? '',
    ];
}

// Close DB connection
$mysqli->close();

// Generate PDF with FPDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'SF2 Attendance Report', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(30, 10, 'ID', 1);
$pdf->Cell(80, 10, 'Student Name', 1);
$pdf->Cell(70, 10, 'Attendance', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 12);
foreach ($students as $student) {
    $pdf->Cell(30, 10, $student['student_id'], 1);
    $pdf->Cell(80, 10, $student['student_name'], 1);
    $pdf->Cell(70, 10, $student['attendance_data'], 1);
    $pdf->Ln();
}

$pdf->Output('I', 'SF2_Report.pdf');

ob_end_flush();
