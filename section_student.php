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

/****************
SECTION MANAGEMENT
*****************/

// --- Handle Add Section ---
if (isset($_POST['add_section'])) {
    validate_csrf_token();
    $section_name = htmlspecialchars(trim($_POST['section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars($_POST['grade_level'], ENT_QUOTES, 'UTF-8');
    $adviser_id   = filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT);

    if (empty($section_name) || empty($grade_level) || $adviser_id === false) {
        echo "<script>alert('All fields are required.'); location='section_student.php'</script>";
        exit();
    }

    // Check for duplicate section name within the same grade level
    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ?");
    $dup_stmt->bind_param("ss", $section_name, $grade_level);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: A section with this name already exists for this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->close();

    // Check if the adviser is already assigned to another section for this grade level
    $adviser_check = $conn->prepare("SELECT section_id FROM sections WHERE adviser_id = ? AND grade_level = ?");
    $adviser_check->bind_param("is", $adviser_id, $grade_level);
    $adviser_check->execute();
    if ($adviser_check->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This adviser is already assigned to a section in this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->close();

    $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, adviser_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $section_name, $grade_level, $adviser_id);
    if ($stmt->execute()) {
        header("Location: section_student.php?status=added");
    } else {
        echo "<script>alert('Error adding section.'); location='section_student.php'</script>";
    }
    exit();
}

// --- Handle Edit Section ---
if (isset($_POST['edit_section'])) {
    validate_csrf_token();
    $section_id   = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT);
    $section_name = htmlspecialchars(trim($_POST['edit_section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars($_POST['edit_grade_level'], ENT_QUOTES, 'UTF-8');
    $adviser_id   = filter_var($_POST['edit_adviser_id'], FILTER_VALIDATE_INT);

    if (!$section_id || empty($section_name) || empty($grade_level) || $adviser_id === false) {
        echo "<script>alert('All fields are required for editing.'); location='section_student.php'</script>";
        exit();
    }
    
    // Check for duplicate section name (excluding current record)
    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? AND section_id != ?");
    $dup_stmt->bind_param("ssi", $section_name, $grade_level, $section_id);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Another section with this name already exists for this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->close();

    // Check if the adviser is already assigned to another section for this grade level
    $adviser_check = $conn->prepare("SELECT section_id FROM sections WHERE adviser_id = ? AND grade_level = ? AND section_id != ?");
    $adviser_check->bind_param("isi", $adviser_id, $grade_level, $section_id);
    $adviser_check->execute();
    if ($adviser_check->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This adviser is already assigned to another section in this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->close();

    $stmt = $conn->prepare("UPDATE sections SET section_name=?, grade_level=?, adviser_id=? WHERE section_id=?");
    $stmt->bind_param("ssii", $section_name, $grade_level, $adviser_id, $section_id);
    if ($stmt->execute()) {
        header("Location: section_student.php?status=updated");
    } else {
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
        $stmt->bind_param("i", $section_id);
        if ($stmt->execute()) {
            header("Location: section_student.php?status=deleted");
        } else {
            echo "<script>alert('Error: Could not delete section. It might be in use.'); location='section_student.php'</script>";
        }
        exit();
    }
}

/************************************
CSV IMPORT FOR SECTIONS (OPTIONAL)
************************************/
function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

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
    if ($conn->connect_error) {
        error_log(date('c') . " DB Conn Error during CSV import: " . $conn->connect_error . "\n", 3, $section_error_log);
        echo "<script>alert('Database connection lost. Cannot import CSV.'); location='section_student.php'</script>";
        exit();
    }
    
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");
        // CSV is expected to have these columns (case-sensitive):
        // section_name, grade_level, adviser_id
        $expected_headers = ['section_name', 'grade_level', 'adviser_id'];
        if (!is_array($header) || count($header) < count($expected_headers)) {
            fclose($handle);
            echo "<script>alert('❌ CSV file must have at least these columns: section_name, grade_level, adviser_id'); location='section_student.php'</script>";
            exit();
        }
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
                echo "<script>alert('❌ CSV header is missing required column: \"{$expected_header}\"'); location='section_student.php'</script>";
                exit();
            }
        }
        $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, adviser_id) VALUES (?, ?, ?)");
        if (!$stmt) {
            fclose($handle);
            error_log(date('c') . " PREPARE ERR (CSV Import): " . $conn->error . "\n", 3, $section_error_log);
            echo "<script>alert('❌ Database error preparing CSV import.'); location='section_student.php'</script>";
            exit();
        }
        $rowCount = 0;
        $errors   = [];
        $row_num  = 1;
        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < count($expected_headers)) {
                continue;
            }
            $csv_section = htmlspecialchars(trim(safeTrimCSV($data[$col_map['section_name']] ?? '')), ENT_QUOTES);
            $csv_grade   = htmlspecialchars(trim(safeTrimCSV($data[$col_map['grade_level']] ?? '')), ENT_QUOTES);
            $csv_adviser = filter_var($data[$col_map['adviser_id']] ?? '', FILTER_VALIDATE_INT);
            if (empty($csv_section) || empty($csv_grade) || !$csv_adviser) {
                $errors[] = "Row {$row_num}: Missing required fields.";
                continue;
            }
            // Check duplicate section name for same grade level
            $dup = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ?");
            $dup->bind_param("ss", $csv_section, $csv_grade);
            $dup->execute();
            $dup->store_result();
            if ($dup->num_rows > 0) {
                $errors[] = "Row {$row_num}: Section '{$csv_section}' for {$csv_grade} already exists.";
                $dup->close();
                continue;
            }
            $dup->close();
            $stmt->bind_param("ssi", $csv_section, $csv_grade, $csv_adviser);
            if (!$stmt->execute()) {
                error_log(date('c') . " EXECUTE ERR (CSV Import): " . $stmt->error . "\n", 3, $section_error_log);
                $errors[] = "Row {$row_num}: Database error during insert.";
            } else {
                $rowCount++;
            }
        }
        fclose($handle);
        $stmt->close();
        $message = "✓ CSV Import Complete\nSuccessfully imported: {$rowCount} sections\n";
        if (!empty($errors)) {
            $message .= "\n❌ Errors (" . count($errors) . " rows failed):\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        echo "<script>alert(" . json_encode($message) . "); location='section_student.php'</script>";
        exit();
    } else {
        echo "<script>alert('❌ Failed to open CSV file. Please check file permissions or file integrity.'); location='section_student.php'</script>";
        exit();
    }
}

// --- Fetch Advisors for dropdown ---
$advisers_result = $conn->query("SELECT adviser_id, CONCAT_WS(' ', firstname, lastname) AS adviser_fullname FROM advisers ORDER BY lastname, firstname");
$all_advisers = $advisers_result ? $advisers_result->fetch_all(MYSQLI_ASSOC) : [];

// Define available grade levels
$available_grade_levels = ['Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'];

// --- Fetch Sections for Table ---
$sections_query = "
    SELECT s.section_id, s.section_name, s.grade_level, s.adviser_id,
           CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_fullname
    FROM sections s
    LEFT JOIN advisers adv ON s.adviser_id = adv.adviser_id
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
    <!-- Custom fonts and styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
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
          font-size: 1.1em;
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
    <script>
      $(document).ready(function(){
        // Initialize real-time search
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
            if(visibleCount === 0 && searchTerm.length > 0 && $('#noResults').length === 0) {
                $('.custom-table tbody').append('<tr id="noResults"><td colspan="5" class="border px-3 py-2 text-center text-gray-500"><span class="material-symbols-outlined" style="font-size:1.2rem;">search_off</span> No sections found matching "' + searchTerm + '"</td></tr>');
            } else { $('#noResults').remove(); }
        });
        clearBtn.on('click', function(){
            searchInput.val('');
            clearBtn.removeClass('show');
            tableRows.show();
            $('#noResults').remove();
            searchInput.focus();
        });
        searchInput.on('keydown', function(e) { if(e.key === 'Escape') clearBtn.click(); });
      });
      
      // Open edit modal populated with data
      function openEditSectionModal(data) {
          const editModal = new bootstrap.Modal(document.getElementById('editSectionModal'));
          document.getElementById('edit_section_id').value   = data.section_id;
          document.getElementById('edit_section_name').value = data.section_name;
          document.getElementById('edit_grade_level').value  = data.grade_level;
          document.getElementById('edit_adviser_id').value   = data.adviser_id;
          editModal.show();
      }
    </script>
  </head>
  <body id="page-top">
    <?php include 'nav.php'; ?>
    <div id="wrapper">
      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          <div class="container-fluid mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="page-title-with-logo">
                <!-- Replace with your logo path -->
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
                <!-- Import CSV Button -->
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span class="material-symbols-outlined">upload_file</span> Import CSV
                </button>
                <!-- Add Section Button -->
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
                      <th class="border px-3 py-2">#</th>
                      <th class="border px-3 py-2">Section Name</th>
                      <th class="border px-3 py-2">Grade Level</th>
                      <th class="border px-3 py-2">Adviser</th>
                      <th class="border px-3 py-2">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $counter = 1;
                      if ($sections_result && $sections_result->num_rows > 0):
                        while ($row = $sections_result->fetch_assoc()):
                    ?>
                      <tr class="hover:bg-gray-50">
                        <td class="border px-3 py-2"><?= $counter++ ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['adviser_fullname'] ?? 'N/A') ?></td>
                        <td class="border px-3 py-2 actions-cell">
                          <button onclick='openEditSectionModal(<?= json_encode($row) ?>)' class="action-icon-btn edit-icon" title="Edit Section">
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
                        <td colspan="5" class="border px-3 py-2 text-center text-gray-500">No sections found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div> <!-- container-fluid -->
        </div> <!-- content -->
      </div> <!-- content-wrapper -->
    </div> <!-- wrapper -->

    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Section Name</label>
                <input type="text" class="form-control" name="section_name" placeholder="e.g., Courage" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Grade Level</label>
                <select class="form-control" name="grade_level" required>
                  <option value="">Select Grade Level</option>
                  <?php foreach ($available_grade_levels as $grade): ?>
                    <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Adviser</label>
                <select class="form-control" name="adviser_id" required>
                  <option value="">Select an Adviser</option>
                  <?php foreach ($all_advisers as $adviser): ?>
                    <option value="<?= $adviser['adviser_id'] ?>"><?= htmlspecialchars($adviser['adviser_fullname']) ?></option>
                  <?php endforeach; ?>
                </select>
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
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="edit_section_id" name="edit_section_id">
            <div class="modal-header">
              <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Section Name</label>
                <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Grade Level</label>
                <select class="form-control" id="edit_grade_level" name="edit_grade_level" required>
                  <?php foreach ($available_grade_levels as $grade): ?>
                    <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Adviser</label>
                <select class="form-control" id="edit_adviser_id" name="edit_adviser_id" required>
                  <?php foreach ($all_advisers as $adviser): ?>
                    <option value="<?= $adviser['adviser_id'] ?>"><?= htmlspecialchars($adviser['adviser_fullname']) ?></option>
                  <?php endforeach; ?>
                </select>
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
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="sections_csvfile" class="form-label">Select CSV File *</label>
                <input type="file" name="sections_csvfile" id="sections_csvfile" class="form-control" accept=".csv" required>
                <small class="text-muted">Max 5MB | Columns: section_name, grade_level, adviser_id</small>
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
  </body>
</html>