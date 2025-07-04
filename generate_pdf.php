<?php
// Start output buffering to prevent any accidental output
ob_start();

// Error reporting - comment these lines out when in production
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0); // Hide errors from output to prevent interfering with PDF

// Include the FPDF library
require('fpdf/fpdf.php'); // Ensure fpdf.php is in a 'fpdf' subdirectory or adjust path

// --- Database Configuration ---
include 'conn.php';

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean(); // Clean buffer before outputting error
    die("Database connection failed: " . $e->getMessage());
}

// --- Input Validation and Sanitization ---
// Example Usage: generate_sf2.php?section_id=1&month=2024-03&school_year=2024-2025&school_id=123456&school_name=MySampleSchool&advisor_name=John%20Doe&school_head_name=Jane%20Smith
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$month_year_str = isset($_GET['month']) ? htmlspecialchars(trim($_GET['month'])) : ''; // Format: YYYY-MM
$school_year = isset($_GET['school_year']) ? htmlspecialchars(trim($_GET['school_year'])) : ''; // Format: YYYY-YYYY
$school_id = isset($_GET['school_id']) ? htmlspecialchars(trim($_GET['school_id'])) : '987654'; // Default/Example
$school_name = isset($_GET['school_name']) ? htmlspecialchars(trim($_GET['school_name'])) : 'SAMPLE INTEGRATED SCHOOL'; // Default/Example
$advisor_name = isset($_GET['advisor_name']) ? htmlspecialchars(trim($_GET['advisor_name'])) : 'LOVELY BUAG CHIANG'; // Default/Example Name from image
$school_head_name = isset($_GET['school_head_name']) ? htmlspecialchars(trim($_GET['school_head_name'])) : 'ELENITA BUSA BELARE'; // Default/Example Name from image

// Auto-derive school year if not provided
if (empty($school_year) && !empty($month_year_str)) {
    $year = (int)substr($month_year_str, 0, 4);
    $month = (int)substr($month_year_str, 5, 2);
    $school_year_start = ($month >= 6) ? $year : $year - 1;
    $school_year_end = $school_year_start + 1;
    $school_year = "{$school_year_start}-{$school_year_end}";
}

if (empty($section_id) || empty($month_year_str) || !preg_match('/^\d{4}-\d{2}$/', $month_year_str) || empty($school_year)) {
    ob_end_clean();
    
    // Provide helpful error message with available sections
    $current_month = date('Y-m');
    $current_year = date('Y');
    $default_school_year = ($current_month >= '06') ? $current_year . '-' . ($current_year + 1) : ($current_year - 1) . '-' . $current_year;
    
    $error_message = "Error: Please provide valid parameters.\n\n";
    $error_message .= "Required parameters:\n";
    $error_message .= "- section_id: ID of the section\n";
    $error_message .= "- month: format YYYY-MM\n";
    $error_message .= "- school_year: format YYYY-YYYY (optional, will be auto-derived if not provided)\n\n";
    $error_message .= "Example URL:\n";
    $error_message .= "generate_sf2.php?section_id=1&month=$current_month&school_year=$default_school_year\n\n";
    
    // Try to show available sections if possible
    try {
        $stmt_available = $pdo->prepare("SELECT section_id, section_name, grade_level FROM sections LIMIT 5");
        $stmt_available->execute();
        $available_sections = $stmt_available->fetchAll();
        
        if (!empty($available_sections)) {
            $error_message .= "Available sections in your database:\n";
            foreach ($available_sections as $section) {
                $error_message .= "- ID: {$section['section_id']} - {$section['section_name']} ({$section['grade_level']})\n";
            }
        }
    } catch (Exception $e) {
        $error_message .= "Could not retrieve available sections from database.\n";
    }
    
    die(nl2br($error_message));
}

$year = (int)substr($month_year_str, 0, 4);
$month = (int)substr($month_year_str, 5, 2);

// Validate month and year
if (!checkdate($month, 1, $year)) {
    ob_end_clean();
    die("Error: Invalid month or year provided.");
}

$month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// --- Data Fetching and Processing ---

// 1. Get section information
$stmt_section = $pdo->prepare("SELECT section_name, grade_level FROM sections WHERE section_id = :section_id");
$stmt_section->execute(['section_id' => $section_id]);
$section_info = $stmt_section->fetch();

if (!$section_info) {
    ob_end_clean();
    die("Error: Section with ID '$section_id' not found.");
}

$section_name = $section_info['section_name'];
$grade_level = $section_info['grade_level'];

// 2. Get all students from the specified section for the given school year
$stmt_students = $pdo->prepare("
    SELECT 
        s.lrn, 
        CONCAT(
            COALESCE(s.lastname, ''), 
            CASE 
                WHEN COALESCE(s.firstname, '') != '' THEN CONCAT(', ', s.firstname)
                ELSE ''
            END,
            CASE 
                WHEN COALESCE(s.middlename, '') != '' THEN CONCAT(' ', s.middlename)
                ELSE ''
            END,
            CASE 
                WHEN COALESCE(s.suffix, '') != '' THEN CONCAT(' ', s.suffix)
                ELSE ''
            END
        ) as name,
        s.birthdate,
        s.age,
        e.enrollment_id
    FROM students s
    INNER JOIN enrollments e ON s.lrn = e.lrn
    WHERE e.section_id = :section_id AND e.school_year = :school_year
    ORDER BY s.lastname, s.firstname, s.middlename ASC
");

try {
    $stmt_students->execute(['section_id' => $section_id, 'school_year' => $school_year]);
    $all_students_raw = $stmt_students->fetchAll();
} catch (Exception $e) {
    ob_end_clean();
    die("Database Error: " . $e->getMessage() . "\n\nNote: Please ensure the enrollments table exists and is properly linked.");
}

if (empty($all_students_raw)) {
    ob_end_clean();
    die("Error: No students found for section ID '$section_id' (Section: $section_name) in school year '$school_year'.");
}

// Since we don't have gender data, we'll assign students to groups
// For a more accurate system, consider adding a gender field to the students table
$total_students = count($all_students_raw);
$male_students = [];
$female_students = [];

// Simple alternating assignment - you should modify this logic based on your needs
// or add a gender field to your students table
foreach ($all_students_raw as $index => $student) {
    if ($index % 2 == 0) {
        $student['gender'] = 'male';
        $male_students[] = $student;
    } else {
        $student['gender'] = 'female';
        $female_students[] = $student;
    }
}

// Set enrollment counts based on actual student counts
$enrolled_male_count_sy_start = count($male_students);
$enrolled_female_count_sy_start = count($female_students);
$total_enrolled_sy_start = $enrolled_male_count_sy_start + $enrolled_female_count_sy_start;

// "Registered Learners as of end of month" is the same as enrollment for this example
$registered_male_count_end_month = $enrolled_male_count_sy_start;
$registered_female_count_end_month = $enrolled_female_count_sy_start;
$total_registered_at_end_month = $total_enrolled_sy_start;

// 3. Get all attendance records for the section for the given month and school year
$stmt_attendance = $pdo->prepare("
    SELECT s.lrn, a.date, a.time_in
    FROM attendance a
    INNER JOIN students s ON a.lrn = s.lrn
    INNER JOIN enrollments e ON a.enrollment_id = e.enrollment_id
    WHERE e.section_id = :section_id 
    AND e.school_year = :school_year
    AND DATE_FORMAT(a.date, '%Y-%m') = :month_year
    AND a.status = 'present'
");

$stmt_attendance->execute([
    'section_id' => $section_id, 
    'school_year' => $school_year,
    'month_year' => $month_year_str
]);
$records = $stmt_attendance->fetchAll();

// 4. Process records into a matrix: [lrn][day] => true (for presence)
$attendance_matrix = [];
foreach ($records as $record) {
    $record_day = (int)date('d', strtotime($record['date']));
    $record_month_val = (int)date('m', strtotime($record['date']));
    $record_year_val = (int)date('Y', strtotime($record['date']));

    // Check if the record is for the actual month and year being reported
    if ($record_month_val == $month && $record_year_val == $year) {
        $attendance_matrix[$record['lrn']][$record_day] = true; // Mark as present
    }
}

// --- PDF Generation ---
class PDF extends FPDF
{
    protected $school_id;
    protected $school_name;
    protected $school_year;
    protected $month_name;
    protected $grade_level;
    protected $section_name;

    function __construct($orientation = 'L', $unit = 'mm', $size = 'A3')
    {
        parent::__construct($orientation, $unit, $size);
    }

    // Setter methods for dynamic header content
    function setHeaderData($school_id, $school_name, $school_year, $month_name, $grade_level, $section_name) {
        $this->school_id = $school_id;
        $this->school_name = $school_name;
        $this->school_year = $school_year;
        $this->month_name = $month_name;
        $this->grade_level = $grade_level;
        $this->section_name = $section_name;
    }

    // Page header
    function Header()
    {
        // Only print header on the first page
        if ($this->PageNo() == 1) {
            // Main Title
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 6, 'School Form 2 (SF2) Daily Attendance Report of Learners', 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)', 0, 1, 'C');
            $this->Ln(3);

            // Structured Info Rows (School ID, School Year, Month, School Name, Grade Level, Section)
            $this->SetFont('Arial', '', 8);

            // Row 1
            $this->Cell(25, 7, 'School ID', 0);
            $this->Cell(30, 7, $this->school_id, 1, 0, 'C');
            $this->Cell(10, 7, '', 0); // Spacer

            $this->Cell(25, 7, 'School Year', 0);
            $this->Cell(30, 7, $this->school_year, 1, 0, 'C');
            $this->Cell(10, 7, '', 0); // Spacer

            $this->Cell(35, 7, 'Report for the Month of', 0);
            $this->Cell(40, 7, $this->month_name, 1, 1, 'C');

            // Row 2: School Name & Grade Level
            $this->Cell(25, 7, 'Name of School', 0);
            $this->Cell(105, 7, $this->school_name, 1, 0, 'L');
            $this->Cell(10, 7, '', 0); // Spacer

            $this->Cell(25, 7, 'Grade Level', 0);
            $this->Cell(30, 7, $this->grade_level, 1, 0, 'C');
            $this->Cell(10, 7, '', 0); // Spacer

            $this->Cell(25, 7, 'Section', 0);
            $this->Cell(30, 7, $this->section_name, 1, 1, 'C');

            $this->Ln(5);
        }
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Generated thru LIS', 0, 0, 'L'); // Aligned left as per image
        // To place page number in the center after the left-aligned text, adjust coordinates.
        $this->SetX($this->GetX() - 100); // Backtrack for the next cell position
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C'); // Original, but centered relative to remaining width
    }
}

// Helper function to generate the attendance table for a specific gender
function generateAttendanceTable($pdf, $students, $attendance_matrix, $school_days_list, $year, $month, $gender_label, $print_header = true) {
    // Standard cell width for each day, adjusted for A3 paper
    $day_cell_width = 8; // Increased width slightly as there are fewer columns
    $name_col_width = 70; // Width for "LEARNER'S NAME" column

    // Only print header if $print_header is true
    if ($print_header) {
        // --- HEADER ROW 1 ---
        $pdf->SetFont('Arial', 'B', 6.5); // Smaller font for the long name header
        $pdf->Cell($name_col_width, 5, "LEARNER'S NAME (Last Name, First Name, Middle Name)", 1, 0, 'C');

        // Reset font size for day headers
        $pdf->SetFont('Arial', 'B', 8);

        // Daily numbers (Only for school days, not 1 to 31)
        foreach ($school_days_list as $day) {
            $pdf->Cell($day_cell_width, 5, $day, 1, 0, 'C');
        }

        // "Total for the Month" header - spans 3 columns below it (Abs, Tdy, Blank space)
        $total_month_header_width = 7 + 7 + 14;
        $pdf->Cell($total_month_header_width, 5, 'Total for the Month', 1, 0, 'C');

        // MultiCell for the long REMARKS header (width 45)
        // Save current X, Y to restore position after MultiCell
        $x_after_total_month = $pdf->GetX();
        $y_at_remarks_start = $pdf->GetY();
        $pdf->MultiCell(45, 2.5, 'REMARKS (If DROPPED OUT, Date/Reason/ Transferred Out)', 1, 'C');
        // Restore X position to after remarks MultiCell, keeping Y for the next line start
        $pdf->SetXY($x_after_total_month + 45, $y_at_remarks_start);

        $pdf->Ln(5); // Move to next line for the second row of headers

        // --- HEADER ROW 2 (Gender, Days of Week, Abs/Tdy, Remarks continued) ---
        // Learner's Name column: Gender label
        $pdf->Cell($name_col_width, 5, $gender_label, 1, 0, 'L');

        // Day of the week row (Only for school days)
        $pdf->SetFont('Arial', 'B', 7); // Font for Day of Week
        foreach ($school_days_list as $day) {
            $date_string = "$year-$month-$day";
            $day_of_week = strtoupper(date('D', strtotime($date_string))[0]); // Get 'M', 'T', 'W', etc.
            $pdf->Cell($day_cell_width, 5, $day_of_week, 1, 0, 'C');
        }
        $pdf->SetFont('Arial', 'B', 8); // Reset font for Abs/Tdy headers

        // Sub-header for Totals: Abs, Tdy, and a blank cell matching the 14mm width
        $pdf->Cell(7, 5, 'Abs', 1, 0, 'C');
        $pdf->Cell(7, 5, 'Tdy', 1, 0, 'C');
        $pdf->Cell(14, 5, '', 1, 0, 'C'); // Empty cell to align with the "Month" blank column

        // Remarks column - second line (no text, but needs borders)
        $pdf->Cell(45, 5, '', 1, 1, 'C');
    } else {
        // Even if we don't print the main header, we still need a gender sub-header row
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($name_col_width, 5, $gender_label, 1, 0, 'L');
        
        // Create blank cells to fill the rest of the row to maintain table structure
        $total_days_width = count($school_days_list) * $day_cell_width;
        $total_summary_width = 7 + 7 + 14;
        $total_remarks_width = 45;
        $pdf->Cell($total_days_width + $total_summary_width + $total_remarks_width, 5, '', 1, 1, 'C');
    }

    // --- TABLE BODY ---
    $pdf->SetFont('Arial', '', 8);
    $daily_absences = array_fill(1, 31, 0); // Stores total absentees per day for this gender (max 31 days)
    $daily_presents = array_fill(1, 31, 0); // Stores total presents for school days (max 31 days)
    $student_attendance_summary = []; // Stores total abs/tardy per student

    foreach ($students as $student) {
        $safe_name = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $student['name']);
        if ($safe_name === false) {
            $safe_name = $student['name']; // Fallback if iconv fails
        }

        $pdf->Cell($name_col_width, 5, $safe_name, 1, 0, 'L');

        $total_absences = 0;
        $total_tardies = 0; // Assuming no tardy data for now, always 0

        // Loop only through school days
        foreach ($school_days_list as $day) {
            $is_present = isset($attendance_matrix[$student['lrn']][$day]);
            
            if ($is_present) {
                $pdf->Cell($day_cell_width, 5, '', 1, 0, 'C'); // Blank (Present)
                $daily_presents[$day]++;
            } else {
                $pdf->Cell($day_cell_width, 5, 'X', 1, 0, 'C'); // 'X' (Absent)
                $total_absences++;
                $daily_absences[$day]++;
            }
        }

        // Output total absences and tardies for each student
        $pdf->Cell(7, 5, $total_absences > 0 ? $total_absences : '', 1, 0, 'C');
        $pdf->Cell(7, 5, $total_tardies > 0 ? $total_tardies : '', 1, 0, 'C');
        $pdf->Cell(14, 5, '', 1, 0, 'C'); // Empty space aligned with "Month" column

        // Remarks column with proper alignment
        $pdf->Cell(45, 5, '', 1, 1, 'L'); // Remarks

        $student_attendance_summary[$student['lrn']] = [
            'absent' => $total_absences,
            'tardy' => $total_tardies
        ];
    }

    // --- DAILY TOTALS ROW ---
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($name_col_width, 5, $gender_label . ' | TOTAL PER DAY -->', 1, 0, 'R');
    
    // Loop only through school days for the totals
    foreach ($school_days_list as $day) {
        $pdf->Cell($day_cell_width, 5, $daily_absences[$day] > 0 ? $daily_absences[$day] : '', 1, 0, 'C');
    }

    // Match the column structure from above (Abs, Tdy, Blank, Remarks)
    $pdf->Cell(7, 5, '', 1, 0, 'C');
    $pdf->Cell(7, 5, '', 1, 0, 'C');
    $pdf->Cell(14, 5, '', 1, 0, 'C');
    $pdf->Cell(45, 5, '', 1, 1, 'C'); // Empty cell for remarks column

    return [
        'daily_absences' => $daily_absences,
        'daily_presents' => $daily_presents, // Return daily presents sums for ADA calculation
        'student_attendance_summary' => $student_attendance_summary
    ];
}

// --- PDF Initialization ---
try {
    $pdf = new PDF('L', 'mm', 'A3'); // A3 for more horizontal space
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 15); // Adjust auto page break margin
    $pdf->setHeaderData($school_id, $school_name, $school_year, $month_name, $grade_level, $section_name);
    $pdf->AddPage();

    // --- Calculate school days for the month, excluding weekends ---
    $school_days_in_month_list = [];
    for ($d = 1; $d <= $days_in_month; $d++) {
        $day_of_week_num = date('N', strtotime("{$year}-{$month}-{$d}"));
        if ($day_of_week_num >= 1 && $day_of_week_num <= 5) { // Mon-Fri
            $school_days_in_month_list[] = $d;
        }
    }
    $total_school_days_in_month = count($school_days_in_month_list);

    // --- Generate Attendance Tables (Male & Female) ---
    // Generate Male table with the full header
    $male_data = generateAttendanceTable($pdf, $male_students, $attendance_matrix, $school_days_in_month_list, $year, $month, 'MALE');
    
    // Generate Female table WITHOUT the header, and remove the space between them.
    $female_data = generateAttendanceTable($pdf, $female_students, $attendance_matrix, $school_days_in_month_list, $year, $month, 'FEMALE', false);

    // --- Combined Daily Totals ---
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(200, 200, 200); // Darker gray for combined totals
    $day_cell_width_global = 8; // Must match the one used in generateAttendanceTable
    $name_col_width = 70; // Must match the one used in generateAttendanceTable

    $pdf->Cell($name_col_width, 5, 'COMBINED | TOTAL PER DAY -->', 1, 0, 'R', true);
    
    // Loop only through school days for the combined totals
    foreach ($school_days_in_month_list as $day) {
        $combined_total_absent = $male_data['daily_absences'][$day] + $female_data['daily_absences'][$day];
        $pdf->Cell($day_cell_width_global, 5, $combined_total_absent > 0 ? $combined_total_absent : '', 1, 0, 'C', true);
    }
    
    // Matching the column structure from above (Abs, Tdy, Blank, Remarks)
    $pdf->Cell(7, 5, '', 1, 0, 'C', true);
    $pdf->Cell(7, 5, '', 1, 0, 'C', true);
    $pdf->Cell(14, 5, '', 1, 0, 'C', true);
    $pdf->Cell(45, 5, '', 1, 1, 'C', true); // Empty cell for remarks column
    $pdf->Ln(5);

    // --- Summary Box Calculations ---
    // Total learners present for the month (sum of daily presents for all school days)
    $total_present_males_sum_daily = array_sum($male_data['daily_presents']);
    $total_present_females_sum_daily = array_sum($female_data['daily_presents']);
    $total_daily_attendance_all = $total_present_males_sum_daily + $total_present_females_sum_daily;

    // Average Daily Attendance (ADA) per Section B. from guidelines
    $avg_daily_attendance_male = ($total_school_days_in_month > 0) ? $total_present_males_sum_daily / $total_school_days_in_month : 0;
    $avg_daily_attendance_female = ($total_school_days_in_month > 0) ? $total_present_females_sum_daily / $total_school_days_in_month : 0;
    $overall_avg_daily_attendance = ($total_school_days_in_month > 0) ? $total_daily_attendance_all / $total_school_days_in_month : 0;

    // Percentage of Attendance for the month per Section C. from guidelines
    // Formula: (Average daily attendance / Registered Learners as of end of the month) x 100
    $percentage_attendance_male = ($registered_male_count_end_month > 0) ? ($avg_daily_attendance_male / $registered_male_count_end_month) * 100 : 0;
    $percentage_attendance_female = ($registered_female_count_end_month > 0) ? ($avg_daily_attendance_female / $registered_female_count_end_month) * 100 : 0;
    $overall_percentage_attendance = ($total_registered_at_end_month > 0) ? ($overall_avg_daily_attendance / $total_registered_at_end_month) * 100 : 0;

    // Set starting Y for this section to prevent overlap with previous tables
    $current_y_summary = $pdf->GetY();
    if ($current_y_summary > 180) { // Check if we need a new page for the summary
        $pdf->AddPage();
        $current_y_summary = $pdf->GetY();
    }
    $start_x_col1 = $pdf->GetX(); // Leftmost column starting X

    // Adjust column widths based on the image
    $col1_width = 100; // Guidelines column width
    $col2_width = 100; // Codes/Reasons column width
    $col3_width = 150; // Summary column width

    // Define starting X for each major section
    $reasons_col_start_x = $start_x_col1 + $col1_width + 5; // Gap of 5mm
    $summary_col_start_x = $reasons_col_start_x + $col2_width + 5; // Gap of 5mm

    // Titles for each section (bold, heading style)
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetY($current_y_summary); // Start new content below tables

    // 1. GUIDELINES column
    $pdf->SetX($start_x_col1);
    $pdf->Cell($col1_width, 5, 'GUIDELINES:', 0, 0, 'L');

    // 2. CODES FOR CHECKING ATTENDANCE column
    $pdf->SetX($reasons_col_start_x);
    $pdf->Cell($col2_width, 5, 'CODES FOR CHECKING ATTENDANCE', 0, 0, 'L');

    // 3. SUMMARY FOR THE MONTH column
    $pdf->SetX($summary_col_start_x);
    $pdf->Cell($col3_width, 5, 'SUMMARY FOR THE MONTH', 0, 1, 'C');

    // Move Y cursor down to start the actual content of the sections
    $y_content_start = $current_y_summary + 7;

    // --- Column 1: GUIDELINES ---
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetXY($start_x_col1, $y_content_start);

    // Guidelines text matching the image exactly
    $guidelines_text = "1. The attendance shall be accomplished daily. Refer to the codes for checking learners' attendance.\n2. Dates shall be written in the columns after Learner's Name.\n3. To compute the following:\n\n   a. Percentage of Enrolment = (Registered Learners as of end of the month / Enrolment as of 1st Friday of the school year) x 100\n\n   b. Average Daily Attendance = (Total Daily Attendance / Number of School Days in reporting month)\n\n   c. Percentage of Attendance for the month = (Average daily attendance / Registered Learners as of end of the month) x 100\n\n4. Every end of the month, the class adviser will submit this form to the office of the principal for recording of summary table into School Form 4. Once signed by the principal, this form should be returned to the adviser.\n5. The adviser will provide necessary interventions including but not limited to home visitation to learners who were absent for 5 consecutive days and/or those at risk of dropping out.\n6. Attendance performance of learners will be reflected in Form 137 and Form 138 every grading period.\n\n*Beginning of School Year/cutoff report is every 1st Friday of the School Year";

    $pdf->MultiCell($col1_width, 3.5, $guidelines_text, 0, 'L');
    $guidelines_end_y = $pdf->GetY();

    // --- Column 2: CODES FOR CHECKING ATTENDANCE & REASONS/CAUSE ---
    $pdf->SetXY($reasons_col_start_x, $y_content_start);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($col2_width, 4, '1. CODES FOR CHECKING ATTENDANCE', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x);
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell($col2_width, 4, "(Blank) - Present, (O) - Absent, (Tardy) (Half shaded) - Late In, (L) - Late Come, (Leave) (G) - Going Out, (C) - Cutting Classes", 0, 'L');
    $pdf->Ln(2);

    // 2. REASONS/CAUSES FOR NLS
    $pdf->SetX($reasons_col_start_x);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($col2_width, 4, '2. REASONS/CAUSES FOR NLS', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 8);

    // Domestic-Related Factors
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, 'a. Domestic-Related Factors', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   1. Had to take care of siblings', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   2. Early marriage/pregnancy', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   3. Parental attitude toward schooling', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   4. Family problems', 0, 1, 'L');
    $pdf->Ln(0.5);

    // Individual-Related Factors
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, 'b. Individual-Related Factors', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   1. Illness', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   2. Disability', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   3. Death', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   4. Drug Abuse', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   5. Poor academic performance', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   6. Lack of interest/Motivation', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   7. Hunger/Malnutrition', 0, 1, 'L');
    $pdf->Ln(0.5);

    // School-Related Factors
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, 'c. School-Related Factors', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   1. Teacher Factor', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   2. Physical condition of classroom', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   3. Peer influence', 0, 1, 'L');
    $pdf->Ln(0.5);

    // Geographic/Environmental
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, 'd. Geographic/Environmental', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   1. Distance between home and school', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   2. Armed conflict (tribal wars & clan feuds)', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   3. Calamities/Disasters', 0, 1, 'L');
    $pdf->Ln(0.5);

    // Financial-Related
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, 'e. Financial-Related', 0, 1, 'L');
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, '   1. Child labor, work', 0, 1, 'L');
    $pdf->Ln(0.5);

    // Others
    $pdf->SetX($reasons_col_start_x); $pdf->Cell($col2_width, 3.5, 'f. Others (Specify)', 0, 1, 'L');
    $reasons_end_y = $pdf->GetY();

    // --- Column 3: Summary Table ---
    $pdf->SetXY($summary_col_start_x, $y_content_start);
    $pdf->SetFont('Arial', '', 8);

    // Define widths for the summary table columns
    $summary_label_width = 65;
    $summary_data_col_width = 20;
    $border = 1;

    // Month and No. of Days of Classes row
    $pdf->Cell(25, 5, 'Month:', $border, 0, 'L');
    $pdf->Cell(20, 5, $month_name, $border, 0, 'C');
    $pdf->Cell(40, 5, 'No. of Days of Classes:', $border, 0, 'L');
    $pdf->Cell(20, 5, $total_school_days_in_month, $border, 1, 'C');

    // Summary table header row
    $pdf->SetX($summary_col_start_x);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($summary_label_width, 5, 'Summary', $border, 0, 'L');
    $pdf->Cell($summary_data_col_width, 5, 'M', $border, 0, 'C');
    $pdf->Cell($summary_data_col_width, 5, 'F', $border, 0, 'C');
    $pdf->Cell($summary_data_col_width, 5, 'TOTAL', $border, 1, 'C');

    // Reset font for data rows
    $pdf->SetFont('Arial', '', 8);

    // Helper function for summary rows
    function addSummaryRow($pdf, $x_start, $label, $m_val, $f_val, $total_val, $label_width, $col_width) {
        $pdf->SetX($x_start);
        $pdf->Cell($label_width, 5, $label, 1, 0, 'L');
        $pdf->Cell($col_width, 5, $m_val, 1, 0, 'C');
        $pdf->Cell($col_width, 5, $f_val, 1, 0, 'C');
        $pdf->Cell($col_width, 5, $total_val, 1, 1, 'C');
    }

    // Data rows
    addSummaryRow(
        $pdf, $summary_col_start_x,
        '* Enrollment as of (1st Friday of the SY)',
        $enrolled_male_count_sy_start,
        $enrolled_female_count_sy_start,
        $total_enrolled_sy_start,
        $summary_label_width, $summary_data_col_width
    );

    // Late Enrollment row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Late enrollment during the month',
        '0',
        '0',
        '0',
        $summary_label_width, $summary_data_col_width
    );

    // Registered Learners row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Registered Learners as of end of month',
        $registered_male_count_end_month,
        $registered_female_count_end_month,
        $total_registered_at_end_month,
        $summary_label_width, $summary_data_col_width
    );

    // Percentage of Enrollment row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Percentage of Enrollment as of end of month',
        '100.00%',
        '100.00%',
        '100.00%',
        $summary_label_width, $summary_data_col_width
    );

    // Average Daily Attendance row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Average Daily Attendance',
        number_format($avg_daily_attendance_male, 2),
        number_format($avg_daily_attendance_female, 2),
        number_format($overall_avg_daily_attendance, 2),
        $summary_label_width, $summary_data_col_width
    );

    // Percentage of Attendance row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Percentage of Attendance for the month',
        number_format($percentage_attendance_male, 2) . '%',
        number_format($percentage_attendance_female, 2) . '%',
        number_format($overall_percentage_attendance, 2) . '%',
        $summary_label_width, $summary_data_col_width
    );

    // Number of students absent row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Number of students absent for 5 consecutive days',
        '',
        '',
        '',
        $summary_label_width, $summary_data_col_width
    );

    // N.L.S row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'N.L.S',
        '',
        '',
        '',
        $summary_label_width, $summary_data_col_width
    );

    // Transferred out row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Transferred out',
        '',
        '',
        '',
        $summary_label_width, $summary_data_col_width
    );

    // Transferred in row
    addSummaryRow(
        $pdf, $summary_col_start_x,
        'Transferred in',
        '',
        '',
        '',
        $summary_label_width, $summary_data_col_width
    );

    $summary_end_y = $pdf->GetY();

    // Set Y for certification and signatures to be below the lowest of the three columns
    $max_col_y = max($guidelines_end_y, $reasons_end_y, $summary_end_y);
    $pdf->SetY($max_col_y + 10);

    // --- Certification and Attestation ---
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, 'I certify that this is a true and correct report.', 0, 1, 'C');
    $pdf->Ln(8);

    // Adviser's signature
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 5, strtoupper($advisor_name), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 5, '(Signature of Adviser over Printed Name)', 0, 1, 'C');
    $pdf->Ln(10);

    // School Head's signature
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, 'Attested by:', 0, 1, 'C');
    $pdf->Ln(8);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 5, strtoupper($school_head_name), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 5, '(Signature of School Head over Printed Name)', 0, 1, 'C');

    // Clear the buffer and output the PDF
    ob_end_clean();
    $pdf->Output('I', "SF2_Attendance_{$section_name}_{$month_year_str}.pdf");

} catch (Exception $e) {
    ob_end_clean();
    // Log the error for debugging, but provide a user-friendly message
    error_log("PDF Generation Error: " . $e->getMessage() . " on line " . $e->getLine());
    die("An unexpected error occurred while generating the report. Please try again or contact support. Details: " . $e->getMessage());
}
?>