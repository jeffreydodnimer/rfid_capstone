<?php
session_start();
// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php'; // Assuming this file correctly connects to your database

// Define log directory and error log for guardian operations
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$guardian_error_log = 'logs/guardian_errors.txt';

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("Database connection failed. Please check the logs.");
}

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- CSRF Token Validation Function ---
function validate_csrf_token() {
    if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, 'logs/guardian_errors.txt');
        echo "<script>alert('Invalid security token. Please try again.');location='guardian.php'</script>";
        exit;
    }
}

// --- Fetch Students for LRN Datalist/Dropdown ---
$students_for_datalist = [];
$students_query_dl = $conn->query("SELECT lrn, firstname, lastname, middlename FROM students ORDER BY lastname, firstname");
if ($students_query_dl) {
    while ($s_dl = $students_query_dl->fetch_assoc()) {
        $fullName = htmlspecialchars($s_dl['lastname'] . ', ' . $s_dl['firstname']);
        if (!empty($s_dl['middlename'])) {
            $fullName .= ' ' . htmlspecialchars(substr($s_dl['middlename'], 0, 1)) . '.';
        }
        $students_for_datalist[] = [
            'lrn' => $s_dl['lrn'],
            'display_name' => $fullName // Display text for the datalist option
        ];
    }
} else {
    error_log(date('c') . " Query Error (students_for_datalist): " . $conn->error . "\n", 3, $guardian_error_log);
}

// --- Fetch Existing Student-Guardian Relationships for JS Validation ---
$existing_relationships_query = $conn->prepare("SELECT lrn, relationship_to_student FROM guardians");
$student_relationships = [];
if ($existing_relationships_query && $existing_relationships_query->execute()) {
    $result = $existing_relationships_query->get_result();
    while ($rel = $result->fetch_assoc()) {
        $lrn = $rel['lrn'];
        $relationship = strtolower($rel['relationship_to_student']);
        if (!isset($student_relationships[$lrn])) {
            $student_relationships[$lrn] = [];
        }
        $student_relationships[$lrn][] = $relationship;
    }
} else {
    error_log(date('c') . " Prepare/Execute Error (existing_relationships): " . $conn->error . "\n", 3, $guardian_error_log);
}
$existing_relationships_query->close();


// --- Add Guardian ---
if (isset($_POST['add_guardian'])) {
    validate_csrf_token();

    // Sanitize and validate inputs
    $lrn_raw       = filter_var($_POST['lrn'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $lrn_val       = (int)$lrn_raw;
    $lastname      = htmlspecialchars(trim($_POST['lastname'] ?? ''), ENT_QUOTES);
    $firstname     = htmlspecialchars(trim($_POST['firstname'] ?? ''), ENT_QUOTES);
    $middlename    = htmlspecialchars(trim($_POST['middlename'] ?? ''), ENT_QUOTES);
    $suffix        = htmlspecialchars(trim($_POST['suffix'] ?? ''), ENT_QUOTES);
    $contact_raw   = filter_var($_POST['contact'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $rel           = htmlspecialchars(trim($_POST['relationship_to_student'] ?? ''), ENT_QUOTES);

    // Required field validation
    if (!$lrn_val || !$lastname || !$firstname || !$contact_raw || !$rel) {
        echo "<script>alert('Please fill in all required fields.');location='guardian.php'</script>";
        exit;
    }

    // Validate Contact Number - MUST be exactly 10 digits
    if (strlen($contact_raw) !== 10 || !ctype_digit($contact_raw)) {
        echo "<script>alert('Contact number must be exactly 10 digits long and contain only numbers.');location='guardian.php'</script>";
        exit;
    }

    // Check if the provided LRN exists in the students table
    $checkLrnExists = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
    if (!$checkLrnExists) {
        error_log(date('c') . " PREPARE ERR (checkLrnExists): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare LRN existence check.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExists->bind_param("i", $lrn_val);
    $checkLrnExists->execute();
    $lrnResult = $checkLrnExists->get_result()->fetch_assoc();
    if ($lrnResult['count'] == 0) {
        echo "<script>alert('LRN does not exist in the student records. Please register the student first.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExists->close();

    // Check that no guardian is already assigned for the given LRN
    $checkDuplicate = $conn->prepare("SELECT COUNT(*) AS count FROM guardians WHERE lrn = ?");
    if (!$checkDuplicate) {
        error_log(date('c') . " PREPARE ERR (checkDuplicate): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare duplicate check.');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicate->bind_param("i", $lrn_val);
    $checkDuplicate->execute();
    $duplicateResult = $checkDuplicate->get_result()->fetch_assoc();
    if ($duplicateResult['count'] > 0) {
        echo "<script>alert('A guardian is already assigned to the student with LRN {$lrn_val}.');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicate->close();

    // Insert new guardian record
    $guardianInsert = $conn->prepare("INSERT INTO guardians (lrn, lastname, firstname, middlename, suffix, contact_number, relationship_to_student) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$guardianInsert) {
        error_log(date('c') . " PREPARE ERR (guardianInsert): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare guardian insert.');location='guardian.php'</script>";
        exit;
    }
    $guardianInsert->bind_param("issssis", $lrn_val, $lastname, $firstname, $middlename, $suffix, $contact_raw, $rel);
    if ($guardianInsert->execute()) {
        header("Location: guardian.php?status=added&lrn=" . $lrn_val);
        exit;
    } else {
        error_log(date('c') . " INSERT ERR (guardianInsert): " . $guardianInsert->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error during guardian insert: " . $guardianInsert->error . "');location='guardian.php'</script>";
        exit;
    }
    $guardianInsert->close();
}

// --- Delete Guardian ---
if (isset($_POST['delete_guardian'])) {
    validate_csrf_token();
    $id     = filter_var($_POST['guardian_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $id_val = (int)$id;

    if (!$id_val) {
        echo "<script>alert('Invalid guardian ID.');location='guardian.php'</script>";
        exit;
    }

    $del = $conn->prepare("DELETE FROM guardians WHERE guardian_id=?");
    if (!$del) {
        error_log(date('c') . " PREPARE ERR (delete_guardian): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare guardian delete.');location='guardian.php'</script>";
        exit;
    }
    $del->bind_param("i", $id_val);
    if ($del->execute()) {
        if ($del->affected_rows > 0) {
            header("Location: guardian.php?status=deleted");
        } else {
            echo "<script>alert('Guardian ID not found or already deleted.');location='guardian.php'</script>";
        }
        exit;
    } else {
        error_log(date('c') . " EXECUTE ERR (delete_guardian): " . $del->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('Delete failed: " . $del->error . "');location='guardian.php'</script>";
        exit;
    }
    $del->close();
}

// --- Edit Guardian ---
if (isset($_POST['edit_guardian'])) {
    validate_csrf_token();
    $id     = filter_var($_POST['edit_guardian_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $id_val = (int)$id;

    $lrn_raw       = filter_var($_POST['lrn'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $lrn_val       = (int)$lrn_raw;
    $lastname      = htmlspecialchars(trim($_POST['lastname'] ?? ''), ENT_QUOTES);
    $firstname     = htmlspecialchars(trim($_POST['firstname'] ?? ''), ENT_QUOTES);
    $middlename    = htmlspecialchars(trim($_POST['middlename'] ?? ''), ENT_QUOTES);
    $suffix        = htmlspecialchars(trim($_POST['suffix'] ?? ''), ENT_QUOTES);
    $contact_raw   = filter_var($_POST['contact'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $rel           = htmlspecialchars(trim($_POST['relationship_to_student'] ?? ''), ENT_QUOTES);

    if (!$id_val || !$lrn_val || !$lastname || !$firstname || !$contact_raw || !$rel) {
        echo "<script>alert('Please fill in all required fields');location='guardian.php'</script>";
        exit;
    }

    // Validate Contact Number (must be 10 digits)
    if (strlen($contact_raw) !== 10 || !ctype_digit($contact_raw)) {
        echo "<script>alert('Contact number must be exactly 10 digits long and contain only numbers.');location='guardian.php'</script>";
        exit;
    }

    // Check if the new LRN exists in students table
    $checkLrnExistsEdit = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
    if (!$checkLrnExistsEdit) {
        error_log(date('c') . " PREPARE ERR (checkLrnExistsEdit): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare LRN check during edit.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExistsEdit->bind_param("i", $lrn_val);
    $checkLrnExistsEdit->execute();
    $lrnResultEdit = $checkLrnExistsEdit->get_result()->fetch_assoc();
    if ($lrnResultEdit['count'] == 0) {
        echo "<script>alert('LRN does not exist in the student records. Please register the student first.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExistsEdit->close();

    // Check for duplicate guardian assignment on a different record
    $checkDuplicateEdit = $conn->prepare("SELECT COUNT(*) AS count FROM guardians WHERE lrn = ? AND guardian_id != ?");
    if (!$checkDuplicateEdit) {
        error_log(date('c') . " PREPARE ERR (checkDuplicateEdit): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare duplicate check for edit.');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicateEdit->bind_param("ii", $lrn_val, $id_val);
    $checkDuplicateEdit->execute();
    $duplicateEditResult = $checkDuplicateEdit->get_result()->fetch_assoc();
    if ($duplicateEditResult['count'] > 0) {
        echo "<script>alert('The student with LRN {$lrn_val} is already assigned to a different guardian.');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicateEdit->close();

    // Update the guardian record
    $updateGuardian = $conn->prepare(
        "UPDATE guardians
         SET lrn = ?, lastname = ?, firstname = ?, middlename = ?, suffix = ?, contact_number = ?, relationship_to_student = ?
         WHERE guardian_id = ?"
    );
    if (!$updateGuardian) {
        error_log(date('c') . " PREPARE ERR (updateGuardian): " . $conn->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('DB Error: Could not prepare guardian update.');location='guardian.php'</script>";
        exit;
    }
    $updateGuardian->bind_param("issssisi", $lrn_val, $lastname, $firstname, $middlename, $suffix, $contact_raw, $rel, $id_val);
    if ($updateGuardian->execute()) {
        header("Location: guardian.php?status=updated&lrn=" . $lrn_val);
        exit;
    } else {
        error_log(date('c') . " EXECUTE ERR (updateGuardian): " . $updateGuardian->error . "\n", 3, $guardian_error_log);
        echo "<script>alert('Update failed: " . $updateGuardian->error . "');location='guardian.php'</script>";
        exit;
    }
    $updateGuardian->close();
}

define('GUARDIAN_UPLOAD_DIR', __DIR__ . '/uploads/guardians/');
if (!file_exists(GUARDIAN_UPLOAD_DIR)) {
    mkdir(GUARDIAN_UPLOAD_DIR, 0755, true);
}

function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

if (isset($_POST['import_guardians_csv']) && isset($_FILES['guardian_csvfile']) && $_FILES['guardian_csvfile']['error'] === UPLOAD_ERR_OK) {

    $tmpPath  = $_FILES['guardian_csvfile']['tmp_name'];
    $fileName = $_FILES['guardian_csvfile']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        echo "<script>alert('❌ Please upload a CSV file.');location='guardian.php'</script>";
        exit;
    }
    if ($_FILES['guardian_csvfile']['size'] > 5 * 1024 * 1024) { // max 5MB
        echo "<script>alert('❌ File size exceeds 5MB limit.');location='guardian.php'</script>";
        exit;
    }
    if ($conn->connect_error) {
        error_log(date('c') . " DB Conn Error during CSV import: " . $conn->connect_error . "\n", 3, $guardian_error_log);
        echo "<script>alert('Database connection lost. Cannot import CSV.');location='guardian.php'</script>";
        exit;
    }

    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");
        // Expected CSV header columns (case-sensitive here)
        $expected_headers = ['lrn', 'lastname', 'firstname', 'middlename', 'suffix', 'contactnumber', 'relationship_to_student'];
        if (!is_array($header) || count($header) < count($expected_headers)) {
            fclose($handle);
            echo "<script>alert('❌ CSV must have at least these columns: LRN, Lastname, Firstname, Middlename, Suffix, ContactNumber, Relationship');location='guardian.php'</script>";
            exit;
        }
        // Map column names to indices
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
                echo "<script>alert('❌ CSV header is missing required column: \"{$expected_header}\".');location='guardian.php'</script>";
                exit;
            }
        }

        $stmt = $conn->prepare("INSERT INTO guardians (lrn, lastname, firstname, middlename, suffix, contact_number, relationship_to_student) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            fclose($handle);
            error_log(date('c') . " PREPARE ERR (CSV Import): " . $conn->error . "\n", 3, $guardian_error_log);
            echo "<script>alert('❌ Database error preparing statement for import. Please check logs.');location='guardian.php'</script>";
            exit;
        }

        $rowCount = 0;
        $errors   = [];
        $row_num  = 1; // Data rows start after header

        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < count($expected_headers)) {
                continue;
            }
            $lrn_raw     = safeTrimCSV($data[$col_map['lrn']] ?? '');
            $lastname    = htmlspecialchars(trim(safeTrimCSV($data[$col_map['lastname']] ?? '')), ENT_QUOTES);
            $firstname   = htmlspecialchars(trim(safeTrimCSV($data[$col_map['firstname']] ?? '')), ENT_QUOTES);
            $middlename  = htmlspecialchars(trim(safeTrimCSV($data[$col_map['middlename']] ?? '')), ENT_QUOTES);
            $suffix      = htmlspecialchars(trim(safeTrimCSV($data[$col_map['suffix']] ?? '')), ENT_QUOTES);
            $contact_raw = safeTrimCSV($data[$col_map['contactnumber']] ?? '');
            $rel         = htmlspecialchars(trim(safeTrimCSV($data[$col_map['relationship_to_student']] ?? '')), ENT_QUOTES);

            $valid_row = true;
            // Validate LRN: exactly 12 digits
            if (empty($lrn_raw) || strlen($lrn_raw) !== 12 || !ctype_digit($lrn_raw)) {
                $errors[] = "Row {$row_num}: Invalid LRN '{$lrn_raw}'. Must be 12 digits.";
                $valid_row = false;
            } else {
                $lrn_val = (int)$lrn_raw;
            }
            // Validate Contact: exactly 10 digits
            if ($valid_row && (empty($contact_raw) || strlen($contact_raw) !== 10 || !ctype_digit($contact_raw))) {
                $errors[] = "Row {$row_num}: Invalid Contact Number '{$contact_raw}'. Must be 10 digits.";
                $valid_row = false;
            }
            if ($valid_row && (!$lrn_val || !$lastname || !$firstname || !$contact_raw || !$rel)) {
                $errors[] = "Row {$row_num}: Missing required fields (LRN, Lastname, Firstname, Contact, Relationship).";
                $valid_row = false;
            }
            // Check student's existence
            if ($valid_row) {
                $checkLrnExists = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
                if (!$checkLrnExists) {
                    error_log(date('c') . " PREPARE ERR (CSV LRN Check): " . $conn->error . "\n", 3, $guardian_error_log);
                    $errors[] = "Row {$row_num}: Database error during LRN check.";
                    $valid_row = false;
                } else {
                    $checkLrnExists->bind_param("i", $lrn_val);
                    $checkLrnExists->execute();
                    $lrnResult = $checkLrnExists->get_result()->fetch_assoc();
                    if ($lrnResult['count'] == 0) {
                        $errors[] = "Row {$row_num}: LRN '{$lrn_val}' not found in student records.";
                        $valid_row = false;
                    }
                    $checkLrnExists->close();
                }
            }
            // Check duplicate guardian records
            if ($valid_row) {
                $checkDuplicate = $conn->prepare("SELECT COUNT(*) AS count FROM guardians WHERE lrn = ?");
                if (!$checkDuplicate) {
                    error_log(date('c') . " PREPARE ERR (CSV Duplicate Check): " . $conn->error . "\n", 3, $guardian_error_log);
                    $errors[] = "Row {$row_num}: Database error during duplicate check.";
                    $valid_row = false;
                } else {
                    $checkDuplicate->bind_param("i", $lrn_val);
                    $checkDuplicate->execute();
                    $duplicateResult = $checkDuplicate->get_result()->fetch_assoc();
                    if ($duplicateResult['count'] > 0) {
                        $errors[] = "Row {$row_num}: LRN '{$lrn_val}' already has an assigned guardian.";
                        $valid_row = false;
                    }
                    $checkDuplicate->close();
                }
            }
            // Insert if valid
            if ($valid_row) {
                $stmt->bind_param("issssis", $lrn_val, $lastname, $firstname, $middlename, $suffix, $contact_raw, $rel);
                if (!$stmt->execute()) {
                    error_log(date('c') . " EXECUTE ERR (CSV Import): " . $stmt->error . "\n", 3, $guardian_error_log);
                    $errors[] = "Row {$row_num}: Database error during insert.";
                } else {
                    $rowCount++;
                }
            }
        }
        fclose($handle);
        $stmt->close();

        $message = "✓ CSV Import Complete\nSuccessfully imported: {$rowCount} guardians\n";
        if (!empty($errors)) {
            $message .= "\n❌ Errors (" . count($errors) . " rows failed):\n";
            $message .= implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        echo "<script>alert(" . json_encode($message) . ");location='guardian.php'</script>";
        exit();
    } else {
        echo "<script>alert('❌ Failed to open CSV file. Please check file permissions or file integrity.');location='guardian.php'</script>";
        exit();
    }
}


// --- Fetch Guardians for Table ---
$guardians_query = $conn->prepare("
    SELECT
        g.guardian_id,
        g.lrn,
        g.lastname,
        g.firstname,
        g.middlename,
        g.suffix,
        g.contact_number AS contact,
        g.relationship_to_student,
        CONCAT(s.firstname, ' ', s.lastname) as student_name
    FROM guardians g
    LEFT JOIN students s ON g.lrn = s.lrn
    ORDER BY g.lastname ASC, g.firstname ASC
");
$guardians = null;
if ($guardians_query) {
    if ($guardians_query->execute()) {
        $guardians = $guardians_query->get_result();
    } else {
        error_log(date('c') . " EXECUTE ERR (Fetch Guardians): " . $guardians_query->error . "\n", 3, $guardian_error_log);
    }
} else {
    error_log(date('c') . " PREPARE ERR (Fetch Guardians): " . $conn->error . "\n", 3, $guardian_error_log);
}
if ($guardians_query) $guardians_query->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Guardians Dashboard</title>
    <!-- Custom fonts and styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Load Material Symbols style from Google Fonts -->
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
      .highlight {
        animation: hl 2s forwards;
      }
      .text-danger-custom {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
      }
      .modal-content input.form-control,
      .modal-content select.form-select {
        border: 1px solid #ccc;
        box-shadow: 2px 4px 8px rgba(0,0,0,0.05);
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
        text-align: left;
      }
      .modal-content label {
        font-weight: 500;
        margin-bottom: 0.25rem;
      }
      .modal-header {
        background-color: #007bff;
        color: white;
      }
      .modal-footer button {
        margin-left: 10px;
      }
      .btn-primary {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-radius: 15px;
      }
      .btn-close-white {
        filter: invert(1);
      }
      .table-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        width: 100%;
        box-shadow: 0 12px 30px rgba(15,23,42,0.08);
      }
      .table-responsive-custom {
        overflow-x: auto;
      }
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
      .custom-table tbody tr:last-child td {
        border-bottom: none;
      }
      .custom-table tbody tr:hover {
        background: rgba(59,130,246,0.06);
      }
      .actions-cell {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        min-width: 80px;
      }
      /* Action Icon Buttons using Material Symbols */
      .action-icon-btn {
        border: none;
        background: none;
        padding: 0;
        margin: 0 2px;
        cursor: pointer;
        transition: color 0.2s ease, opacity 0.2s ease;
      }
      .action-icon-btn .material-symbols-outlined {
        font-size: 1.1em;
        font-variation-settings:
          'FILL' 0,
          'wght' 400,
          'GRAD' 0,
          'opsz' 24;
      }
      .action-icon-btn.edit-icon .material-symbols-outlined {
        color: #1c74e4;
      }
      .action-icon-btn.delete-icon .material-symbols-outlined {
        color: #dc3545;
      }
      .action-icon-btn:hover .material-symbols-outlined {
        opacity: 0.7;
      }
      /* Search Box Styles */
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
      .search-box input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
      }
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
      .clear-search:hover {
        color: #374151;
      }
      .clear-search.show {
        display: block;
      }
      /* Logo Styles */
      .page-title-with-logo {
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .page-logo {
        width: 45px;
        height: 45px;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
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
                <!-- Logo/Photo - Replace with your actual logo path -->
                <img src="img/depedlogo.jpg" alt="Guardian Logo" class="page-logo">
                <h2 class="h3 mb-0 text-gray-800">Guardians Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <!-- Search Box -->
                <div class="search-box">
                  <input type="text" id="searchGuardian" placeholder="Search Guardian" autocomplete="off">
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
                <!-- Add Guardian Button -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined">person_add</span> Add Guardian
                </button>
              </div>
            </div>
            <div class="table-card">
              <div class="table-responsive-custom">
                <table class="custom-table">
                  <thead class="bg-gray-200 font-semibold">
                    <tr>
                      <th class="border px-3 py-2">#</th>
                      <th class="border px-3 py-2">LRN</th>
                      <th class="border px-3 py-2">Lastname</th>
                      <th class="border px-3 py-2">Firstname</th>
                      <th class="border px-3 py-2">Middlename</th>
                      <th class="border px-3 py-2">Suffix</th>
                      <th class="border px-3 py-2">Contact</th>
                      <th class="border px-3 py-2">Relationship</th>
                      <th class="border px-3 py-2 text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $counter = 1;
                    if ($guardians && $guardians->num_rows > 0):
                        while ($g = $guardians->fetch_assoc()):
                            $row_class = '';
                            if (isset($_GET['status']) && $_GET['status'] === 'added' && $g['lrn'] == $_GET['lrn']) {
                                $row_class = 'highlight';
                            }
                    ?>
                      <tr class="hover:bg-gray-50 <?= $row_class ?>">
                        <td class="border px-3 py-2"><?= $counter++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['lrn'] ?? '') ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['lastname'] ?? '') ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['firstname'] ?? '') ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['middlename'] ?? '') ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['suffix'] ?? '') ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['contact'] ?? '') ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($g['relationship_to_student'] ?? '') ?></td>
                        <td class="border px-3 py-2 actions-cell">
                          <!-- Edit Button using Material Symbols -->
                          <button onclick='openEditModal(<?= json_encode($g) ?>)' class="action-icon-btn edit-icon" title="Edit Guardian">
                            <span class="material-symbols-outlined">edit</span>
                          </button>
                          <!-- Delete Form using Material Symbols -->
                          <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($g['firstname'] . ' ' . $g['lastname']) ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="guardian_id" value="<?= htmlspecialchars($g['guardian_id'] ?? '') ?>">
                            <button type="submit" name="delete_guardian" class="action-icon-btn delete-icon" title="Delete Guardian">
                              <span class="material-symbols-outlined">delete</span>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="9" class="border px-3 py-2 text-center text-gray-500">No guardians found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Add Guardian Modal -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Add Guardian Information</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="container">
              <form method="post" name="add_guardian_form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3">
                  <label for="add_lrn" class="form-label">Student LRN *</label>
                  <input type="text" id="add_lrn" name="lrn" class="form-control" list="student_lrns"
                         placeholder="Type LRN or select from list" required oninput="validateAddLRN(this)">
                  <datalist id="student_lrns">
                    <?php foreach ($students_for_datalist as $student): ?>
                      <option value="<?= htmlspecialchars($student['lrn']) ?>">
                        <?= htmlspecialchars($student['display_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </datalist>
                  <div id="add_lrn_error" class="text-danger-custom" style="display:none;"></div>
                </div>
                <div class="mb-3">
                  <label for="add_lastname" class="form-label">Last Name *</label>
                  <input id="add_lastname" type="text" class="form-control" name="lastname" placeholder="Enter Last Name" required>
                </div>
                <div class="mb-3">
                  <label for="add_firstname" class="form-label">First Name *</label>
                  <input id="add_firstname" type="text" class="form-control" name="firstname" placeholder="Enter First Name" required>
                </div>
                <div class="mb-3">
                  <label for="add_middlename" class="form-label">Middle Name</label>
                  <input id="add_middlename" type="text" class="form-control" name="middlename" placeholder="Enter Middle Name">
                </div>
                <div class="mb-3">
                  <label for="add_suffix" class="form-label">Suffix</label>
                  <input id="add_suffix" type="text" class="form-control" name="suffix" placeholder="Enter Suffix (Jr., III, etc.)">
                </div>
                <div class="mb-3">
                  <label for="add_contact" class="form-label">Contact Number *</label>
                  <input id="add_contact" type="text" class="form-control" name="contact"
                         placeholder="Enter Contact Number (10 digits)" required pattern="[0-9]{10}" maxlength="10"
                         title="Contact number must be exactly 10 digits long and contain only numbers."
                         oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                  <div id="add_contact_error" class="text-danger-custom" style="display:none;"></div>
                </div>
                <div class="mb-3">
                  <label for="add_relationship" class="form-label">Relationship to Student *</label>
                  <input id="add_relationship" type="text" class="form-control" name="relationship_to_student"
                         placeholder="Enter Relationship to Student" required>
                  <div id="add_relationship_error" class="text-danger-custom" style="display:none;"></div>
                </div>
                <div class="text-center">
                  <button type="submit" name="add_guardian" id="add_guardian_btn" class="btn btn-primary px-4 py-2">Register Guardian</button>
                </div>
              </form>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Edit Guardian Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editModalLabel">Edit Guardian Information</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="container">
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" id="edit_id" name="edit_guardian_id">
                <div class="mb-3">
                  <label for="edit_lrn" class="form-label">Student LRN *</label>
                  <input id="edit_lrn" type="text" class="form-control" name="lrn"
                         placeholder="Enter LRN" required list="student_lrns" oninput="validateEditLRN(this)">
                  <div id="edit_lrn_error" class="text-danger-custom" style="display:none;"></div>
                </div>
                <div class="mb-3">
                  <label for="edit_lastname" class="form-label">Last Name *</label>
                  <input id="edit_lastname" type="text" class="form-control" name="lastname" placeholder="Enter Last Name" required>
                </div>
                <div class="mb-3">
                  <label for="edit_firstname" class="form-label">First Name *</label>
                  <input id="edit_firstname" type="text" class="form-control" name="firstname" placeholder="Enter First Name" required>
                </div>
                <div class="mb-3">
                  <label for="edit_middlename" class="form-label">Middle Name</label>
                  <input id="edit_middlename" type="text" class="form-control" name="middlename" placeholder="Enter Middle Name">
                </div>
                <div class="mb-3">
                  <label for="edit_suffix" class="form-label">Suffix</label>
                  <input id="edit_suffix" type="text" class="form-control" name="suffix" placeholder="Enter Suffix (Jr., III, etc.)">
                </div>
                <div class="mb-3">
                  <label for="edit_contact" class="form-label">Contact Number *</label>
                  <input id="edit_contact" type="text" class="form-control" name="contact"
                         placeholder="Enter Contact Number (10 digits)" required pattern="[0-9]{10}" maxlength="10"
                         title="Contact number must be exactly 10 digits long and contain only numbers."
                         oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                  <div id="edit_contact_error" class="text-danger-custom" style="display:none;"></div>
                </div>
                <div class="mb-3">
                  <label for="edit_rel" class="form-label">Relationship to Student *</label>
                  <input id="edit_rel" type="text" class="form-control" name="relationship_to_student"
                         placeholder="Enter Relationship to Student" required>
                </div>
                <div class="text-center">
                  <button type="submit" name="edit_guardian" class="btn btn-primary px-4 py-2">Update Guardian</button>
                </div>
              </form>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Import Guardian CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="importModalLabel">Import Guardians from CSV</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-body">
              <div class="mb-3">
                <label for="guardian_csvfile" class="form-label">Select CSV File *</label>
                <input type="file" name="guardian_csvfile" id="guardian_csvfile" class="form-control" accept=".csv" required>
                <small class="text-muted">Max 5MB | Columns: LRN, Lastname, Firstname, Middlename, Suffix, ContactNumber, Relationship_to_Student</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="import_guardians_csv" class="btn btn-primary">Upload & Import</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <script>
      // Populate Edit Modal with Data
      function openEditModal(g) {
        $('#editModal').modal('show');
        $('#edit_id').val(g.guardian_id);
        $('#edit_lrn').val(g.lrn);
        $('#edit_lastname').val(g.lastname);
        $('#edit_firstname').val(g.firstname);
        $('#edit_middlename').val(g.middlename);
        $('#edit_suffix').val(g.suffix);
        $('#edit_contact').val(g.contact);
        $('#edit_rel').val(g.relationship_to_student);
      }
      // Validate LRN in Add Form
      function validateAddLRN(input) {
        const lrnValue = input.value.replace(/\D/g, '');
        const errorDiv = document.getElementById('add_lrn_error');
        const datalistOptions = document.getElementById('student_lrns').options;
        let lrnExistsInDataList = false;
        if (lrnValue.length === 0) {
          errorDiv.textContent = 'LRN is required.';
          errorDiv.style.display = 'block';
          input.setCustomValidity('LRN is required.');
        } else if (lrnValue.length !== 12) {
          errorDiv.textContent = 'LRN must be exactly 12 digits.';
          errorDiv.style.display = 'block';
          input.setCustomValidity('LRN must be exactly 12 digits.');
        } else {
          for (let i = 0; i < datalistOptions.length; i++) {
            if (datalistOptions[i].value === lrnValue) {
              lrnExistsInDataList = true;
              break;
            }
          }
          if (!lrnExistsInDataList) {
            errorDiv.textContent = 'LRN not found in student records. Please register the student first.';
            errorDiv.style.display = 'block';
            input.setCustomValidity('LRN not found in student records.');
          } else {
            errorDiv.textContent = '';
            errorDiv.style.display = 'none';
            input.setCustomValidity('');
          }
        }
        input.value = lrnValue;
      }
      // Validate LRN in Edit Form
      function validateEditLRN(input) {
        const lrnValue = input.value.replace(/\D/g, '');
        const errorDiv = document.getElementById('edit_lrn_error');
        const datalistOptions = document.getElementById('student_lrns').options;
        let lrnExistsInDataList = false;
        if (lrnValue.length === 0) {
          errorDiv.textContent = 'LRN is required.';
          errorDiv.style.display = 'block';
          input.setCustomValidity('LRN is required.');
        } else if (lrnValue.length !== 12) {
          errorDiv.textContent = 'LRN must be exactly 12 digits.';
          errorDiv.style.display = 'block';
          input.setCustomValidity('LRN must be exactly 12 digits.');
        } else {
          for (let i = 0; i < datalistOptions.length; i++) {
            if (datalistOptions[i].value === lrnValue) {
              lrnExistsInDataList = true;
              break;
            }
          }
          if (!lrnExistsInDataList) {
            errorDiv.textContent = 'LRN not found in student records. Please register the student first.';
            errorDiv.style.display = 'block';
            input.setCustomValidity('LRN not found in student records.');
          } else {
            errorDiv.textContent = '';
            errorDiv.style.display = 'none';
            input.setCustomValidity('');
          }
        }
        input.value = lrnValue;
      }
      // Client-Side Validation for Contact and Relationship on Form Submission
      document.forms['add_guardian_form']?.addEventListener('submit', function(event) {
        const contactInput = document.getElementById('add_contact');
        const relationshipInput = document.getElementById('add_relationship');
        const contactErrorDiv = document.getElementById('add_contact_error');
        const relationshipErrorDiv = document.getElementById('add_relationship_error');
        let isValid = true;
        if (contactInput.value.length !== 10 || !/^\d+$/.test(contactInput.value)) {
          contactErrorDiv.textContent = 'Contact number must be exactly 10 digits.';
          contactErrorDiv.style.display = 'block';
          contactInput.classList.add('is-invalid');
          isValid = false;
        } else {
          contactErrorDiv.textContent = '';
          contactErrorDiv.style.display = 'none';
          contactInput.classList.remove('is-invalid');
        }
        if (relationshipInput.value.trim() === '') {
          relationshipErrorDiv.textContent = 'Relationship to student is required.';
          relationshipErrorDiv.style.display = 'block';
          relationshipInput.classList.add('is-invalid');
          isValid = false;
        } else {
          relationshipErrorDiv.textContent = '';
          relationshipErrorDiv.style.display = 'none';
          relationshipInput.classList.remove('is-invalid');
        }
        if (!isValid) {
          event.preventDefault();
        }
      });
      // Display status message based on URL parameter
      $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status) {
          let message = "";
          let highlightedLrn = urlParams.get('lrn');
          if (status === "added") {
            message = "Guardian added successfully!";
            if (highlightedLrn) {
              $(`tr:contains('${highlightedLrn}')`).addClass('highlight');
            }
          } else if (status === "deleted") {
            message = "Guardian deleted successfully!";
          } else if (status === "updated") {
            message = "Guardian updated successfully!";
            if (highlightedLrn) {
              $(`tr:contains('${highlightedLrn}')`).addClass('highlight');
            }
          } else if (status === "imported") {
            message = "Guardians imported successfully!";
          }
          if (message) {
            alert(message);
            window.history.replaceState({}, document.title, "guardian.php");
          }
        }
        // ========== SEARCH FUNCTIONALITY ==========
        const searchInput = $('#searchGuardian');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');
        // Real-time search
        searchInput.on('input', function() {
          const searchTerm = $(this).val().toLowerCase().trim();
          if (searchTerm.length > 0) {
            clearBtn.addClass('show');
          } else {
            clearBtn.removeClass('show');
          }
          let visibleCount = 0;
          tableRows.each(function() {
            const row = $(this);
            const rowText = row.text().toLowerCase();
            if (rowText.includes(searchTerm)) {
              row.show();
              visibleCount++;
            } else {
              row.hide();
            }
          });
          if (visibleCount === 0 && searchTerm.length > 0) {
            if ($('#noResults').length === 0) {
              $('.custom-table tbody').append(
                '<tr id="noResults"><td colspan="9" class="border px-3 py-2 text-center text-gray-500"><span class="material-symbols-outlined" style="font-size:1.2rem;">search_off</span> No guardians found matching "' + searchTerm + '"</td></tr>'
              );
            }
          } else {
            $('#noResults').remove();
          }
        });
        // Clear search
        clearBtn.on('click', function() {
          searchInput.val('');
          clearBtn.removeClass('show');
          tableRows.show();
          $('#noResults').remove();
          searchInput.focus();
        });
        searchInput.on('keydown', function(e) {
          if (e.key === 'Escape') {
            clearBtn.click();
          }
        });
      });
    </script>
  </body>
</html>