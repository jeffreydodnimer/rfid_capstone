<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';
if (!$conn) {
    die("Database connection failed.");
}

// Create logs folder if needed
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$section_error_log = 'logs/section_errors.txt';

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
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, 'logs/section_errors.txt');
        echo "<script>alert('Invalid security token. Please try again.');location='section_student.php'</script>";
        exit();
    }
}

function reset_sections_autoincrement($conn) {
    $count_res = $conn->query("SELECT COUNT(*) AS cnt FROM sections");
    if ($count_res) {
        $row = $count_res->fetch_assoc();
        if ((int)$row['cnt'] === 0) {
            $conn->query("ALTER TABLE sections AUTO_INCREMENT = 1");
        }
    }
}

$available_grade_levels = [
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', '11-GAS', 'Grade 12', '12-GAS'
];

// --- Handle Add Section (using employee_id) ---
if (isset($_POST['add_section'])) {
    validate_csrf_token();
    $section_name = htmlspecialchars(trim($_POST['section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars(trim($_POST['grade_level_input']), ENT_QUOTES, 'UTF-8');
    $employee_id   = filter_var($_POST['employee_id'], FILTER_VALIDATE_INT);

    if (empty($section_name) || empty($grade_level) || $employee_id === false || $employee_id === null) {
        echo "<script>alert('All fields are required. Please ensure Section Name, Grade Level, and Adviser are provided.'); location='section_student.php'</script>";
        exit();
    }

    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ?");
    if (!$dup_stmt) {
        error_log(date('c') . " PREPARE ERR (Add Section Dup Check): " . $conn->error . "\n", 3, $section_error_log);
        echo "<script>alert('Database error during duplicate check.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->bind_param("ss", $section_name, $grade_level);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: A section with this name already exists for this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->close();

    // Check if employee is already assigned to ANY section
    $adviser_check = $conn->prepare("SELECT section_id FROM sections WHERE employee_id = ?");
    if (!$adviser_check) {
        error_log(date('c') . " PREPARE ERR (Add Section Adviser Check): " . $conn->error . "\n", 3, $section_error_log);
        echo "<script>alert('Database error during adviser check.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->bind_param("i", $employee_id);
    $adviser_check->execute();
    if ($adviser_check->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This adviser is already assigned to another section. One adviser can only manage one section.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->close();

    // Insert using employee_id
    $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, employee_id) VALUES (?, ?, ?)");
    if (!$stmt) {
        error_log(date('c') . " PREPARE ERR (Add Section Insert): " . $conn->error . "\n", 3, $section_error_log);
        echo "<script>alert('Database error preparing insert statement.'); location='section_student.php'</script>";
        exit();
    }
    $stmt->bind_param("ssi", $section_name, $grade_level, $employee_id);
    if ($stmt->execute()) {
        header("Location: section_student.php?status=added");
    } else {
        error_log(date('c') . " EXECUTE ERR (Add Section Insert): " . $stmt->error . "\n", 3, $section_error_log);
        echo "<script>alert('Error adding section.'); location='section_student.php'</script>";
    }
    exit();
}

// --- Handle Edit Section (using employee_id) ---
if (isset($_POST['edit_section'])) {
    validate_csrf_token();
    $section_id   = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT);
    $section_name = htmlspecialchars(trim($_POST['edit_section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars(trim($_POST['edit_grade_level_input']), ENT_QUOTES, 'UTF-8');
    $employee_id   = filter_var($_POST['edit_employee_id'], FILTER_VALIDATE_INT);

    if (!$section_id || empty($section_name) || empty($grade_level) || $employee_id === false || $employee_id === null) {
        echo "<script>alert('All fields are required for editing. Please ensure Section Name, Grade Level, and Adviser are provided.'); location='section_student.php'</script>";
        exit();
    }

    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? AND section_id != ?");
    if (!$dup_stmt) {
        error_log(date('c') . " PREPARE ERR (Edit Section Dup Check): " . $conn->error . "\n", 3, $section_error_log);
        echo "<script>alert('Database error during duplicate check.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->bind_param("ssi", $section_name, $grade_level, $section_id);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Another section with this name already exists for this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->close();

    // Check if employee is already assigned to another section
    $adviser_check = $conn->prepare("SELECT section_id FROM sections WHERE employee_id = ? AND section_id != ?");
    if (!$adviser_check) {
        error_log(date('c') . " PREPARE ERR (Edit Section Adviser Check): " . $conn->error . "\n", 3, $section_error_log);
        echo "<script>alert('Database error during adviser check.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->bind_param("ii", $employee_id, $section_id);
    $adviser_check->execute();
    if ($adviser_check->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This adviser is already assigned to another section. One adviser can only manage one section.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->close();

    // Update using employee_id
    $stmt = $conn->prepare("UPDATE sections SET section_name=?, grade_level=?, employee_id=? WHERE section_id=?");
    if (!$stmt) {
        error_log(date('c') . " PREPARE ERR (Edit Section Update): " . $conn->error . "\n", 3, $section_error_log);
        echo "<script>alert('Database error preparing update statement.'); location='section_student.php'</script>";
        exit();
    }
    $stmt->bind_param("ssii", $section_name, $grade_level, $employee_id, $section_id);
    if ($stmt->execute()) {
        header("Location: section_student.php?status=updated");
    } else {
        error_log(date('c') . " EXECUTE ERR (Edit Section Update): " . $stmt->error . "\n", 3, $section_error_log);
        echo "<script>alert('Error updating section.'); location='section_student.php'</script>";
    }
    exit();
}

// --- Handle Delete Section ---
if (isset($_POST['delete_section'])) {
    validate_csrf_token();
    $section_id = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);
    if ($section_id) {
        $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
        if (!$stmt) {
            error_log(date('c') . " PREPARE ERR (Delete Section): " . $conn->error . "\n", 3, $section_error_log);
            echo "<script>alert('Database error preparing delete statement.'); location='section_student.php'</script>";
            exit();
        }
        $stmt->bind_param("i", $section_id);
        if ($stmt->execute()) {
            reset_sections_autoincrement($conn);
            header("Location: section_student.php?status=deleted");
        } else {
            error_log(date('c') . " EXECUTE ERR (Delete Section): " . $stmt->error . "\n", 3, $section_error_log);
            echo "<script>alert('Error: Could not delete section. It might be in use by enrollments or other records.'); location='section_student.php'</script>";
        }
        exit();
    }
}

function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

// --- CSV Import (FIXED: parameter count mismatch) ---
if (isset($_POST['import_sections_csv']) && isset($_FILES['sections_csvfile']) && $_FILES['sections_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token();
    $tmpPath  = $_FILES['sections_csvfile']['tmp_name'];
    $fileName = $_FILES['sections_csvfile']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'csv') {
        echo "<script>alert('❌ Please upload a CSV file.'); location='section_student.php'</script>";
        exit();
    }
    if ($_FILES['sections_csvfile']['size'] > 5 * 1024 * 1024) {
        echo "<script>alert('❌ File size exceeds 5MB limit.'); location='section_student.php'</script>";
        exit();
    }
    
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        $conn->begin_transaction(); // Start transaction for safety
        
        try {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE TABLE sections");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            ini_set('auto_detect_line_endings', 1);
            $header = fgetcsv($handle, 2000, ",");

            $header_aliases = [
                'section_name' => ['section_name', 'section'],
                'grade_level'  => ['grade_level', 'grade'],
                'adviser_name' => ['adviser_name', 'adviser', 'advisor_name', 'advisor', 'adviser name', 'advisor name']
            ];

            if (!is_array($header)) {
                throw new Exception('❌ Invalid CSV header row.');
            }

            $col_map = [];
            foreach ($header_aliases as $logical_name => $aliases) {
                $found = false;
                foreach ($header as $h_idx => $h_val) {
                    $cleaned = strtolower(safeTrimCSV($h_val));
                    if (in_array($cleaned, $aliases, true)) {
                        $col_map[$logical_name] = $h_idx;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new Exception("❌ CSV header is missing required column (or alias) for: \"{$logical_name}\"");
                }
            }
            // FIXED: Robust adviser lookup with "Sandwich" matching (Starts with First, Ends with Last)
            // This handles cases where CSV has "First Middle Last" but DB only has "First Last"
            $lookup_stmt = $conn->prepare("
                SELECT employee_id, CONCAT(firstname, ' ', lastname) AS full_name 
                FROM advisers 
                WHERE 
                    LOWER(CONCAT(firstname, ' ', lastname)) = LOWER(?) OR
                    LOWER(CONCAT(lastname, ' ', firstname)) = LOWER(?) OR
                    LOWER(firstname) = LOWER(?) OR
                    LOWER(lastname) = LOWER(?) OR
                    LOWER(CONCAT(firstname, lastname)) = LOWER(REPLACE(?, ' ', '')) OR
                    LOWER(CONCAT(lastname, firstname)) = LOWER(REPLACE(?, ' ', '')) OR
                    -- NEW: Allow match if CSV starts with Firstname and Ends with Lastname (Ignores Middle Name mismatch)
                    (LOWER(?) LIKE CONCAT(LOWER(firstname), '%') AND LOWER(?) LIKE CONCAT('%', LOWER(lastname)))
                LIMIT 1
            ");
            
            // Insert using employee_id into sections table
            $insert_stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, employee_id) VALUES (?, ?, ?)");

            if (!$lookup_stmt || !$insert_stmt) {
                throw new Exception('❌ Database error preparing CSV import statements.');
            }

            $rowCount = 0;
            $errors   = [];
            $row_num  = 1;

            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $row_num++;

                if (!is_array($data) || count(array_filter($data)) === 0) continue;

                $csv_section = htmlspecialchars(trim(safeTrimCSV($data[$col_map['section_name']] ?? '')), ENT_QUOTES, 'UTF-8');
                $csv_grade   = htmlspecialchars(trim(safeTrimCSV($data[$col_map['grade_level']] ?? '')), ENT_QUOTES, 'UTF-8');
                $csv_adv_name = trim(safeTrimCSV($data[$col_map['adviser_name']] ?? ''));

                // Clean up double spaces in the name (e.g. "Name  Surname" becomes "Name Surname")
                $csv_adv_name = preg_replace('/\s+/', ' ', $csv_adv_name);

                if (empty($csv_section) || empty($csv_grade) || empty($csv_adv_name)) {
                    $errors[] = "Row {$row_num}: Missing required fields.";
                    continue;
                }

                // Prepare multiple search variations
                $adv_name = $csv_adv_name;
                $adv_name_no_space = str_replace(' ', '', $csv_adv_name);
                
                // Basic split for fallback logic (though the new SQL handles most of this)
                $adv_parts = explode(' ', $csv_adv_name);
                $first_name_guess = $adv_parts[0] ?? '';
                $last_name_guess = end($adv_parts) ?: '';

                // BIND PARAMETERS: 
                // We added 2 new placeholders (?) in the SQL, so we need 8 strings total (ssssssss)
                // The last two $adv_name variables correspond to the new LIKE matching
                $lookup_stmt->bind_param("ssssssss", 
                    $adv_name,           // Exact First Last
                    $adv_name,           // Exact Last First
                    $first_name_guess,   // Exact First
                    $last_name_guess,    // Exact Last
                    $adv_name_no_space,  // NoSpace FirstLast
                    $adv_name_no_space,  // NoSpace LastFirst
                    $adv_name,           // NEW: Starts with First...
                    $adv_name            // NEW: ...Ends with Last
                );
                
                $lookup_stmt->execute();
                $lookup_res = $lookup_stmt->get_result();

                if ($lookup_res->num_rows === 0) {
                    $errors[] = "Row {$row_num}: Adviser '{$csv_adv_name}' not found in database.";
                    continue;
                }
                
                $adviser_data = $lookup_res->fetch_assoc();
                $employee_id = (int)$adviser_data['employee_id'];

                $insert_stmt->bind_param("ssi", $csv_section, $csv_grade, $employee_id);
                if ($insert_stmt->execute()) {
                    $rowCount++;
                } else {
                    $errors[] = "Row {$row_num}: DB Error - " . $insert_stmt->error;
                }
            }

            fclose($handle);
            $lookup_stmt->close();
            $insert_stmt->close();

            if (!empty($errors)) {
                $conn->rollback(); // Rollback if there were any errors
                $message = "CSV Import Failed. Please fix errors and try again.\\n❌ Errors (" . count($errors) . " rows failed):\\n" . implode("\\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= "\\n... and " . (count($errors) - 5) . " more errors.";
                }
            } else {
                $conn->commit(); // Commit transaction if successful
                $message = "CSV Import Complete. Successfully imported: {$rowCount} sections.";
            }
            
            echo "<script>alert(" . json_encode($message) . "); location='section_student.php'</script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            if (isset($handle) && is_resource($handle)) fclose($handle);
            if (isset($lookup_stmt)) $lookup_stmt->close();
            if (isset($insert_stmt)) $insert_stmt->close();
            error_log(date('c') . " CSV IMPORT EXCEPTION: " . $e->getMessage() . "\n", 3, $section_error_log);
            echo "<script>alert(" . json_encode($e->getMessage()) . "); location='section_student.php'</script>";
            exit();
        }
    } else {
        echo "<script>alert('❌ Failed to open CSV file.'); location='section_student.php'</script>";
        exit();
    }
}


// --- Fetch Advisors (using employee_id) ---
$advisers_result = $conn->query("SELECT employee_id, CONCAT_WS(' ', firstname, lastname) AS adviser_fullname FROM advisers ORDER BY lastname, firstname");
$all_advisers_php = $advisers_result ? $advisers_result->fetch_all(MYSQLI_ASSOC) : [];
$all_advisers_json = json_encode($all_advisers_php);

$available_grade_levels_json = json_encode($available_grade_levels);

// --- Fetch Sections (using employee_id) ---
$sections_query = "
    SELECT s.section_id, s.section_name, s.grade_level, s.employee_id,
           CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_fullname
    FROM sections s
    LEFT JOIN advisers adv ON s.employee_id = adv.employee_id
    ORDER BY s.grade_level, s.section_name
";
$sections_result = $conn->query($sections_query);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sections Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
      .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
      @keyframes hl { 0% { background-color: #c8e6c9; } 100% { background-color: transparent; } }
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
          transition: color 0.2s ease, opacity 0.2s ease;
      }
      .action-icon-btn .material-symbols-outlined {
          font-size: 1.2em;
          font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
      .action-icon-btn.edit-icon .material-symbols-outlined { color: #1c74e4; }
      .action-icon-btn.delete-icon .material-symbols-outlined { color: #dc3545; }
      .action-icon-btn:hover .material-symbols-outlined { opacity: 0.7; }
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
          display: none;
      }
      .clear-search.show { display: block; }
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
          <div class="container-fluid mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="page-title-with-logo">
                <img src="img/depedlogo.jpg" alt="Section Logo" class="page-logo">
                <h2 class="h3 mb-0 text-gray-800">Sections Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <div class="search-box">
                  <input type="text" id="searchSection" placeholder="Search Section" autocomplete="off">
                  <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                      <span class="material-symbols-outlined">close</span>
                  </button>
                  <span class="search-icon">
                      <span class="material-symbols-outlined">search</span>
                  </span>
                </div>
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span class="material-symbols-outlined">upload_file</span> Import CSV
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span class="material-symbols-outlined">note_add</span> Add Section
                </button>
              </div>
            </div>

            <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              Section <?= htmlspecialchars($_GET['status']) ?> successfully!
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="table-card">
              <div class="table-responsive-custom">
                <table class="custom-table">
                  <thead class="bg-gray-200 font-semibold">
                    <tr>
                      <th class="px-3 py-2">#</th>
                      <th class="px-3 py-2">Section Name</th>
                      <th class="px-3 py-2">Grade Level</th>
                      <th class="px-3 py-2">Adviser</th>
                      <th class="px-3 py-2 text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $counter = 1;
                      if ($sections_result && $sections_result->num_rows > 0):
                        while ($row = $sections_result->fetch_assoc()):
                    ?>
                      <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2"><?= $counter++ ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($row['adviser_fullname'] ?? 'N/A') ?></td>
                        <td class="px-3 py-2 actions-cell">
                          <button onclick='openEditSectionModal(<?= json_encode([
                            "section_id" => $row["section_id"],
                            "section_name" => $row["section_name"],
                            "grade_level" => $row["grade_level"],
                            "employee_id" => $row["employee_id"],
                            "adviser_fullname" => $row["adviser_fullname"]
                          ]) ?>)' class="action-icon-btn edit-icon" title="Edit Section">
                            <span class="material-symbols-outlined">edit</span>
                          </button>
                          <form method="POST" class="inline" onsubmit="return confirm('Delete section <?= htmlspecialchars($row['section_name']) ?>? This cannot be undone.');">
                              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                              <input type="hidden" name="section_id" value="<?= $row['section_id'] ?>">
                              <button type="submit" name="delete_section" class="action-icon-btn delete-icon" title="Delete Section">
                                  <span class="material-symbols-outlined">delete</span>
                              </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; else: ?>
                      <tr>
                        <td colspan="5" class="px-3 py-2 text-center text-gray-500">No sections found.</td>
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

    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" id="addSectionForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="add_section_name" class="form-label">Section Name</label>
                <input type="text" class="form-control" id="add_section_name" name="section_name" placeholder="e.g., Courage" required>
              </div>
              <div class="mb-3">
                <label for="add_grade_level_input" class="form-label">Grade Level</label>
                <input type="text" class="form-control" id="add_grade_level_input" name="grade_level_input" list="grade_levels_datalist_add" placeholder="Type Grade Level" required>
                <datalist id="grade_levels_datalist_add"></datalist>
              </div>
              <div class="mb-3">
                <label for="add_adviser_name_input" class="form-label">Adviser</label>
                <input type="text" class="form-control" id="add_adviser_name_input" list="advisers_datalist_add" placeholder="Select or type Adviser Name" required>
                <datalist id="advisers_datalist_add"></datalist>
                <input type="hidden" id="add_employee_id_hidden" name="employee_id">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="add_section" class="btn btn-primary">Add Section</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" id="editSectionForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="edit_section_id" name="edit_section_id">
            <div class="modal-header">
              <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="edit_section_name" class="form-label">Section Name</label>
                <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" required>
              </div>
              <div class="mb-3">
                <label for="edit_grade_level_input" class="form-label">Grade Level</label>
                <input type="text" class="form-control" id="edit_grade_level_input" name="edit_grade_level_input" list="grade_levels_datalist_edit" placeholder="Type Grade Level" required>
                <datalist id="grade_levels_datalist_edit"></datalist>
              </div>
              <div class="mb-3">
                <label for="edit_adviser_name_input" class="form-label">Adviser</label>
                <input type="text" class="form-control" id="edit_adviser_name_input" list="advisers_datalist_edit" placeholder="Select or type Adviser Name" required>
                <datalist id="advisers_datalist_edit"></datalist>
                <input type="hidden" id="edit_employee_id_hidden" name="edit_employee_id">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="edit_section" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Import Sections CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="importModalLabel">Import Sections from CSV</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-warning">
                <strong>Warning:</strong> Importing will first delete all existing sections and replace them with the content of the CSV file.
              </div>
              <div class="mb-3">
                <label for="sections_csvfile" class="form-label">Select CSV File *</label>
                <input type="file" name="sections_csvfile" id="sections_csvfile" class="form-control" accept=".csv" required>
                <small class="text-muted">Max 5MB | Columns (case-insensitive): section_name, grade_level, adviser_name</small><br>
                <small class="text-info">Adviser name matching is now more flexible (case-insensitive, name order variations, etc.).</small>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="import_sections_csv" class="btn btn-primary">Upload & Import</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      const allAdvisersData = <?= $all_advisers_json ?>;
      const availableGradeLevels = <?= $available_grade_levels_json ?>;

      // This script now consistently uses 'employee_id' for identifying advisers.
      function populateAdvisersDatalist(adviserNameInputId, datalistId, employeeIdHiddenId, initialAdviserName = null, initialEmployeeId = null) {
          const adviserDatalist = document.getElementById(datalistId);
          const adviserNameInput = document.getElementById(adviserNameInputId);
          const employeeIdHidden = document.getElementById(employeeIdHiddenId);

          adviserDatalist.innerHTML = '';

          allAdvisersData.forEach(adviser => {
              const option = document.createElement('option');
              option.value = adviser.adviser_fullname;
              // Set the employee_id as a data attribute for easy retrieval
              option.setAttribute('data-employee-id', adviser.employee_id);
              adviserDatalist.appendChild(option);
          });

          // Pre-fill values for the edit modal
          if (initialAdviserName && initialEmployeeId) {
              adviserNameInput.value = initialAdviserName;
              employeeIdHidden.value = initialEmployeeId;
          } else {
              adviserNameInput.value = '';
              employeeIdHidden.value = '';
          }
      }

      function handleAdviserInput(adviserNameInputId, datalistId, employeeIdHiddenId) {
          const adviserNameInput = document.getElementById(adviserNameInputId);
          const adviserDatalist = document.getElementById(datalistId);
          const employeeIdHidden = document.getElementById(employeeIdHiddenId);
          
          const updateEmployeeId = () => {
              const selectedOption = Array.from(adviserDatalist.options).find(option => option.value === adviserNameInput.value);
              if (selectedOption) {
                  employeeIdHidden.value = selectedOption.getAttribute('data-employee-id');
                  adviserNameInput.setCustomValidity(''); // Valid selection
              } else {
                  employeeIdHidden.value = '';
                  // Do not set custom validity on input, but on submit
              }
          };

          adviserNameInput.addEventListener('input', updateEmployeeId);
          adviserNameInput.addEventListener('change', updateEmployeeId);
      }

      function populateGradeLevelDatalist(datalistId) {
          const datalist = document.getElementById(datalistId);
          datalist.innerHTML = '';
          availableGradeLevels.forEach(grade => {
              const option = document.createElement('option');
              option.value = grade;
              datalist.appendChild(option);
          });
      }

      // Main document ready function
      $(document).ready(function(){
        // Search functionality
        const searchInput = $('#searchSection');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');
        searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            if(searchTerm.length > 0) { clearBtn.addClass('show'); } else { clearBtn.removeClass('show'); }
            let visibleCount = 0;
            tableRows.each(function(){
                const row = $(this);
                if(row.text().toLowerCase().includes(searchTerm)){
                    row.show(); visibleCount++;
                }else{
                    row.hide();
                }
            });
            $('#noResults').remove(); // Always remove previous no-results message
            if(visibleCount === 0 && tableRows.length > 0) {
                $('.custom-table tbody').append('<tr id="noResults"><td colspan="5" class="px-3 py-2 text-center text-gray-500"><span class="material-symbols-outlined" style="font-size:1.2rem;">search_off</span> No sections found matching "' + searchTerm + '"</td></tr>');
            }
        });
        clearBtn.on('click', function(){
            searchInput.val('').trigger('input').focus();
        });
        searchInput.on('keydown', function(e) { if(e.key === 'Escape') clearBtn.click(); });
      
        // Auto-hide success alert
        setTimeout(function(){
          $(".alert").alert('close');
          if (window.history.replaceState){
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({path: url.href}, '', url.href);
          }
        }, 5000);

        // --- Add Modal Logic ---
        $('#addSectionModal').on('show.bs.modal', function() {
            populateGradeLevelDatalist('grade_levels_datalist_add');
            populateAdvisersDatalist('add_adviser_name_input', 'advisers_datalist_add', 'add_employee_id_hidden');
            document.getElementById('addSectionForm').reset();
            document.getElementById('add_adviser_name_input').setCustomValidity('');
        });
        handleAdviserInput('add_adviser_name_input', 'advisers_datalist_add', 'add_employee_id_hidden');

        document.getElementById('addSectionForm').addEventListener('submit', function(event) {
            const employeeIdHidden = document.getElementById('add_employee_id_hidden');
            const adviserNameInput = document.getElementById('add_adviser_name_input');
            if (!employeeIdHidden.value) {
                adviserNameInput.setCustomValidity('Please select a valid Adviser from the suggestions or type an exact match.');
                event.preventDefault();
                adviserNameInput.reportValidity();
            } else {
                adviserNameInput.setCustomValidity('');
            }
        });

        // --- Edit Modal Logic ---
        handleAdviserInput('edit_adviser_name_input', 'advisers_datalist_edit', 'edit_employee_id_hidden');
        document.getElementById('editSectionForm').addEventListener('submit', function(event) {
            const employeeIdHidden = document.getElementById('edit_employee_id_hidden');
            const adviserNameInput = document.getElementById('edit_adviser_name_input');
            if (!employeeIdHidden.value) {
                adviserNameInput.setCustomValidity('Please select a valid Adviser from the suggestions or type an exact match.');
                event.preventDefault();
                adviserNameInput.reportValidity();
            } else {
                adviserNameInput.setCustomValidity('');
            }
        });
      });
      
      function openEditSectionModal(data) {
          const editModal = new bootstrap.Modal(document.getElementById('editSectionModal'));
          
          // Populate the form fields with data from the selected row
          document.getElementById('edit_section_id').value   = data.section_id;
          document.getElementById('edit_section_name').value = data.section_name;
          document.getElementById('edit_grade_level_input').value = data.grade_level;
          
          populateGradeLevelDatalist('grade_levels_datalist_edit');
          populateAdvisersDatalist(
              'edit_adviser_name_input',
              'advisers_datalist_edit',
              'edit_employee_id_hidden',
              data.adviser_fullname,
              data.employee_id
          );
          
          // Clear any previous validation messages
          document.getElementById('edit_adviser_name_input').setCustomValidity('');
          
          editModal.show();
      }
    </script>
  </body>
</html>