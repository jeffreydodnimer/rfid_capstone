<?php
require 'config.php';

// Insert sample header
$mysqli->query("INSERT INTO sf2_headers (school_id, school_name, school_year, report_month, grade_level, section)
VALUES ('111129', 'San Isidro National High School', '2024-2025', 'June', 'Grade 7', 'Year I-H PULI')");

$header_id = $mysqli->insert_id;

// Insert sample learners
for ($i = 1; $i <= 5; $i++) {
    $name = "Learner $i";
    $absent = rand(0, 2);
    $tardy = rand(0, 1);
    $mysqli->query("INSERT INTO sf2_learners (header_id, name, absent, tardy, remarks)
    VALUES ($header_id, '$name', $absent, $tardy, '')");
}

echo "Sample data inserted. <a href='sf2_generate.php?header_id=$header_id'>Generate SF2 PDF</a>";
?>
