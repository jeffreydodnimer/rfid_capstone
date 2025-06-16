<?php
require_once('fpdf/fpdf.php');

class SF2PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, 'School Form 2 (SF2) Daily Attendance Report of Learners', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)', 0, 1, 'C');
        $this->Ln(3);

        // Structured Info Row
        $this->SetFont('Arial', '', 8);
        $this->Cell(25, 7, 'School ID', 0);
        $this->Cell(25, 7, '301394', 1, 0, 'C');
        $this->Cell(25, 7, '', 0); // Sample

        $this->Cell(25, 7, 'School Year', 0);
        $this->Cell(30, 7, '2024 - 2025', 1, 0, 'C');
        $this->Cell(30, 7, '', 0);

        $this->Cell(35, 7, 'Report for the Month of', 0);
        $this->Cell(40, 7, 'June', 1, 1, 'C');



        // Second row: School Name & Grade Level
        $this->Cell(25, 7, 'Name of School', 0);
        $this->Cell(105, 7, 'SAN ISIDRO NATIONAL HIGH SCHOOL', 1, 0, 'L');
        $this->Cell(30, 7, '', 0);

        $this->Cell(25, 7, 'Grade Level', 0);
        $this->Cell(50, 7, 'Grade 7 (Year I)', 1, 0, 'C');
        $this->Cell(20, 7, '', 0);
        
        $this->Cell(25, 7, 'Section', 0);
        $this->Cell(30, 7, 'H PULI', 1, 1, 'C');

        $this->Ln(5);
    }

    function AttendanceTable($students, $month = 6, $year = 2024)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $weekdays = ['M', 'T', 'W', 'T', 'F'];
        $weekdayIndex = 0;

        // Header Row: #, Name, Weekday Letters
        $this->SetFont('Arial', 'B', 7);
        $this->Cell(10, 10, '#', 1, 0, 'C');
        $this->Cell(60, 10, 'LEARNER\'S NAME', 1, 0, 'C');
        for ($i = 1; $i <= $daysInMonth + 30; $i++) {
            
            if ($i<30){
                $this->Cell(5, 5, $i, 1, 0, 'C');
            }
            if ($i==30){
                $this->Cell(5, 5, $i, 1, 1, 'C');
                $this->Cell(70, 5, '', 0);
            }
             if ($i>30){
                $this->Cell(5, 5, 'M', 1, 0, 'C');
            }
            $weekdayIndex = ($weekdayIndex + 1) % 5;
        }
        $this->Cell(10, 10, 'P', 1, 0, 'C');
        $this->Cell(10, 10, 'A', 1, 0, 'C');
        $this->Cell(10, 10, 'T', 1, 0, 'C');
        $this->Cell(50, 10, 'REMARKS', 1, 1, 'C');

        // Remove second row (empty or initials) — we skip it here

        // Student Rows
        $this->SetFont('Arial', '', 7);
        $counter = 1;
        foreach ($students as $student) {
            $this->Cell(10, 6, $counter++, 1);
            $this->Cell(60, 6, $student['name'], 1);

            $present = $absent = $tardy = 0;

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $mark = $student['attendance'][$i] ?? '';
                $this->Cell(5, 6, $mark, 1, 0, 'C');

                if ($mark == 'P')
                    $present++;
                elseif ($mark == 'A')
                    $absent++;
                elseif ($mark == 'T')
                    $tardy++;
            }

            $this->Cell(10, 6, $present, 1, 0, 'C');
            $this->Cell(10, 6, $absent, 1, 0, 'C');
            $this->Cell(10, 6, $tardy, 1, 0, 'C');
            $this->Cell(50, 6, $student['remarks'] ?? '', 1, 1);
        }
    }
}

// Sample students
$students = [
    [
        'name' => 'Juan Dela Cruz',
        'attendance' => [
            1 => 'P',
            2 => 'P',
            3 => 'A',
            4 => 'P',
            5 => 'T',
            6 => 'P',
            7 => 'P',
            8 => 'P',
            9 => 'A',
            10 => 'P'
        ],
        'remarks' => ''
    ],
    [
        'name' => 'Maria Santos',
        'attendance' => [
            1 => 'P',
            2 => 'P',
            3 => 'P',
            4 => 'P',
            5 => 'P',
            6 => 'P',
            7 => 'P',
            8 => 'T',
            9 => 'P',
            10 => 'P'
        ],
        'remarks' => ''
    ]
];

$pdf = new SF2PDF('L', 'mm', 'Legal');
$pdf->AddPage();
$pdf->AttendanceTable($students, 6, 2024); // June 2024
$pdf->Output();
?>