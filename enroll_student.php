<?php
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lrn = $_POST['lrn'];
    $grade_level = $_POST['grade_level'];
    $section_id = $_POST['section_id'];
    $school_year = $_POST['school_year'];

    $stmt = $conn->prepare("INSERT INTO enrollments (lrn, grade_level, section_id, school_year) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $lrn, $grade_level, $section_id, $school_year);

    if ($stmt->execute()) {
        echo "Enrollment successful.";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<form method="post">
    LRN: <input type="text" name="lrn" required><br>
    Grade Level: <input type="text" name="grade_level" required><br>
    Section ID: <input type="number" name="section_id" required><br>
    School Year (e.g. 2024-2025): <input type="text" name="school_year" required><br>
    <input type="submit" value="Enroll Student">
</form>
