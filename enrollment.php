<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';

// Create logs directory if needed
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$enrollment_error_log = 'logs/enrollment_errors.txt';

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("Database connection failed. Please check the logs.");
}

// Ensure connection is utf8mb4 to match DB tables/collations
if (!$conn->set_charset("utf8mb4")) {
    error_log(date('c') . " Charset Error: " . $conn->error . "\n", 3, 'logs/error_log.txt');
    die("Failed to set database charset.");
}

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function validate_csrf_token() {
    // Check if CSRF token is present and valid
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, 'logs/enrollment_errors.txt');
        // Display alert and redirect to prevent further actions on a potentially compromised request
        echo "<script>alert('Invalid security token. Please try again.'); window.location.href='enrollment.php';</script>";
        exit;
    }
}

function getCurrentSchoolYear() {
    $currentMonth = (int)date('m');
    $currentYear = (int)date('Y');
    // Assuming school year starts around August
    if ($currentMonth >= 8) {
        return $currentYear . '-' . ($currentYear + 1);
    } else {
        return ($currentYear - 1) . '-' . $currentYear;
    }
}

$available_grade_levels = [
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'
];

/**
 * Fetch students for LRN datalist suggestions to aid user input.
 */
$students_result_for_dropdown = $conn->query(
    "SELECT lrn, lastname, firstname, middlename FROM students ORDER BY lastname, firstname"
);
$all_students_for_dropdown = [];
if ($students_result_for_dropdown) {
    while ($row = $students_result_for_dropdown->fetch_assoc()) {
        // Format name for display, using initials for middlename
        $fullName = htmlspecialchars($row['lastname'] . ', ' . $row['firstname']);
        if (!empty($row['middlename'])) {
            $fullName .= ' ' . htmlspecialchars(substr($row['middlename'], 0, 1)) . '.';
        }
        $all_students_for_dropdown[] = [
            'lrn' => $row['lrn'],
            'name' => $fullName
        ];
    }
} else {
    // Log error if fetching student list fails
    error_log("Error fetching students for dropdown: " . $conn->error);
}

/**
 * Fetch all sections and their advisers to populate dropdowns/datalists.
 * Uses employee_id to link sections to advisers.
 */
$sections_query = "
    SELECT
        s.section_id,
        s.section_name,
        s.grade_level,
        s.employee_id,
        COALESCE(
            CONCAT_WS(' ',
                adv.firstname,
                CASE
                    WHEN adv.middlename IS NOT NULL AND adv.middlename <> ''
                        THEN CONCAT(LEFT(adv.middlename, 1), '.')
                    ELSE NULL
                END,
                adv.lastname,
                CASE
                    WHEN adv.suffix IS NOT NULL AND adv.suffix <> ''
                        THEN adv.suffix
                    ELSE NULL
                END,
                CONCAT('(', s.employee_id, ')')
            ),
            'N/A'
        ) AS adviser_fullname
    FROM sections s
    LEFT JOIN advisers adv ON s.employee_id = adv.employee_id
    ORDER BY s.grade_level ASC, s.section_name ASC
";
$sections_result_all = $conn->query($sections_query);
$all_sections_for_js = []; // Array to hold section data for JavaScript
if ($sections_result_all) {
    while ($row = $sections_result_all->fetch_assoc()) {
        $all_sections_for_js[] = $row;
    }
} else {
    // Log warning if loading sections fails, as it might affect UI functionality
    error_log("Warning: Could not load sections for dropdowns. " . $conn->error);
}
// Encode section data to JSON for use in JavaScript
$all_sections_json = json_encode($all_sections_for_js);

/**
 * Handle Add Enrollment POST request.
 */
if (isset($_POST['add_enrollment'])) {
    validate_csrf_token(); // Validate CSRF token first
    // Sanitize and trim all inputs
    $lrn         = htmlspecialchars(trim($_POST['lrn']), ENT_QUOTES, 'UTF-8');
    $grade_level = htmlspecialchars(trim($_POST['grade_level']), ENT_QUOTES, 'UTF-8');
    $section_name = htmlspecialchars(trim($_POST['section_name']), ENT_QUOTES, 'UTF-8');
    $school_year = htmlspecialchars(trim($_POST['school_year']), ENT_QUOTES, 'UTF-8');

    // Check for empty required fields
    if (empty($lrn) || empty($grade_level) || empty($section_name) || empty($school_year)) {
        echo "<script>alert('All required fields must be filled.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    // Use transaction for atomicity
    $conn->begin_transaction();
    try {
        // 1. Check if the student (LRN) exists in the students table.
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found. Please add the student first.");
        }
        $stmt->close();

        // 2. Prevent duplicate enrollment for the same student and school year.
        $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE lrn = ? AND school_year = ?");
        $stmt->bind_param("ss", $lrn, $school_year);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("This student is already enrolled for the specified school year.");
        }
        $stmt->close();
        
        // 3. Insert the new enrollment record.
        $stmt = $conn->prepare("INSERT INTO enrollments (lrn, grade_level, section_name, school_year) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $lrn, $grade_level, $section_name, $school_year);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add enrollment: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit(); // Commit transaction if all operations were successful
        // Redirect to enrollment page with success status, which will show an alert
        echo "<script>alert('Enrollment added successfully!'); window.location.href='enrollment.php?status=added';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback transaction if any error occurred
        // Display error message
        echo "<script>alert('Error adding enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle Delete Enrollment POST request.
 */
if (isset($_POST['delete_enrollment'])) {
    validate_csrf_token(); // Validate CSRF token
    // Validate enrollment_id is an integer
    $enrollment_id = filter_var($_POST['enrollment_id'], FILTER_VALIDATE_INT);
    if ($enrollment_id === false || $enrollment_id === null) {
        echo "<script>alert('Invalid Enrollment ID.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start transaction for deletion
    try {
        // Delete the enrollment record
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        $stmt->bind_param("i", $enrollment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete enrollment: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit(); // Commit transaction
        // Redirect to enrollment page with success status
        echo "<script>alert('Enrollment deleted successfully!'); window.location.href='enrollment.php?status=deleted';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        // Display error message
        echo "<script>alert('Error deleting enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle Edit Enrollment POST request.
 */
if (isset($_POST['edit_enrollment'])) {
    validate_csrf_token(); // Validate CSRF token
    // Sanitize and validate inputs
    $enrollment_id = filter_var($_POST['edit_enrollment_id'], FILTER_VALIDATE_INT);
    $lrn           = htmlspecialchars(trim($_POST['edit_lrn_input']), ENT_QUOTES, 'UTF-8');
    $grade_level   = htmlspecialchars(trim($_POST['edit_grade_level']), ENT_QUOTES, 'UTF-8');
    $section_name  = htmlspecialchars(trim($_POST['edit_section_name']), ENT_QUOTES, 'UTF-8');
    $school_year   = htmlspecialchars(trim($_POST['edit_school_year']), ENT_QUOTES, 'UTF-8');

    // Check for invalid ID or missing fields
    if (
        $enrollment_id === false || $enrollment_id === null ||
        empty($lrn) || empty($grade_level) ||
        empty($section_name) || empty($school_year)
    ) {
        echo "<script>alert('Invalid ID or missing fields.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start transaction for update
    try {
        // Check if the student (LRN) still exists
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) throw new Exception("LRN not found. Cannot update enrollment.");
        $stmt->close();

        // Prevent duplicate enrollment (excluding the current record being edited)
        $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE lrn = ? AND school_year = ? AND enrollment_id != ?");
        $stmt->bind_param("ssi", $lrn, $school_year, $enrollment_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) throw new Exception("Another enrollment for this student exists for this school year.");
        $stmt->close();

        // Update the enrollment record
        $stmt = $conn->prepare("UPDATE enrollments SET lrn = ?, grade_level = ?, section_name = ?, school_year = ? WHERE enrollment_id = ?");
        $stmt->bind_param("ssssi", $lrn, $grade_level, $section_name, $school_year, $enrollment_id);
        if (!$stmt->execute()) throw new Exception("Failed to update enrollment: " . $stmt->error);
        $stmt->close();

        $conn->commit(); // Commit transaction
        // Redirect with success status
        echo "<script>alert('Enrollment updated successfully!'); window.location.href='enrollment.php?status=updated';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        // Display error message
        echo "<script>alert('Error updating enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle CSV Import POST request.
 */
function safeTrimCSV($value) {
    // Helper to safely trim and remove BOM if present
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) return '';
    // Remove UTF-8 BOM (Byte Order Mark) if it exists at the beginning
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

if (isset($_POST['import_enrollments_csv']) && isset($_FILES['enrollment_csvfile']) && $_FILES['enrollment_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token(); // Validate CSRF token
    $tmpPath  = $_FILES['enrollment_csvfile']['tmp_name'];
    $fileName = $_FILES['enrollment_csvfile']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Validate file type and size
    if ($fileExt !== 'csv') {
        echo "<script>alert('Please upload a CSV file.');window.location.href='enrollment.php';</script>";
        exit();
    }
    if ($_FILES['enrollment_csvfile']['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo "<script>alert('File size exceeds 5MB limit.');window.location.href='enrollment.php';</script>";
        exit();
    }

    // Process the CSV file
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1); // Helps with different line endings
        $header = fgetcsv($handle, 2000, ","); // Read the header row
        $expected_headers = ['lrn', 'grade_level', 'section_name', 'school_year'];

        // Validate header presence and count
        if (!is_array($header) || count($header) < count($expected_headers)) {
            fclose($handle);
            echo "<script>alert('CSV must have these columns: lrn, grade_level, section_name, school_year');window.location.href='enrollment.php';</script>";
            exit();
        }

        // Build a case-insensitive column map
        $header_normalized = array_map(fn($col) => strtolower(trim($col)), $header);
        $col_map = [];
        foreach ($expected_headers as $col) {
            $index = array_search($col, $header_normalized, true);
            if ($index === false) {
                fclose($handle);
                echo "<script>alert('CSV must include the column: {$col}');window.location.href='enrollment.php';</script>";
                exit();
            }
            $col_map[$col] = $index;
        }

        // Prepare SQL statement for insertion
        $stmt = $conn->prepare("INSERT INTO enrollments (lrn, grade_level, section_name, school_year) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            fclose($handle);
            error_log(date('c') . " PREPARE ERR (CSV Import): " . $conn->error . "\n", 3, $enrollment_error_log);
            echo "<script>alert('Database error preparing CSV import.');window.location.href='enrollment.php';</script>";
            exit();
        }

        $rowCount = 0; // Count of successfully imported rows
        $errors   = []; // Array to store errors
        $row_num  = 1; // Start row count from 1 (header is row 0)

        // Loop through each data row in the CSV
        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            // Skip empty or incomplete rows
            if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < count($expected_headers)) continue;

            // Extract and trim data using the column map
            $lrn_csv         = safeTrimCSV($data[$col_map['lrn']] ?? '');
            $grade_level_csv = safeTrimCSV($data[$col_map['grade_level']] ?? '');
            $section_name_csv = safeTrimCSV($data[$col_map['section_name']] ?? '');
            $school_year_csv = safeTrimCSV($data[$col_map['school_year']] ?? '');
            $valid_row = true; // Flag to check if current row is valid for insertion

            // Basic validation for required fields
            if (empty($lrn_csv) || empty($grade_level_csv) || empty($section_name_csv) || empty($school_year_csv)) {
                $errors[] = "Row {$row_num}: Missing required fields.";
                $valid_row = false;
            }

            // Further validation: check if student LRN exists
            if ($valid_row) {
                $stmt_check_student = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
                if ($stmt_check_student) {
                    $stmt_check_student->bind_param("s", $lrn_csv);
                    $stmt_check_student->execute();
                    $result = $stmt_check_student->get_result()->fetch_assoc();
                    if ($result['count'] == 0) {
                        $errors[] = "Row {$row_num}: LRN '{$lrn_csv}' not found in student records.";
                        $valid_row = false;
                    }
                    $stmt_check_student->close();
                } else {
                    $errors[] = "Row {$row_num}: Database error during student check.";
                    $valid_row = false;
                }
            }

            // Further validation: prevent duplicate enrollment for the same student and school year
            if ($valid_row) {
                $stmt_check_duplicate = $conn->prepare("SELECT COUNT(*) AS count FROM enrollments WHERE lrn = ? AND school_year = ?");
                if ($stmt_check_duplicate) {
                    $stmt_check_duplicate->bind_param("ss", $lrn_csv, $school_year_csv);
                    $stmt_check_duplicate->execute();
                    $result = $stmt_check_duplicate->get_result()->fetch_assoc();
                    if ($result['count'] > 0) {
                        $errors[] = "Row {$row_num}: Enrollment already exists for LRN '{$lrn_csv}' in school year '{$school_year_csv}'.";
                        $valid_row = false;
                    }
                    $stmt_check_duplicate->close();
                } else {
                    $errors[] = "Row {$row_num}: Database error during duplicate check.";
                    $valid_row = false;
                }
            }

            // If row is valid, bind parameters and execute insertion
            if ($valid_row) {
                $stmt->bind_param("ssss", $lrn_csv, $grade_level_csv, $section_name_csv, $school_year_csv);
                if (!$stmt->execute()) {
                    // Log insertion error
                    $errors[] = "Row {$row_num}: DB error during insert: " . $stmt->error;
                } else {
                    $rowCount++; // Increment success count
                }
            }
        }

        fclose($handle); // Close the CSV file handle
        $stmt->close(); // Close the prepared statement

        // Construct the message to display to the user
        $message = "CSV Import Complete\nSuccessfully imported: {$rowCount} enrollment records\n";
        if (!empty($errors)) {
            // Limit the number of displayed errors for brevity
            $message .= "\nErrors (" . count($errors) . " rows failed):\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        // Redirect to the admin dashboard after import
        echo "<script>alert(" . json_encode($message) . ");window.location.href='admin_dashboard.php';</script>";
        exit();
    } else {
        // Handle failure to open CSV file
        echo "<script>alert('Failed to open CSV file.');window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Fetch enrollment records for display in the table.
 * Joins with students table to get student names and with sections/advisers to get adviser names.
 */
$enrollments_query = "
    SELECT
        e.enrollment_id, e.lrn, e.grade_level, e.school_year, e.section_name,
        COALESCE(st.firstname, 'N/A') AS student_firstname,
        COALESCE(st.middlename, '') AS student_middlename,
        COALESCE(st.lastname, 'N/A') AS student_lastname,
        COALESCE(
            CONCAT_WS(' ',
                adv.firstname,
                CASE WHEN adv.middlename IS NOT NULL AND adv.middlename != '' THEN CONCAT(LEFT(adv.middlename, 1),'.') ELSE NULL END,
                adv.lastname,
                CASE WHEN adv.suffix IS NOT NULL AND adv.suffix != '' THEN adv.suffix ELSE NULL END
            ),
            'N/A'
        ) AS adviser_name
    FROM enrollments e
    LEFT JOIN students st ON e.lrn = st.lrn
    LEFT JOIN sections sec ON e.section_name = sec.section_name AND e.grade_level = sec.grade_level
    LEFT JOIN advisers adv ON sec.employee_id = adv.employee_id
    ORDER BY e.school_year DESC, e.grade_level ASC, e.section_name ASC, st.lastname, st.firstname
";
$enrollments_result = $conn->query($enrollments_query);
if (!$enrollments_result) {
    // Log error if fetching enrollments fails
    error_log("Error fetching enrollments: " . $conn->error);
    // Provide a fallback for the UI to display no records gracefully
    $enrollments_result = (object)[
        'num_rows' => 0,
        'fetch_assoc' => function(){ return null; } // Mock fetch_assoc for empty result
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Enrollment Dashboard</title>
    <!-- Custom fonts for this template -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Tailwind CSS (for styling components like modals and cards) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <style>
      /* Animation for highlighting changes */
      @keyframes hl {
        0% { background-color: #c8e6c9; }
        100% { background-color: transparent; }
      }
      .highlight { animation: hl 2s forwards; }

      /* Card styling for tables */
      .table-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        width: 100%;
        box-shadow: 0 12px 30px rgba(15,23,42,0.08);
      }
      /* Responsive table container */
      .table-responsive-custom { overflow-x: auto; }
      /* Custom table styling */
      .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
      }
      .custom-table thead th {
        text-align: left;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        color: #4b5563;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e5e7eb;
        background-color: #f9fafb;
      }
      .custom-table tbody td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
      }
      .custom-table tbody tr:hover { background: rgba(59,130,246,0.06); }
      /* Styles for action buttons */
      .actions-cell {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end; /* Align buttons to the right */
        min-width: 80px; /* Ensure consistent width */
      }
      .action-icon-btn {
        border: none;
        background: none;
        padding: 0;
        margin: 0 2px;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      .action-icon-btn .material-symbols-outlined {
        font-size: 1.1em;
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; /* Default icon style */
      }
      .action-icon-btn.edit-icon .material-symbols-outlined { color: #1c74e4; } /* Blue for edit */
      .action-icon-btn.delete-icon .material-symbols-outlined { color: #dc3545; } /* Red for delete */
      .action-icon-btn:hover .material-symbols-outlined { opacity: 0.7; } /* Hover effect */

      /* Page title styling */
      .page-title-with-logo { display: flex; align-items: center; gap: 12px; }
      .page-logo {
        width: 45px;
        height: 45px;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      /* Search box styling */
      .search-box {
        position: relative;
        display: inline-block;
        margin-right: 10px;
      }
      .search-box input {
        padding: 0.5rem 2.5rem 0.5rem 1rem; /* Space for icon and clear button */
        border: 1px solid #d1d5db;
        border-radius: 8px;
        width: 300px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
      }
      .search-box input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
      .search-box .search-icon {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        pointer-events: none; /* Icon should not be clickable */
      }
      .clear-search {
        position: absolute;
        right: 2.5rem; /* Position to the left of the search icon */
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        display: none; /* Hidden by default */
      }
      .clear-search.show { display: block; } /* Show when search input has text */
    </style>
  </head>
  <body id="page-top">
    <?php include 'nav.php'; // Assuming this includes your main navigation ?>
    <div id="wrapper">
      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          <div class="container-fluid">
            <!-- Page Title and Action Buttons -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
              <div class="page-title-with-logo">
                <img src="img/depedlogo.jpg" alt="School Logo" class="page-logo"> <!-- Make sure this path is correct -->
                <h2 class="h3 mb-0 text-gray-800">Enrollment Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <!-- Search Box -->
                <div class="search-box">
                  <input type="text" id="searchEnrollment" placeholder="Search Enrollment" autocomplete="off">
                  <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                    <span class="material-symbols-outlined">close</span>
                  </button>
                  <span class="search-icon">
                    <span class="material-symbols-outlined">search</span>
                  </span>
                </div>
                <!-- Import CSV Button -->
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined">upload_file</span> Import CSV
                </button>
                <!-- Add Enrollment Button -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined">person_add</span> Add Enrollment
                </button>
              </div>
            </div>

            <!-- Status Alert Message -->
<?php if (isset($_GET['status'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php
      // Display user-friendly messages based on status query parameter
      switch ($_GET['status']) {
        case 'added':   echo 'Enrollment added successfully!'; break;
        case 'updated': echo 'Enrollment updated successfully!'; break;
        case 'deleted': echo 'Enrollment deleted successfully!'; break;
      }
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Enrollment Table Card -->
<div class="table-card">
  <div class="table-responsive-custom">
    <table class="custom-table">
      <thead class="bg-gray-200 font-semibold">
        <tr>
          <th class="border px-3 py-2">No.</th>
          <th class="border px-3 py-2">LRN</th>
          <th class="border px-3 py-2">Student Name</th>
          <th class="border px-3 py-2">Section</th>
          <th class="border px-3 py-2">Grade Level</th>
          <th class="border px-3 py-2">Adviser</th>
          <th class="border px-3 py-2">School Year</th>
          <th class="border px-3 py-2">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $counter = 1; // Initialize row counter
        if ($enrollments_result->num_rows > 0):
            // Loop through each enrollment record and display in table rows
            while ($row = $enrollments_result->fetch_assoc()):
                // Format student's full name for display
                $fn = $row['student_firstname'];
                $mn = $row['student_middlename'];
                $ln = $row['student_lastname'];
                $student_fullname = htmlspecialchars($ln); // Start with Last Name
                if (!empty($fn) && $fn !== 'N/A') {
                    $student_fullname .= ', ' . htmlspecialchars($fn); // Add First Name if available
                }
                if (!empty($mn)) {
                    $student_fullname .= ' ' . htmlspecialchars(substr($mn, 0, 1)) . '.'; // Add Middle Initial
                }
        ?>
                <tr class="hover:bg-gray-50">
                  <td class="border px-3 py-2"><?= $counter++ ?></td>
                  <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn']) ?></td>
                  <td class="border px-3 py-2"><?= $student_fullname ?></td>
                  <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                  <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                  <td class="border px-3 py-2"><?= htmlspecialchars($row['adviser_name']) ?></td>
                  <td class="border px-3 py-2"><?= htmlspecialchars($row['school_year']) ?></td>
                  <td class="border px-3 py-2 actions-cell">
                    <!-- Edit Button -->
                    <button onclick='openEditEnrollmentModal(<?= json_encode([
                      "enrollment_id" => $row["enrollment_id"],
                      "lrn" => $row["lrn"],
                      "grade_level" => $row["grade_level"],
                      "section_name" => $row["section_name"],
                      "school_year" => $row["school_year"]
                    ]) ?>)' class="action-icon-btn edit-icon" title="Edit Enrollment">
                      <span class="material-symbols-outlined">edit</span>
                    </button>
                    <!-- Delete Form -->
                    <form method="POST" class="inline" onsubmit="return confirm('Remove enrollment for <?= htmlspecialchars($student_fullname) ?>? This action cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                      <input type="hidden" name="enrollment_id" value="<?= htmlspecialchars($row['enrollment_id']) ?>">
                      <button type="submit" name="delete_enrollment" class="action-icon-btn delete-icon" title="Delete Enrollment">
                        <span class="material-symbols-outlined">delete</span>
                      </button>
                    </form>
                  </td>
                </tr>
        <?php endwhile; else: ?>
                <!-- Message if no records found -->
                <tr>
                  <td colspan="8" class="border px-3 py-2 text-center text-gray-500">No enrollment records found.</td>
                </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
          </div>
        </div>

        <?php include 'footer.php'; // Assuming this includes your footer ?>
      </div>
    </div>

    <!-- Scroll to Top Button -->
    <a class="scroll-to-top rounded" href="#page-top">
      <i class="fas fa-angle-up"></i>
    </a>

    <!-- Add Enrollment Modal -->
    <div class="modal fade" id="addEnrollmentModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New Enrollment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="enrollmentForm">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

              <div class="mb-3">
                <label for="lrn_input" class="form-label">Student LRN *</label>
                <!-- Datalist for student LRN suggestions -->
                <input type="text" list="studentLrnList" id="lrn_input" name="lrn" class="form-control" placeholder="Enter LRN or select student" required>
                <datalist id="studentLrnList">
                    <?php foreach ($all_students_for_dropdown as $student): ?>
                        <option value="<?= htmlspecialchars($student['lrn']) ?>" label="<?= $student['name'] ?> (LRN: <?= $student['lrn'] ?>)"></option>
                    <?php endforeach; ?>
                </datalist>
              </div>

              <div class="mb-3">
                <label for="add_grade_level_input" class="form-label">Grade Level *</label>
                <!-- Datalist for grade levels -->
                <input type="text" list="add_grade_level_list" id="add_grade_level_input" name="grade_level" class="form-control" placeholder="Type or select grade level" required>
                <datalist id="add_grade_level_list">
                    <?php 
                      // Dynamically load grade levels from existing sections
                      $stmt_grades = $conn->prepare("SELECT DISTINCT grade_level FROM sections ORDER BY grade_level");
                      if ($stmt_grades) {
                        $stmt_grades->execute();
                        $result_grades = $stmt_grades->get_result();
                        while ($row_grade = $result_grades->fetch_assoc()): 
                    ?>
                        <option value="<?= htmlspecialchars($row_grade['grade_level']) ?>"></option>
                    <?php 
                        endwhile;
                        $stmt_grades->close();
                      }
                    ?>
                </datalist>
                <small class="text-danger" id="add_grade_level_error" style="display: none;"></small>
              </div>

              <div class="mb-3">
                <label for="add_section_input" class="form-label">Section *</label>
                <!-- Datalist for sections, populated by JavaScript -->
                <input type="text" list="add_section_list" id="add_section_input" name="section_name" class="form-control" placeholder="Type or select section" required>
                <datalist id="add_section_list"></datalist>
                <small class="text-danger" id="add_section_error" style="display: none;"></small>
              </div>

              <div class="mb-3">
                <label for="school_year_add" class="form-label">School Year *</label>
                <input type="text" id="school_year_add" name="school_year" class="form-control" maxlength="9" required value="<?= getCurrentSchoolYear() ?>">
              </div>

              <div class="text-center">
                <button type="submit" name="add_enrollment" class="btn btn-primary px-4 py-2">Enroll Student</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Enrollment Modal -->
    <div class="modal fade" id="editEnrollmentModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Enrollment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form method="post" id="editEnrollmentForm">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" id="edit_enrollment_id" name="edit_enrollment_id">

              <div class="mb-3">
                <label for="edit_lrn_input" class="form-label">Student LRN *</label>
                <!-- Datalist for student LRN suggestions (reused from add modal) -->
                <input type="text" list="studentLrnList" id="edit_lrn_input" name="edit_lrn_input" class="form-control" placeholder="Enter LRN" required>
              </div>

              <div class="mb-3">
                <label for="edit_grade_level_input" class="form-label">Grade Level *</label>
                <!-- Datalist for grade levels (reused from add modal) -->
                <input type="text" list="edit_grade_level_list" id="edit_grade_level_input" name="edit_grade_level" class="form-control" placeholder="Type or select grade level" required>
                <datalist id="edit_grade_level_list">
                    <?php 
                      // Dynamically load grade levels from existing sections
                      $stmt_grades_edit = $conn->prepare("SELECT DISTINCT grade_level FROM sections ORDER BY grade_level");
                      if ($stmt_grades_edit) {
                        $stmt_grades_edit->execute();
                        $result_grades_edit = $stmt_grades_edit->get_result();
                        while ($row_grade_edit = $result_grades_edit->fetch_assoc()): 
                    ?>
                        <option value="<?= htmlspecialchars($row_grade_edit['grade_level']) ?>"></option>
                    <?php 
                        endwhile;
                        $stmt_grades_edit->close();
                      }
                    ?>
                </datalist>
                <small class="text-danger" id="edit_grade_level_error" style="display: none;"></small>
              </div>

              <div class="mb-3">
                <label for="edit_section_input" class="form-label">Section *</label>
                <!-- Datalist for sections, populated by JavaScript -->
                <input type="text" list="edit_section_list" id="edit_section_input" name="edit_section_name" class="form-control" placeholder="Type or select section" required>
                <datalist id="edit_section_list"></datalist>
                <small class="text-danger" id="edit_section_error" style="display: none;"></small>
              </div>

              <div class="mb-3">
                <label for="edit_school_year" class="form-label">School Year *</label>
                <input type="text" id="edit_school_year" name="edit_school_year" class="form-control" maxlength="9" required>
              </div>

              <div class="text-center">
                <button type="submit" name="edit_enrollment" class="btn btn-primary px-4 py-2">Save Changes</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Import Enrollment CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="importModalLabel">Import Enrollments from CSV</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-body">
              <div class="mb-3">
                <label for="enrollment_csvfile" class="form-label">Select CSV File *</label>
                <input type="file" name="enrollment_csvfile" id="enrollment_csvfile" class="form-control" accept=".csv" required>
                <small class="text-muted">Max 5MB | Required Columns: lrn, grade_level, section_name, school_year</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="import_enrollments_csv" class="btn btn-primary">Upload & Import</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      // JavaScript for dynamic section population and search functionality
      const allSectionsData = <?= $all_sections_json ?>; // Load sections data from PHP

      /**
       * Populates the section datalist based on the selected grade level.
       * @param {string} gradeLevel - The selected grade level.
       * @param {string} datalistId - The ID of the datalist element to populate.
       * @param {string} sectionInputId - The ID of the section input field.
       */
      function populateSectionList(gradeLevel, datalistId, sectionInputId) {
        const datalist = document.getElementById(datalistId);
        const sectionInput = document.getElementById(sectionInputId);
        const errorMsgId = datalistId.includes('add') ? 'add_section_error' : 'edit_section_error'; // Determine which error message to show

        datalist.innerHTML = ''; // Clear previous options
        sectionInput.value = ''; // Clear the input field
        document.getElementById(errorMsgId).style.display = 'none'; // Hide error message

        if (!gradeLevel) return; // Exit if no grade level is selected

        // Filter sections that match the selected grade level (case-insensitive)
        const filteredSections = allSectionsData.filter(
          s => s.grade_level.toLowerCase() === gradeLevel.toLowerCase()
        );

        // Display an error if no sections are found for the grade level
        if (filteredSections.length === 0) {
          document.getElementById(errorMsgId).textContent = `No sections available for "${gradeLevel}"`;
          document.getElementById(errorMsgId).style.display = 'block';
          return;
        }

        // Populate the datalist with matching section names
        filteredSections.forEach(section => {
          const option = document.createElement('option');
          option.value = section.section_name;
          datalist.appendChild(option);
        });
        // Set the section input value to the selected section (if any)
        document.getElementById(sectionInputId).value = filteredSections[0]?.section_name || '';
      }

      /**
       * Opens the edit enrollment modal and populates its fields.
       * @param {object} data - An object containing enrollment data.
       */
      function openEditEnrollmentModal(data) {
        // Populate hidden fields and input values
        document.getElementById('edit_enrollment_id').value = data.enrollment_id;
        document.getElementById('edit_lrn_input').value = data.lrn;
        document.getElementById('edit_school_year').value = data.school_year;
        document.getElementById('edit_grade_level_input').value = data.grade_level;

        // Populate sections based on the grade level and set the section input value
        populateSectionList(data.grade_level, 'edit_section_list', 'edit_section_input');
        document.getElementById('edit_section_input').value = data.section_name;

        // Show the Bootstrap modal
        const editModal = new bootstrap.Modal(document.getElementById('editEnrollmentModal'));
        editModal.show();
      }

      // Event listeners for dynamic section loading
      document.addEventListener('DOMContentLoaded', function() {
        // Listener for the 'Add Enrollment' modal
        const addGradeLevelInput = document.getElementById('add_grade_level_input');
        if (addGradeLevelInput) {
          addGradeLevelInput.addEventListener('input', () =>
            populateSectionList(addGradeLevelInput.value, 'add_section_list', 'add_section_input')
          );
        }

        // Listener for the 'Edit Enrollment' modal
        const editGradeLevelInput = document.getElementById('edit_grade_level_input');
        if (editGradeLevelInput) {
          editGradeLevelInput.addEventListener('input', () =>
            populateSectionList(editGradeLevelInput.value, 'edit_section_list', 'edit_section_input')
          );
        }

        // Clear modal state when 'Add Enrollment' modal is hidden
        document.getElementById('addEnrollmentModal').addEventListener('hidden.bs.modal', () => {
          document.getElementById('enrollmentForm').reset(); // Reset form fields
          document.getElementById('add_section_list').innerHTML = ''; // Clear section datalist
          document.getElementById('add_grade_level_error').style.display = 'none'; // Hide error messages
          document.getElementById('add_section_error').style.display = 'none';
        });

        // --- Search Functionality ---
        const searchInput = $('#searchEnrollment');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');

        // Filter table rows based on search input
        searchInput.on('input', function() {
          const term = $(this).val().toLowerCase().trim(); // Get search term
          term.length > 0 ? clearBtn.addClass('show') : clearBtn.removeClass('show'); // Show/hide clear button

          let visibleCount = 0;
          tableRows.each(function() {
            const rowText = $(this).text().toLowerCase(); // Get text content of the row
            if (rowText.includes(term)) {
              $(this).show(); // Show row if it matches search term
              visibleCount++;
            } else {
              $(this).hide(); // Hide row if it doesn't match
            }
          });

          // Display 'No results found' message if necessary
          if (visibleCount === 0 && term.length > 0 && $('#noResults').length === 0) {
            $('.custom-table tbody').append('<tr id="noResults"><td colspan="8" class="border px-3 py-2 text-center text-gray-500"><span class="material-symbols-outlined">search_off</span> No enrollments found matching your search</td></tr>');
          } else {
            $('#noResults').remove(); // Remove the message if results are found or search is cleared
          }
        });

        // Clear search input and reset table visibility
        clearBtn.on('click', function() {
          searchInput.val('');
          clearBtn.removeClass('show');
          tableRows.show(); // Show all rows
          $('#noResults').remove(); // Remove 'no results' message
          searchInput.focus(); // Set focus back to search input
        });

        // Automatically close success alert after 5 seconds
        setTimeout(() => {
          $(".alert").alert('close');
          // Remove the 'status' query parameter from the URL after closing the alert
          if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({ path: url.href }, '', url.href);
          }
        }, 5000);
      });
    </script>
  </body>
</html>