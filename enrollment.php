<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php'; // Your database connection file

// Create logs directory if needed for enrollment operations
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$enrollment_error_log = 'logs/enrollment_errors.txt';

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("Database connection failed. Please check the logs.");
}
$conn->set_charset("utf8");

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function validate_csrf_token() {
    if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, 'logs/enrollment_errors.txt');
        echo "<script>alert('Invalid security token. Please try again.'); location='enrollment.php';</script>";
        exit;
    }
}

/**
 * Function to get the current school year based on month.
 * Assumes school year starts in August.
 */
function getCurrentSchoolYear() {
    $currentMonth = (int)date('m');
    $currentYear = (int)date('Y');
    if ($currentMonth >= 8) {
        return $currentYear . '-' . ($currentYear + 1);
    } else {
        return ($currentYear - 1) . '-' . $currentYear;
    }
}

/**
 * Static available grade levels
 */
$available_grade_levels = [
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'
];

/**
 * Fetch students for LRN datalist suggestions
 */
$students_result_for_dropdown = $conn->query(
    "SELECT lrn, lastname, firstname, middlename FROM students ORDER BY lastname, firstname"
);
$all_students_for_dropdown = [];
if ($students_result_for_dropdown) {
    while ($row = $students_result_for_dropdown->fetch_assoc()) {
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
    error_log("Error fetching students for dropdown: " . $conn->error);
}

/**
 * Fetch all sections (for filtering and populating dropdowns)
 */
$sections_query = "
    SELECT
        s.section_id,
        s.section_name,
        s.grade_level,
        s.adviser_id,
        CONCAT_WS(' ', 
            adv.firstname,
            CASE WHEN adv.middlename IS NOT NULL AND adv.middlename != '' THEN CONCAT(LEFT(adv.middlename, 1),'.') ELSE NULL END,
            adv.lastname,
            CASE WHEN adv.suffix IS NOT NULL AND adv.suffix != '' THEN adv.suffix ELSE NULL END
        ) AS adviser_fullname
    FROM sections s
    LEFT JOIN advisers adv ON s.adviser_id = adv.adviser_id
    ORDER BY s.grade_level ASC, s.section_name ASC
";
$sections_result_all = $conn->query($sections_query);
$all_sections_for_js = [];
if ($sections_result_all) {
    while ($row = $sections_result_all->fetch_assoc()) {
        $all_sections_for_js[] = $row;
    }
} else {
    error_log("Warning: Could not load sections for dropdowns. " . $conn->error);
}
$all_sections_json = json_encode($all_sections_for_js);

/**
 * Handle Add Enrollment
 */
if (isset($_POST['add_enrollment'])) {
    validate_csrf_token();
    // Sanitize inputs
    $lrn         = htmlspecialchars(trim($_POST['lrn']), ENT_QUOTES, 'UTF-8');
    $grade_level = htmlspecialchars(trim($_POST['grade_level']), ENT_QUOTES, 'UTF-8');
    $section_id  = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);
    $school_year = htmlspecialchars(trim($_POST['school_year']), ENT_QUOTES, 'UTF-8');

    if (empty($lrn) || empty($grade_level) || $section_id === false || $section_id === null || empty($school_year)) {
        echo "<script>alert('All required fields must be filled (LRN, Grade Level, Section, School Year).'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Check if student exists
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found. Please add the student first.");
        }
        $stmt->close();

        // Check if section exists
        $stmt = $conn->prepare("SELECT 1 FROM sections WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("Selected section not found.");
        }
        $stmt->close();

        // Prevent duplicate enrollment for same student and school year
        $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE lrn = ? AND school_year = ?");
        $stmt->bind_param("ss", $lrn, $school_year);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("This student is already enrolled for the specified school year.");
        }
        $stmt->close();

        // Insert enrollment
        $stmt = $conn->prepare("INSERT INTO enrollments (lrn, grade_level, section_id, school_year) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $lrn, $grade_level, $section_id, $school_year);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add enrollment: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo "<script>alert('Enrollment added successfully!'); window.location.href='enrollment.php?status=added';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error adding enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle Delete Enrollment
 */
if (isset($_POST['delete_enrollment'])) {
    validate_csrf_token();
    $enrollment_id = filter_var($_POST['enrollment_id'], FILTER_VALIDATE_INT);
    if ($enrollment_id === false || $enrollment_id === null) {
        echo "<script>alert('Invalid Enrollment ID.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        $stmt->bind_param("i", $enrollment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete enrollment: " . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        echo "<script>alert('Enrollment deleted successfully!'); window.location.href='enrollment.php?status=deleted';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error deleting enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle Edit Enrollment
 */
if (isset($_POST['edit_enrollment'])) {
    validate_csrf_token();
    $enrollment_id = filter_var($_POST['edit_enrollment_id'], FILTER_VALIDATE_INT);
    $lrn           = htmlspecialchars(trim($_POST['edit_lrn_input']), ENT_QUOTES, 'UTF-8');
    $grade_level   = htmlspecialchars(trim($_POST['edit_grade_level']), ENT_QUOTES, 'UTF-8');
    $section_id    = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT);
    $school_year   = htmlspecialchars(trim($_POST['edit_school_year']), ENT_QUOTES, 'UTF-8');

    if (
        $enrollment_id === false || $enrollment_id === null ||
        empty($lrn) || empty($grade_level) ||
        $section_id === false || $section_id === null || empty($school_year)
    ) {
        echo "<script>alert('Invalid ID or missing fields.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Check student exists
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found.");
        }
        $stmt->close();

        // Check section exists
        $stmt = $conn->prepare("SELECT 1 FROM sections WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("Selected section not found.");
        }
        $stmt->close();

        // Prevent duplicate enrollment (for a different enrollment_id)
        $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE lrn = ? AND school_year = ? AND enrollment_id != ?");
        $stmt->bind_param("ssi", $lrn, $school_year, $enrollment_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("Another enrollment for this student exists for this school year.");
        }
        $stmt->close();

        // Update enrollment record
        $stmt = $conn->prepare("UPDATE enrollments SET lrn = ?, grade_level = ?, section_id = ?, school_year = ? WHERE enrollment_id = ?");
        $stmt->bind_param("ssisi", $lrn, $grade_level, $section_id, $school_year, $enrollment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update enrollment: " . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        echo "<script>alert('Enrollment updated successfully!'); window.location.href='enrollment.php?status=updated';</script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle CSV Import for Enrollments
 * Expect CSV columns: lrn, grade_level, section_id, school_year
 */
function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}
if (isset($_POST['import_enrollments_csv']) && isset($_FILES['enrollment_csvfile']) && $_FILES['enrollment_csvfile']['error'] === UPLOAD_ERR_OK) {
    $tmpPath  = $_FILES['enrollment_csvfile']['tmp_name'];
    $fileName = $_FILES['enrollment_csvfile']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        echo "<script>alert('❌ Please upload a CSV file.');window.location.href='enrollment.php';</script>";
        exit;
    }
    if ($_FILES['enrollment_csvfile']['size'] > 5 * 1024 * 1024) {
        echo "<script>alert('❌ File size exceeds 5MB limit.');window.location.href='enrollment.php';</script>";
        exit;
    }
    if ($conn->connect_error) {
        error_log(date('c') . " DB Conn Error during CSV import: " . $conn->connect_error . "\n", 3, $enrollment_error_log);
        echo "<script>alert('Database connection lost. Cannot import CSV.');window.location.href='enrollment.php';</script>";
        exit;
    }
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");
        $expected_headers = ['lrn', 'grade_level', 'section_id', 'school_year'];
        if (!is_array($header) || count($header) < count($expected_headers)) {
            fclose($handle);
            echo "<script>alert('❌ CSV must have these columns: lrn, grade_level, section_id, school_year');window.location.href='enrollment.php';</script>";
            exit;
        }
        // Map columns to indices
        $col_map = [];
        foreach ($expected_headers as $expected_header) {
            $found = false;
            foreach ($header as $h_idx => $h_val) {
                if (strtolower(trim($h_val)) === $expected_header) {
                    $col_map[$expected_header] = $h_idx;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                fclose($handle);
                echo "<script>alert('❌ CSV header is missing required column: \"{$expected_header}\".');window.location.href='enrollment.php';</script>";
                exit;
            }
        }
        $stmt = $conn->prepare("INSERT INTO enrollments (lrn, grade_level, section_id, school_year) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            fclose($handle);
            error_log(date('c') . " PREPARE ERR (CSV Import): " . $conn->error . "\n", 3, $enrollment_error_log);
            echo "<script>alert('❌ Database error preparing CSV import.');window.location.href='enrollment.php';</script>";
            exit;
        }
        $rowCount = 0;
        $errors   = [];
        $row_num  = 1;
        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < count($expected_headers)) {
                continue;
            }
            $lrn_csv         = safeTrimCSV($data[$col_map['lrn']] ?? '');
            $grade_level_csv = safeTrimCSV($data[$col_map['grade_level']] ?? '');
            $section_id_csv  = safeTrimCSV($data[$col_map['section_id']] ?? '');
            $school_year_csv = safeTrimCSV($data[$col_map['school_year']] ?? '');
            $valid_row = true;
            if (empty($lrn_csv) || empty($grade_level_csv) || empty($section_id_csv) || empty($school_year_csv)) {
                $errors[] = "Row {$row_num}: Missing required fields.";
                $valid_row = false;
            }
            $section_id_val = (int)$section_id_csv;
            // Check if student exists
            if ($valid_row) {
                $stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("s", $lrn_csv);
                    $stmt_check->execute();
                    $result = $stmt_check->get_result()->fetch_assoc();
                    if ($result['count'] == 0) {
                        $errors[] = "Row {$row_num}: LRN '{$lrn_csv}' not found in student records.";
                        $valid_row = false;
                    }
                    $stmt_check->close();
                } else {
                    $errors[] = "Row {$row_num}: DB error during student check.";
                    $valid_row = false;
                }
            }
            // Check if section exists
            if ($valid_row) {
                $stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM sections WHERE section_id = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("i", $section_id_val);
                    $stmt_check->execute();
                    $result = $stmt_check->get_result()->fetch_assoc();
                    if ($result['count'] == 0) {
                        $errors[] = "Row {$row_num}: Section ID '{$section_id_csv}' not found.";
                        $valid_row = false;
                    }
                    $stmt_check->close();
                } else {
                    $errors[] = "Row {$row_num}: DB error during section check.";
                    $valid_row = false;
                }
            }
            // Prevent duplicate enrollment
            if ($valid_row) {
                $stmt_check = $conn->prepare("SELECT COUNT(*) AS count FROM enrollments WHERE lrn = ? AND school_year = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("ss", $lrn_csv, $school_year_csv);
                    $stmt_check->execute();
                    $result = $stmt_check->get_result()->fetch_assoc();
                    if ($result['count'] > 0) {
                        $errors[] = "Row {$row_num}: Enrollment already exists for LRN '{$lrn_csv}' and school year.";
                        $valid_row = false;
                    }
                    $stmt_check->close();
                } else {
                    $errors[] = "Row {$row_num}: DB error during duplicate check.";
                    $valid_row = false;
                }
            }
            if ($valid_row) {
                $stmt->bind_param("ssis", $lrn_csv, $grade_level_csv, $section_id_val, $school_year_csv);
                if (!$stmt->execute()) {
                    $errors[] = "Row {$row_num}: DB error during insert.";
                } else {
                    $rowCount++;
                }
            }
        }
        fclose($handle);
        $stmt->close();
        $message = "✓ CSV Import Complete\nSuccessfully imported: {$rowCount} enrollment records\n";
        if (!empty($errors)) {
            $message .= "\n❌ Errors (" . count($errors) . " rows failed):\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        echo "<script>alert(" . json_encode($message) . ");window.location.href='enrollment.php';</script>";
        exit();
    } else {
        echo "<script>alert('❌ Failed to open CSV file. Please check file permissions or file integrity.');window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Fetch enrollment records for display
 */
$enrollments_query = "
    SELECT
        e.enrollment_id, e.lrn, e.grade_level, e.school_year, e.section_id,
        COALESCE(st.firstname, 'N/A') AS student_firstname,
        COALESCE(st.middlename, '') AS student_middlename,
        COALESCE(st.lastname, 'N/A') AS student_lastname,
        COALESCE(sec.section_name, 'N/A') AS section_name,
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
    LEFT JOIN sections sec ON e.section_id = sec.section_id
    LEFT JOIN advisers adv ON sec.adviser_id = adv.adviser_id
    ORDER BY e.school_year DESC, e.grade_level ASC, sec.section_name ASC, st.lastname, st.firstname
";
$enrollments_result = $conn->query($enrollments_query);
if (!$enrollments_result) {
    error_log("Error fetching enrollments: " . $conn->error);
    $enrollments_result = (object)[
        'num_rows' => 0,
        'fetch_assoc' => function(){ return null; }
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
    <!-- Custom fonts and styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
      @keyframes hl {
        0% { background-color: #c8e6c9; }
        100% { background-color: transparent; }
      }
      .highlight { animation: hl 2s forwards; }
      .table-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        width: 100%;
        box-shadow: 0 12px 30px rgba(15,23,42,0.08);
      }
      .table-responsive-custom { overflow-x: auto; }
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
      .actions-cell {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        min-width: 80px;
      }
      .action-icon-btn {
        border: none;
        background: none;
        padding: 0;
        margin: 0 2px;
        cursor: pointer;
        transition: color 0.2s, opacity 0.2s;
      }
      .action-icon-btn .material-symbols-outlined {
        font-size: 1.1em;
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
      .action-icon-btn.edit-icon .material-symbols-outlined { color: #1c74e4; }
      .action-icon-btn.delete-icon .material-symbols-outlined { color: #dc3545; }
      .action-icon-btn:hover .material-symbols-outlined { opacity: 0.7; }
      .page-title-with-logo { display: flex; align-items: center; gap: 12px; }
      .page-logo {
        width: 45px;
        height: 45px;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      .search-box {
        position: relative;
        display: inline-block;
        margin-right: 10px;
      }
      .search-box input {
        padding: 0.5rem 2.5rem 0.5rem 1rem;
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
        pointer-events: none;
      }
      .clear-search {
        position: absolute;
        right: 2.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        display: none;
      }
      .clear-search.show { display: block; }
    </style>
  </head>
  <body id="page-top">
    <?php include 'nav.php'; ?>
    <div id="wrapper">
      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
              <div class="page-title-with-logo">
                <img src="img/depedlogo.jpg" alt="School Logo" class="page-logo">
                <h2 class="h3 mb-0 text-gray-800">Enrollment Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <div class="search-box">
                  <input type="text" id="searchEnrollment" placeholder="Search Enrollment" autocomplete="off">
                  <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                    <span class="material-symbols-outlined">close</span>
                  </button>
                  <span class="search-icon">
                    <span class="material-symbols-outlined">search</span>
                  </span>
                </div>
                <!-- CSV Import Button -->
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined">upload_file</span> Import CSV
                </button>
                <!-- Add Enrollment Button -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined">person_add</span> Add Enrollment
                </button>
              </div>
            </div>

            <!-- Status Alerts -->
            <?php if (isset($_GET['status'])): ?>
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                  switch ($_GET['status']) {
                    case 'added':   echo 'Enrollment added successfully!'; break;
                    case 'updated': echo 'Enrollment updated successfully!'; break;
                    case 'deleted': echo 'Enrollment deleted successfully!'; break;
                  }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>

            <!-- Enrollment Table -->
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
                      $counter = 1;
                      if ($enrollments_result->num_rows > 0):
                        while ($row = $enrollments_result->fetch_assoc()):
                          $fn = $row['student_firstname'];
                          $mn = $row['student_middlename'];
                          $ln = $row['student_lastname'];
                          $student_fullname = htmlspecialchars($ln);
                          if (!empty($fn) && $fn !== 'N/A') {
                              $student_fullname .= ', ' . htmlspecialchars($fn);
                          }
                          if (!empty($mn)) {
                              $student_fullname .= ' ' . htmlspecialchars(substr($mn, 0, 1)) . '.';
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
                        <button onclick='openEditEnrollmentModal(<?= json_encode([
                          "enrollment_id" => $row["enrollment_id"],
                          "lrn" => $row["lrn"],
                          "grade_level" => $row["grade_level"],
                          "section_id" => $row["section_id"],
                          "school_year" => $row["school_year"]
                        ]) ?>)' class="action-icon-btn edit-icon" title="Edit Enrollment">
                          <span class="material-symbols-outlined">edit</span>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Remove enrollment for <?= $student_fullname ?>?');">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <input type="hidden" name="enrollment_id" value="<?= htmlspecialchars($row['enrollment_id']) ?>">
                          <button type="submit" name="delete_enrollment" class="action-icon-btn delete-icon" title="Delete Enrollment">
                            <span class="material-symbols-outlined">delete</span>
                          </button>
                        </form>
                      </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                      <td colspan="8" class="border px-3 py-2 text-center text-gray-500">No enrollment records found.</td>
                    </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div><!-- container-fluid -->
        </div><!-- content -->

        <?php include 'footer.php'; ?>
      </div><!-- content-wrapper -->
    </div><!-- wrapper -->

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
                <input type="text" id="lrn_input" name="lrn" class="form-control" list="student_lrns" placeholder="Type LRN or select from list" required>
                <datalist id="student_lrns">
                  <?php foreach ($all_students_for_dropdown as $student): ?>
                    <option value="<?= htmlspecialchars($student['lrn']) ?>"><?= htmlspecialchars($student['name']) ?></option>
                  <?php endforeach; ?>
                </datalist>
              </div>
              <div class="mb-3">
                <label for="add_grade_level" class="form-label">Grade Level *</label>
                <select id="add_grade_level" name="grade_level" class="form-control" required onchange="populateSections('add_grade_level', 'add_section_id')">
                  <option value="">Select Grade Level</option>
                  <?php foreach ($available_grade_levels as $lvl): ?>
                    <option value="<?= htmlspecialchars($lvl) ?>"><?= htmlspecialchars($lvl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="add_section_id" class="form-label">Section *</label>
                <select id="add_section_id" name="section_id" class="form-control" required>
                  <option value="">Select Grade Level first</option>
                </select>
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
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <input type="hidden" id="edit_enrollment_id" name="edit_enrollment_id">
              <div class="mb-3">
                <label for="edit_lrn_input" class="form-label">Student LRN *</label>
                <input type="text" id="edit_lrn_input" name="edit_lrn_input" class="form-control" list="student_lrns" required>
              </div>
              <div class="mb-3">
                <label for="edit_grade_level" class="form-label">Grade Level *</label>
                <select id="edit_grade_level" name="edit_grade_level" class="form-control" required onchange="populateSections('edit_grade_level', 'edit_section_id')">
                  <?php foreach ($available_grade_levels as $lvl): ?>
                    <option value="<?= htmlspecialchars($lvl) ?>"><?= htmlspecialchars($lvl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="edit_section_id" class="form-label">Section *</label>
                <select id="edit_section_id" name="edit_section_id" class="form-control" required>
                  <option value="">Select Grade Level first</option>
                </select>
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
                <small class="text-muted">Max 5MB | Columns: lrn, grade_level, section_id, school_year</small>
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
      // Pass sections data to JavaScript
      const allSectionsData = <?= $all_sections_json ?>;

      /**
       * Populate section dropdown based on selected grade level.
       * Optionally pre-select a section if selectedSectionId is provided.
       */
      function populateSections(gradeLevelSelectId, sectionSelectId, selectedSectionId = null) {
        const gradeLevel = document.getElementById(gradeLevelSelectId).value;
        const sectionSelect = document.getElementById(sectionSelectId);
        sectionSelect.innerHTML = '<option value="">Select Section</option>';
        if (gradeLevel) {
          const filteredSections = allSectionsData.filter(section => section.grade_level === gradeLevel);
          filteredSections.sort((a, b) => a.section_name.localeCompare(b.section_name));
          filteredSections.forEach(section => {
            const option = document.createElement('option');
            option.value = section.section_id;
            const adviserDisplay = section.adviser_fullname && section.adviser_fullname !== 'N/A' ? ` (Adviser: ${section.adviser_fullname})` : '';
            option.textContent = `${section.section_name}${adviserDisplay}`;
            if (selectedSectionId !== null && +section.section_id === +selectedSectionId) {
              option.selected = true;
            }
            sectionSelect.appendChild(option);
          });
        }
      }

      /**
       * Populate Edit Enrollment Modal fields.
       */
      function openEditEnrollmentModal(data) {
        document.getElementById('edit_enrollment_id').value = data.enrollment_id;
        document.getElementById('edit_lrn_input').value = data.lrn;
        document.getElementById('edit_school_year').value = data.school_year;
        document.getElementById('edit_grade_level').value = data.grade_level;
        populateSections('edit_grade_level', 'edit_section_id', data.section_id);
        var editModal = new bootstrap.Modal(document.getElementById('editEnrollmentModal'));
        editModal.show();
      }

      // Reset Add Enrollment form on modal close
      document.getElementById('addEnrollmentModal').addEventListener('hidden.bs.modal', function (){
        document.getElementById('enrollmentForm').reset();
        document.getElementById('add_section_id').innerHTML = '<option value="">Select Grade Level first</option>';
      });

      // Search functionality for enrollment table
      $(document).ready(function(){
        const searchInput = $('#searchEnrollment');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');
        searchInput.on('input', function(){
          const term = $(this).val().toLowerCase().trim();
          if (term.length > 0) { clearBtn.addClass('show'); } else { clearBtn.removeClass('show'); }
          let visibleCount = 0;
          tableRows.each(function(){
            const rowText = $(this).text().toLowerCase();
            if (rowText.includes(term)) { $(this).show(); visibleCount++; }
            else { $(this).hide(); }
          });
          if (visibleCount === 0 && term.length > 0 && $('#noResults').length === 0) {
            $('.custom-table tbody').append('<tr id="noResults"><td colspan="8" class="border px-3 py-2 text-center text-gray-500"><span class="material-symbols-outlined" style="font-size:1.2rem;">search_off</span> No enrollments found matching "' + term + '"</td></tr>');
          } else { $('#noResults').remove(); }
        });
        clearBtn.on('click', function(){
          searchInput.val('');
          clearBtn.removeClass('show');
          tableRows.show();
          $('#noResults').remove();
          searchInput.focus();
        });
        searchInput.on('keydown', function(e){ if(e.key==='Escape'){ clearBtn.click(); } });

        // Auto-dismiss alerts and clean URL
        setTimeout(function(){
          $(".alert").alert('close');
          if (window.history.replaceState){
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({path: url.href}, '', url.href);
          }
        }, 5000);
      });
    </script>
  </body>
</html>